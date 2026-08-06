<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'billing_period' => $this->billing_period,
            'trial_days' => $this->trial_days,
            'price_cents' => $this->price_cents,
            'max_users' => $this->max_users,
            'features' => $this->features,
        ];
    }
}
