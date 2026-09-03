<?php

namespace App\Support;

use App\Enums\MembershipStatus;
use App\Models\Acknowledgement;
use App\Models\Category;
use App\Models\CompanyMembership;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Materializes the roster behind "presa visione" / "firma": one acknowledgement
 * row per person the document is required of, written when the duty is set rather
 * than when the receipt is given.
 *
 * Without it the concept simply is not recorded — `FileController::acknowledge`
 * creates the row at confirmation time, so somebody who has not confirmed has no
 * row anywhere. That leaves the "2/40" ring of prototype 059 with no denominator
 * and the "in attesa di completamento" list of 063 with nothing to read.
 *
 * The roster is the org chart intersected with the share: whoever the document was
 * actually shared with, guests let in by a share excluded. Company admins and the
 * owner of the personal branch it sits in are the people setting the duty, not
 * serving it.
 */
final class AcknowledgementRoster
{
    /** Brings the rows for [$file]'s current version in line with who it is required of. */
    public static function sync(File $file): void
    {
        $versionId = $file->current_version_id;

        if (! $versionId || ! $file->folder?->category?->company) {
            return;
        }

        // Duty dropped, or never there: the roster goes with it. Receipts already
        // given stay — they are the trail of a duty that did exist.
        if (! $file->requires_acknowledgement && ! $file->requires_signature) {
            Acknowledgement::query()
                ->where('file_version_id', $versionId)
                ->whereNull('confirmed_at')
                ->delete();

            return;
        }

        $expected = self::expectedUserIds($file);

        foreach ($expected as $userId) {
            Acknowledgement::query()->firstOrCreate(
                ['file_version_id' => $versionId, 'user_id' => $userId],
                ['file_id' => $file->getKey(), 'required_at' => now()],
            );
        }

        // Un-shared since: the pending duty goes, anything confirmed stays.
        Acknowledgement::query()
            ->where('file_version_id', $versionId)
            ->whereNull('confirmed_at')
            // whereNotIn on an empty list matches nothing, which would keep every
            // stale row instead of clearing them all.
            ->when($expected !== [], fn (Builder $query) => $query->whereNotIn('user_id', $expected))
            ->delete();
    }

    /**
     * Every file a grant on [$grantable] reaches, re-synced. A grant on a category
     * or a folder cascades, so sharing one folder changes the roster of every
     * document under it.
     */
    public static function syncFor(?Model $grantable): void
    {
        $files = match (true) {
            $grantable instanceof File => File::query()->whereKey($grantable->getKey()),
            $grantable instanceof Folder => File::query()->whereIn('folder_id', self::branchFolderIds($grantable)),
            $grantable instanceof Category => File::query()->whereRelation('folder', 'category_id', $grantable->getKey()),
            default => null,
        };

        if ($files === null) {
            return;
        }

        $files
            ->where(fn (Builder $query) => $query
                ->where('requires_acknowledgement', true)
                ->orWhere('requires_signature', true))
            ->whereNotNull('current_version_id')
            ->with('folder.category.company')
            ->get()
            ->each(fn (File $file) => self::sync($file));
    }

    /**
     * The people the document is required of.
     *
     * @return array<int, int>
     */
    public static function expectedUserIds(File $file): array
    {
        $company = $file->folder->category->company;

        return CompanyMembership::query()
            ->where('company_id', $company->getKey())
            // "le persone appartenenti all'organigramma": a guest let in by a share
            // is not on the chart and is not asked to confirm anything.
            ->onOrgChart()
            ->where('status', MembershipStatus::Active)
            ->where('is_admin', false)
            ->with('user')
            ->get()
            ->filter(fn (CompanyMembership $membership) => $membership->user !== null
                && $membership->user_id !== $file->owner_user_id
                && EffectiveAccess::forFile($membership->user, $file) !== null)
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The folder and everything nested under it. Same shape as the cascade in
     * {@see EffectiveAccess::visibleFolderIds}, and cheap for the same reason.
     *
     * @return array<int, int>
     */
    private static function branchFolderIds(Folder $folder): array
    {
        $ids = [$folder->getKey()];

        $candidates = Folder::query()
            ->where('category_id', $folder->category_id)
            ->get(['id', 'parent_folder_id']);

        do {
            $before = count($ids);

            foreach ($candidates as $candidate) {
                if ($candidate->parent_folder_id
                    && in_array($candidate->parent_folder_id, $ids, true)
                    && ! in_array($candidate->getKey(), $ids, true)) {
                    $ids[] = $candidate->getKey();
                }
            }
        } while (count($ids) > $before);

        return $ids;
    }
}
