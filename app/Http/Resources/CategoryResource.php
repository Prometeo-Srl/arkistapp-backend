<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'icon' => $this->icon,
            'color' => $this->color,
            'position' => $this->position,
            'created_at' => $this->created_at,
            'root_folders' => FolderResource::collection($this->whenLoaded('rootFolders')),
        ];
    }
}
