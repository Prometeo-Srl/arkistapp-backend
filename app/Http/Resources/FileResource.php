<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'folder_id' => $this->folder_id,
            'document_type_id' => $this->document_type_id,
            'name' => $this->name,
            'media_kind' => $this->media_kind,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'current_version_id' => $this->current_version_id,
            'issued_at' => $this->issued_at?->toDateString(),
            'expires_at' => $this->expires_at?->toDateString(),
            'requires_acknowledgement' => $this->requires_acknowledgement,
            'requires_signature' => $this->requires_signature,
            'owner_user_id' => $this->owner_user_id,
            'uploaded_by_id' => $this->uploaded_by_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'uploaded_by' => new UserResource($this->whenLoaded('uploadedBy')),
            'folder' => new FolderResource($this->whenLoaded('folder')),
            // "proprietario" on the info card: the workspace the document belongs to.
            'company_name' => $this->whenLoaded('folder', fn () => $this->folder->category?->company?->name),
            'document_type' => new DocumentTypeResource($this->whenLoaded('documentType')),
            'current_version' => new FileVersionResource($this->whenLoaded('currentVersion')),
        ];
    }
}
