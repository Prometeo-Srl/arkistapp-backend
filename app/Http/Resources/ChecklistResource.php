<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChecklistResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'frequency' => $this->frequency,
            'due_at' => $this->due_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_by_id' => $this->created_by_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'sections' => ChecklistSectionResource::collection($this->whenLoaded('sections')),
            'assignments' => ChecklistAssignmentResource::collection($this->whenLoaded('assignments')),
        ];
    }
}
