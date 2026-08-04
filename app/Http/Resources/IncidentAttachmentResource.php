<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class IncidentAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'incident_report_id' => $this->incident_report_id,
            'media_kind' => $this->media_kind,
            'caption' => $this->caption,
            'url' => Storage::url($this->storage_path),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
