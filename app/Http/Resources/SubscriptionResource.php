<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'status' => $this->status,
            'entitles' => $this->status->entitles(),
            'started_at' => $this->started_at,
            'current_period_end' => $this->current_period_end,
            'canceled_at' => $this->canceled_at,
            'superseded_by_id' => $this->superseded_by_id,
            'plan' => new PlanResource($this->whenLoaded('plan')),
        ];
    }
}
