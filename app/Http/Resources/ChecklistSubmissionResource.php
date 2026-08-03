<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChecklistSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'checklist_assignment_id' => $this->checklist_assignment_id,
            'submitted_by_id' => $this->submitted_by_id,
            'started_at' => $this->started_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'status' => $this->status,
            'answers' => ChecklistAnswerResource::collection($this->whenLoaded('answers')),
            'submitted_by' => new UserResource($this->whenLoaded('submittedBy')),
            'assignment' => new ChecklistAssignmentResource($this->whenLoaded('assignment')),
        ];
    }
}
