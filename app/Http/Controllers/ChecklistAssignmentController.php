<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\GranteeType;
use App\Http\Requests\StoreAssignmentRequest;
use App\Http\Resources\ChecklistAssignmentResource;
use App\Models\Checklist;
use App\Models\ChecklistAssignment;
use Illuminate\Http\Request;

class ChecklistAssignmentController extends Controller
{
    public function index(Request $request, Checklist $checklist)
    {
        $this->authorize('manage', $checklist);

        return ChecklistAssignmentResource::collection(
            $checklist->assignments()->with(['submission', 'checklist'])->latest()->get()
        );
    }

    public function store(StoreAssignmentRequest $request, Checklist $checklist)
    {
        $this->authorize('manage', $checklist);

        $assignment = $checklist->assignments()->create([
            ...$request->validated(),
            'status' => AssignmentStatus::Pending,
            'assigned_by_id' => $request->user()->getKey(),
        ]);

        $assignment->load(match ($assignment->assignee_type) {
            GranteeType::User => 'assigneeUser',
            GranteeType::OrgRole => 'assigneeOrgRole',
        });

        return (new ChecklistAssignmentResource($assignment))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, ChecklistAssignment $assignment)
    {
        $this->authorize('manage', $assignment->checklist);

        $assignment->delete();

        return response()->noContent();
    }
}
