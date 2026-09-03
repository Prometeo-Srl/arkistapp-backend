<?php

namespace App\Http\Controllers;

use App\Enums\ActivityKind;
use App\Enums\ActivityStatus;
use App\Enums\MonitorKind;
use App\Http\Requests\UpdateActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\PaymentResource;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Subscription;
use App\Support\MonitorBoard;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Monitor & Billing slice: "Monitora attività" (prototypes 059/063), the four
 * counters of 078, the audit trail and payment history. Read-mostly; only
 * PATCH /activities writes.
 *
 * Two boards live here, and they are not the same thing:
 *
 * - {@see board}, {@see subject} and {@see summary} are what the app renders.
 *   They are derived per request from the duties on `files` and
 *   `checklist_assignments` — see {@see MonitorBoard}.
 * - {@see index} and {@see update} are the per-assignee `activities` rows.
 *   Nothing writes that table today, so the endpoint answers an empty board; it
 *   is kept because it is the shape a materialized board would take.
 */
class MonitorController extends Controller
{
    /** The board of 059: one card per subject, counted across its whole roster. */
    public function board(Request $request, Company $company)
    {
        $this->authorize('view', $company);

        $filters = $request->validate([
            'kind' => ['nullable', 'array'],
            'kind.*' => [Rule::enum(MonitorKind::class)],
            'status' => ['nullable', 'in:open,done'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $items = collect(MonitorBoard::items($company));

        // The tab counts and the home carousel describe the whole board, so they are
        // taken before the filters narrow it: 059 shows "in corso (4) / completate (2)"
        // with a tipologia filter applied.
        $meta = [
            'counts' => [
                'open' => $items->where('status', 'open')->count(),
                'done' => $items->where('status', 'done')->count(),
            ],
            'by_kind' => MonitorBoard::byKind($items->all()),
        ];

        if ($kinds = $filters['kind'] ?? null) {
            $items = $items->whereIn('kind', $kinds);
        }

        if ($status = $filters['status'] ?? null) {
            $items = $items->where('status', $status);
        }

        if ($needle = mb_strtolower(trim($filters['q'] ?? ''))) {
            $items = $items->filter(fn (array $item) => str_contains(mb_strtolower($item['title']), $needle));
        }

        return response()->json(['data' => $items->values()->all(), 'meta' => $meta]);
    }

    /** One card's roster, split into "hanno completato" / "in attesa" (063). */
    public function subject(Request $request, Company $company, string $subjectType, int $subjectId)
    {
        $this->authorize('view', $company);

        $detail = MonitorBoard::detail($company, $subjectType, $subjectId);
        abort_unless($detail !== null, 404);

        return response()->json(['data' => $detail]);
    }

    /** The four counters of the home page (078). */
    public function summary(Request $request, Company $company)
    {
        $this->authorize('view', $company);

        return response()->json(['data' => MonitorBoard::summary($company)]);
    }

    /** `activities` rows: company-scoped, filterable by status/kind/assignee, latest first. */
    public function index(Request $request, Company $company)
    {
        // Through the policy, so the operator's before() bypass applies here too:
        // a raw isMemberOf() check locked the super admin out of the board.
        $this->authorize('view', $company);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ActivityStatus::class)],
            'kind' => ['nullable', Rule::enum(ActivityKind::class)],
            'assignee_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $activities = $company->activities()
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['kind'] ?? null, fn ($query, $kind) => $query->where('kind', $kind))
            ->when($filters['assignee_user_id'] ?? null, fn ($query, $userId) => $query->where('assignee_user_id', $userId))
            ->with(['subject', 'assignee'])
            ->latest()
            ->paginate($request->integer('per_page', 50));

        return ActivityResource::collection($activities);
    }

    /** Status transition (open → done); admins may also reassign or reschedule. */
    public function update(UpdateActivityRequest $request, Activity $activity)
    {
        $this->authorize('update', $activity);

        $data = $request->validated();
        $isAdmin = $request->user()->isOperator() || $request->user()->isAdminOf($activity->company);

        if (! $isAdmin) {
            unset($data['assignee_user_id'], $data['due_at']);
        }

        if (isset($data['status'])) {
            $status = ActivityStatus::from($data['status']);
            $data['status'] = $status;
            if ($status === ActivityStatus::Done) {
                $data['completed_at'] = now();
            } elseif ($activity->status === ActivityStatus::Done) {
                // Reopened: clear the stale completion stamp.
                $data['completed_at'] = null;
            }
        }

        $activity->update($data);

        return new ActivityResource($activity->fresh()->load(['subject', 'assignee']));
    }

    /** Audit trail is sensitive: admins and operators only. */
    public function auditLog(Request $request, Company $company)
    {
        $this->authorize('update', $company);

        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $logs = $company->auditLogs()
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->with('user')
            ->latest()
            ->paginate($request->integer('per_page', 50));

        return AuditLogResource::collection($logs);
    }

    /** Billing history of a subscription; open to every member of the company. */
    public function payments(Request $request, Subscription $subscription)
    {
        $this->authorize('viewSubscription', $subscription->company);

        return PaymentResource::collection(
            $subscription->payments()->latest()->paginate($request->integer('per_page', 50))
        );
    }
}
