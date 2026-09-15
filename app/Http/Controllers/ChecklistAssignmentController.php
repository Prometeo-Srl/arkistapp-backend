<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\ChecklistStatus;
use App\Enums\MembershipStatus;
use App\Http\Requests\ShareChecklistRequest;
use App\Http\Resources\ChecklistAssignmentResource;
use App\Http\Resources\ChecklistResource;
use App\Models\Checklist;
use App\Models\ChecklistAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChecklistAssignmentController extends Controller
{
    /** Who was asked, who has started, who has finished. */
    public function index(Request $request, Checklist $checklist)
    {
        $this->authorize('manage', $checklist);

        return ChecklistAssignmentResource::collection(
            $checklist->assignments()->with(['submission', 'assigneeUser'])->latest()->get()
        );
    }

    /**
     * Condivisione: publishing and assigning are one act (ADR-0005). In one
     * transaction the checklist is published and one assegnazione is written per
     * recipient, so a published checklist nobody can answer is not a state the
     * system can reach - and from here the questionnaire is frozen.
     *
     * Re-sharing an already published checklist adds the people who were missed,
     * without touching the structure. It is idempotent per person: somebody who
     * already holds an assegnazione keeps the one they have, answers included.
     */
    public function share(ShareChecklistRequest $request, Checklist $checklist)
    {
        $this->authorize('manage', $checklist);

        $recipients = $request->validated('assignee_user_ids');
        $dueAt = $request->validated('due_at');

        // Only people who are actually in this workspace: an assegnazione to an
        // outsider is unanswerable, because every execution check requires an
        // active membership.
        $members = $checklist->company->memberships()
            ->where('status', MembershipStatus::Active)
            ->whereIn('user_id', $recipients)
            ->pluck('user_id');

        abort_if(
            count($recipients) !== $members->count(),
            422,
            'Every recipient must be an active member of this workspace.'
        );

        DB::transaction(function () use ($checklist, $members, $dueAt, $request) {
            if ($checklist->isDraft()) {
                $checklist->update([
                    'status' => ChecklistStatus::Published,
                    'published_at' => now(),
                ]);
            }

            foreach ($members as $userId) {
                $checklist->assignments()->firstOrCreate(
                    ['assignee_user_id' => $userId],
                    [
                        'due_at' => $dueAt,
                        'status' => AssignmentStatus::Pending,
                        'assigned_by_id' => $request->user()->getKey(),
                    ]
                );
            }
        });

        return new ChecklistResource(
            $checklist->fresh(['sections.questions.options', 'createdBy', 'assignments.assigneeUser'])
        );
    }

    /**
     * Withdrawing an obligation, not erasing a record: the assegnazione is
     * cancelled rather than deleted, so a compilazione already handed in stays
     * readable (ADR-0005).
     */
    public function destroy(Request $request, ChecklistAssignment $assignment)
    {
        $this->authorize('manage', $assignment->checklist);

        $assignment->update(['status' => AssignmentStatus::Cancelled]);

        return new ChecklistAssignmentResource($assignment->fresh(['submission', 'assigneeUser']));
    }
}
