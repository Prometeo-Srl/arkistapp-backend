<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'kind' => $this->kind,
            'status' => $this->status,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'assignee_user_id' => $this->assignee_user_id,
            'due_at' => $this->due_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'subject' => $this->whenLoaded('subject', fn () => $this->subject),
            'assignee' => new UserResource($this->whenLoaded('assignee')),
        ];
    }
}
