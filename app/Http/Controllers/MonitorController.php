<?php

namespace App\Http\Controllers;

use App\Enums\ActivityKind;
use App\Enums\ActivityStatus;
use App\Http\Requests\UpdateActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\PaymentResource;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Monitor & Billing slice: "Monitora attività" board, status transitions,
 * the audit trail and payment history. Read-mostly; only PATCH /activities
 * writes.
 */
class MonitorController extends Controller
{
    /** Board: company-scoped, filterable by status/kind/assignee, latest first. */
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
