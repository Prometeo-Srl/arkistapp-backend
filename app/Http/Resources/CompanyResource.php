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
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'province' => $this->province,
            // "Via del Celso 12, Roma (RM) 00042" — the single indirizzo line of
            // "modifica dati personali" (prototype 080). Composed here rather
            // than in the app: the four parts are stored separately because
            // registration collects them separately, and every client that
            // shows an address wants the same joined form.
            'full_address' => $this->fullAddress(),
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
                // The appointments the caller holds here. The client gates the
                // injury flow on them; the policy is what actually enforces it.
                'roles' => $this->pivot->orgRoles()->wherePivotNull('revoked_at')->pluck('code'),
                // "Gestisci autorizzazioni" (086) resolved for the caller: what
                // their appointments let them see here. The app hides the
                // organigramma and the segnalazioni tabs on these; CompanyPolicy
                // and IncidentReportPolicy are what enforce them.
                'permissions' => $request->user()->orgPermissionsIn($this->resource),
            ]),
        ];
    }
}
