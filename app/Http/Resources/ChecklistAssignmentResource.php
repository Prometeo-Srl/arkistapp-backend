<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChecklistAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'checklist_id' => $this->checklist_id,
            'assignee_type' => $this->assignee_type,
            'assignee_id' => $this->assignee_id,
            'due_at' => $this->due_at?->toIso8601String(),
            'status' => $this->status,
            'assigned_by_id' => $this->assigned_by_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'checklist' => new ChecklistResource($this->whenLoaded('checklist')),
            'submission' => new ChecklistSubmissionResource($this->whenLoaded('submission')),
            'assignee_user' => new UserResource($this->whenLoaded('assigneeUser')),
            'assignee_org_role' => new OrgRoleResource($this->whenLoaded('assigneeOrgRole')),
        ];
    }
}
