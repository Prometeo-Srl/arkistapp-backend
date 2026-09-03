<?php

namespace App\Support;

use App\Enums\AssignmentStatus;
use App\Enums\IncidentKind;
use App\Enums\MembershipStatus;
use App\Enums\MonitorKind;
use App\Enums\SeverityBucket;
use App\Models\Acknowledgement;
use App\Models\Checklist;
use App\Models\ChecklistAssignment;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\File;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * "Monitora attività" (prototypes 059 and 063) and the four counters of 078.
 *
 * Read straight off the two things that carry the duty — the flags on `files` and
 * `checklist_assignments` — rather than off the `activities` table, which is a
 * materialized board no code has ever written a row into.
 *
 * ponytail: derived per request instead of materializing `activities`. A company's
 * documents run in the hundreds; add the writer and query the table if this ever
 * shows up in the timings.
 *
 * A card is one subject seen across its whole roster: the ring is completed/total
 * *people*, not one person's state.
 */
final class MonitorBoard
{
    /**
     * Every card of the board, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function items(Company $company): array
    {
        return collect([...self::fileItems($company), ...self::checklistItems($company)])
            ->sortByDesc('updated_at')
            ->values()
            ->all();
    }

    /**
     * The four cards of the home carousel: one per tipologia, always all four, so
     * the carousel keeps its shape on a workspace with nothing in it yet.
     *
     * [$title] names the leading open subject — what the card of 078 shows under
     * its ring — while the ring itself is the tipologia's whole progress.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function byKind(array $items): array
    {
        $board = collect($items);

        return collect(MonitorKind::cases())
            ->map(function (MonitorKind $kind) use ($board) {
                $of = $board->where('kind', $kind->value);
                $leading = $of->firstWhere('status', 'open') ?? $of->first();

                return [
                    'kind' => $kind->value,
                    'label' => $kind->label(),
                    'subjects' => $of->count(),
                    'completed' => (int) $of->sum('completed'),
                    'total' => (int) $of->sum('total'),
                    'title' => $leading['title'] ?? null,
                ];
            })
            ->all();
    }

    /**
     * The roster of one subject, split the two ways 063 lists it.
     *
     * @return array<string, mixed>|null
     */
    public static function detail(Company $company, string $subjectType, int $subjectId): ?array
    {
        return match ($subjectType) {
            'file' => self::fileDetail($company, $subjectId),
            'checklist' => self::checklistDetail($company, $subjectId),
            default => null,
        };
    }

    /**
     * The four counters of 078.
     *
     * @return array<string, int>
     */
    public static function summary(Company $company): array
    {
        return [
            'open_activities' => collect(self::items($company))->where('status', 'open')->count(),
            'org_chart_members' => CompanyMembership::query()
                ->where('company_id', $company->getKey())
                ->onOrgChart()
                ->where('status', '!=', MembershipStatus::Archived)
                ->count(),
            // "infortuni gravi": the over-40-days bucket, which is the line the
            // reports tab already draws.
            'serious_injuries' => $company->incidentReports()
                ->where('kind', IncidentKind::Injury)
                ->where('severity_bucket', SeverityBucket::Over40Days)
                ->count(),
            'reports' => $company->incidentReports()->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fileItems(Company $company): array
    {
        $files = File::query()
            ->whereRelation('folder.category', 'company_id', $company->getKey())
            ->where(fn (Builder $query) => $query
                ->where('requires_acknowledgement', true)
                ->orWhere('requires_signature', true))
            ->whereNotNull('current_version_id')
            ->get();

        if ($files->isEmpty()) {
            return [];
        }

        $byVersion = Acknowledgement::query()
            ->whereIn('file_version_id', $files->pluck('current_version_id'))
            ->get(['id', 'file_version_id', 'confirmed_at', 'signature_path'])
            ->groupBy('file_version_id');

        return $files
            ->map(function (File $file) use ($byVersion) {
                $kind = MonitorKind::forFile($file);

                if ($kind === null) {
                    return null;
                }

                /** @var Collection<int, Acknowledgement> $rows */
                $rows = $byVersion->get($file->current_version_id, collect());

                return self::card(
                    kind: $kind,
                    subjectType: 'file',
                    subjectId: $file->getKey(),
                    title: $file->name,
                    completed: $rows->filter(fn (Acknowledgement $ack) => self::isConfirmed($ack, $file))->count(),
                    total: $rows->count(),
                    updatedAt: $file->updated_at?->toIso8601String(),
                );
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function checklistItems(Company $company): array
    {
        return $company->checklists()
            // A draft has no assignees yet: it belongs to the builder, not the board.
            ->whereNotNull('published_at')
            ->withCount([
                'assignments as roster_count',
                'assignments as completed_count' => fn (Builder $query) => $query
                    ->where('status', AssignmentStatus::Completed),
            ])
            ->get()
            ->map(fn (Checklist $checklist) => self::card(
                kind: MonitorKind::FillChecklist,
                subjectType: 'checklist',
                subjectId: $checklist->getKey(),
                title: $checklist->title,
                completed: (int) $checklist->completed_count,
                total: (int) $checklist->roster_count,
                updatedAt: $checklist->updated_at?->toIso8601String(),
            ))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function card(
        MonitorKind $kind,
        string $subjectType,
        int $subjectId,
        string $title,
        int $completed,
        int $total,
        ?string $updatedAt,
    ): array {
        return [
            'kind' => $kind->value,
            'label' => $kind->label(),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'title' => $title,
            'completed' => $completed,
            'total' => $total,
            // "completate" only once there was somebody to complete it: a duty
            // shared with nobody is still in corso, not done.
            'status' => $total > 0 && $completed >= $total ? 'done' : 'open',
            'updated_at' => $updatedAt,
        ];
    }

    /**
     * "presa visione + firma" is one duty in two halves: the receipt is not
     * complete until the signature it asked for is on file.
     */
    private static function isConfirmed(Acknowledgement $ack, File $file): bool
    {
        return $ack->confirmed_at !== null
            && (! $file->requires_signature || $ack->signature_path !== null);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fileDetail(Company $company, int $fileId): ?array
    {
        $file = File::query()
            ->whereRelation('folder.category', 'company_id', $company->getKey())
            ->find($fileId);

        $kind = $file ? MonitorKind::forFile($file) : null;

        if ($file === null || $kind === null) {
            return null;
        }

        $rows = Acknowledgement::query()
            ->where('file_version_id', $file->current_version_id)
            ->with('user')
            ->get();

        [$done, $pending] = $rows->partition(fn (Acknowledgement $ack) => self::isConfirmed($ack, $file));
        $roles = self::roleLabels($company);

        return [
            'kind' => $kind->value,
            'label' => $kind->label(),
            'subject_type' => 'file',
            'subject_id' => $file->getKey(),
            'title' => $file->name,
            'completed' => self::acknowledgers($done, $roles),
            'pending' => self::acknowledgers($pending, $roles),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function checklistDetail(Company $company, int $checklistId): ?array
    {
        $checklist = $company->checklists()
            ->with(['assignments.assigneeUser', 'assignments.assigneeOrgRole'])
            ->find($checklistId);

        if ($checklist === null) {
            return null;
        }

        [$done, $pending] = $checklist->assignments->partition(
            fn (ChecklistAssignment $assignment) => $assignment->status === AssignmentStatus::Completed
        );
        $roles = self::roleLabels($company);

        return [
            'kind' => MonitorKind::FillChecklist->value,
            'label' => MonitorKind::FillChecklist->label(),
            'subject_type' => 'checklist',
            'subject_id' => $checklist->getKey(),
            'title' => $checklist->title,
            'completed' => self::assignees($done, $roles),
            'pending' => self::assignees($pending, $roles),
        ];
    }

    /**
     * @param  Collection<int, Acknowledgement>  $rows
     * @param  array<int, string>  $roles
     * @return array<int, array<string, mixed>>
     */
    private static function acknowledgers(Collection $rows, array $roles): array
    {
        return $rows
            ->filter(fn (Acknowledgement $ack) => $ack->user !== null)
            ->map(fn (Acknowledgement $ack) => [
                'user_id' => $ack->user_id,
                'name' => self::fullName($ack->user->name, $ack->user->surname),
                'role_label' => $roles[$ack->user_id] ?? 'lavoratore',
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * One entry per assignment rather than per person: an assignment addressed to an
     * org role carries a single status for the role, so its holders cannot be split
     * between the two lists. It is listed under the role's own name.
     *
     * @param  Collection<int, ChecklistAssignment>  $assignments
     * @param  array<int, string>  $roles
     * @return array<int, array<string, mixed>>
     */
    private static function assignees(Collection $assignments, array $roles): array
    {
        return $assignments
            ->map(function (ChecklistAssignment $assignment) use ($roles) {
                $user = $assignment->assigneeUser;

                if ($user === null) {
                    $role = $assignment->assigneeOrgRole;

                    return [
                        'user_id' => null,
                        'name' => $role?->label ?? 'assegnatario',
                        'role_label' => 'ruolo',
                    ];
                }

                return [
                    'user_id' => $user->getKey(),
                    'name' => self::fullName($user->name, $user->surname),
                    'role_label' => $roles[$user->getKey()] ?? 'lavoratore',
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * Somebody with no appointment is a plain lavoratore — the same fallback the
     * org chart directory draws.
     *
     * @return array<int, string>
     */
    private static function roleLabels(Company $company): array
    {
        return CompanyMembership::query()
            ->where('company_id', $company->getKey())
            ->onOrgChart()
            ->with('orgRoles')
            ->get()
            ->mapWithKeys(fn (CompanyMembership $membership) => [
                $membership->user_id => $membership->orgRoles->first()?->label ?? 'lavoratore',
            ])
            ->all();
    }

    private static function fullName(?string $name, ?string $surname): string
    {
        return trim(($name ?? '').' '.($surname ?? '')) ?: 'utente';
    }
}
