<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
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
            'severity_bucket' => $this->severity_bucket,
            'is_anonymous' => $this->is_anonymous,
            // An anonymous report must not leak who filed it.
            'reported_by' => $this->when(
                ! $this->is_anonymous && $this->resource->relationLoaded('reportedBy'),
                fn () => new UserResource($this->resource->reportedBy)
            ),
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'reported_at' => $this->reported_at?->toIso8601String(),
            'location' => $this->location,
            'department' => $this->department,
            'description' => $this->description,
            'causes' => $this->causes,
            'actions_taken' => $this->actions_taken,
            'injured_person_name' => $this->injured_person_name,
            'absence_days' => $this->absence_days,
            'inail_ref' => $this->inail_ref,
            'status' => $this->status,
            'reviewed_by' => $this->when(
                $this->resource->relationLoaded('reviewedBy'),
                fn () => new UserResource($this->resource->reviewedBy)
            ),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'attachments' => IncidentAttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
