<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'kind' => $this->kind,
            'vat_number' => $this->vat_number,
            'tax_code' => $this->tax_code,
            'legal_address' => $this->legal_address,
            'ateco_code' => $this->ateco_code,
            'employees_count' => $this->employees_count,
            'logo_path' => $this->logo_path,
            'status' => $this->status,
            'owner_user_id' => $this->owner_user_id,
            'branding' => $this->whenLoaded('brandingSetting'),
            'subscription' => new SubscriptionResource($this->whenLoaded('entitlingSubscription')),
            // Present only on the workspace list, where the pivot rides along.
            'membership' => $this->whenPivotLoaded('company_memberships', fn () => [
                'is_admin' => (bool) $this->pivot->is_admin,
                'status' => $this->pivot->status,
                'department' => $this->pivot->department,
            ]),
        ];
    }
}
