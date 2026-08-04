<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FolderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'parent_folder_id' => $this->parent_folder_id,
            'name' => $this->name,
            'icon' => $this->icon,
            'position' => $this->position,
            'is_personal_of_user_id' => $this->is_personal_of_user_id,
            'created_at' => $this->created_at,
            'parent' => new FolderResource($this->whenLoaded('parent')),
            'children' => FolderResource::collection($this->whenLoaded('children')),
            'files' => FileResource::collection($this->whenLoaded('files')),
        ];
    }
}
