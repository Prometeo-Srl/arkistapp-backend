<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrgRoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->label,
            'is_unique_per_company' => $this->is_unique_per_company,
            'min_required' => $this->min_required,
            // Appointment dates travel on the pivot when the role comes from a membership.
            'appointed_at' => $this->whenPivotLoaded('membership_roles', fn () => $this->pivot->appointed_at?->toDateString()),
            'revoked_at' => $this->whenPivotLoaded('membership_roles', fn () => $this->pivot->revoked_at?->toDateString()),
        ];
    }
}
