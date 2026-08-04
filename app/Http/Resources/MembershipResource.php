<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'is_admin' => $this->is_admin,
            'employee_code' => $this->employee_code,
            'department' => $this->department,
            'hired_at' => $this->hired_at?->toDateString(),
            'user' => new UserResource($this->whenLoaded('user')),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'org_roles' => OrgRoleResource::collection($this->whenLoaded('orgRoles')),
        ];
    }
}
