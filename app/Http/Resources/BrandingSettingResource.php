<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandingSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'primary_hex' => $this->primary_hex,
            'secondary_hex' => $this->secondary_hex,
            'accent_hex' => $this->accent_hex,
            'font_family' => $this->font_family,
            'logo_path' => $this->logo_path,
            'icon_set' => $this->icon_set,
        ];
    }
}
