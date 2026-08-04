<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_id' => $this->file_id,
            'version_no' => $this->version_no,
            'size_bytes' => $this->size_bytes,
            'checksum' => $this->checksum,
            'uploaded_by_id' => $this->uploaded_by_id,
            'replaced_reason' => $this->replaced_reason,
            'created_at' => $this->created_at,
        ];
    }
}
