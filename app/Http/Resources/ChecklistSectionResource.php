<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChecklistSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // The builder reconciles on this, not on the server id.
            'uuid' => $this->uuid,
            'checklist_id' => $this->checklist_id,
            'title' => $this->title,
            'position' => $this->position,
            'questions' => ChecklistQuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
