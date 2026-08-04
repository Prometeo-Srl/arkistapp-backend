<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationResource extends JsonResource
{
    /**
     * The token is deliberately absent: it travels by email, not through the list endpoint.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'email' => $this->email,
            'is_admin' => $this->is_admin,
            'is_pending' => $this->isPending(),
            'expires_at' => $this->expires_at,
            'accepted_at' => $this->accepted_at,
            'org_role' => new OrgRoleResource($this->whenLoaded('orgRole')),
        ];
    }
}
