<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportThreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'opened_by' => new UserResource($this->whenLoaded('openedBy')),
            'assigned_operator' => new UserResource($this->whenLoaded('assignedOperator')),
            'channel' => $this->channel,
            'status' => $this->status,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'messages' => SupportMessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
