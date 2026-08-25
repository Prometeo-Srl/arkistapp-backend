<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccessGrantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grantable_type' => $this->grantable_type,
            'grantable_id' => $this->grantable_id,
            'grantee_type' => $this->grantee_type,
            'grantee_id' => $this->grantee_id,
            // The address the row is shown by: the account's when there is one, the
            // invited address while the grant is still waiting for an account.
            'email' => $this->granteeUser?->email ?? $this->invited_email,
            'name' => $this->granteeUser?->name,
            'permission' => $this->permission,
            'expires_at' => $this->expires_at,
            'granted_by_id' => $this->granted_by_id,
            'created_at' => $this->created_at,
            'grantable' => $this->whenLoaded('grantable'),
        ];
    }
}
