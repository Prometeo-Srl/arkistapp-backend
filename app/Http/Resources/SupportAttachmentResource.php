<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SupportAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'support_message_id' => $this->support_message_id,
            'media_kind' => $this->media_kind,
            'size_bytes' => $this->size_bytes,
            'url' => Storage::url($this->storage_path),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
