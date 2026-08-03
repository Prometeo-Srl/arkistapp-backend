<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcknowledgementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_id' => $this->file_id,
            'file_version_id' => $this->file_version_id,
            'user_id' => $this->user_id,
            'required_at' => $this->required_at,
            'viewed_at' => $this->viewed_at,
            'confirmed_at' => $this->confirmed_at,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at,
        ];
    }
}
