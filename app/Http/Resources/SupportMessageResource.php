<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'support_thread_id' => $this->support_thread_id,
            'sender' => new UserResource($this->whenLoaded('sender')),
            'body' => $this->body,
            'kind' => $this->kind,
            'call_duration_seconds' => $this->call_duration_seconds,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'attachments' => SupportAttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
