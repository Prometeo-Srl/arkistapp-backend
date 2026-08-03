<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentTypeResource extends JsonResource
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
            'kind' => $this->kind,
            'validity_months' => $this->validity_months,
            'reminder_offsets' => $this->reminder_offsets,
            'requires_acknowledgement_default' => $this->requires_acknowledgement_default,
        ];
    }
}
