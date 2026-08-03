<?php

namespace Database\Factories;

use App\Models\BrandingSetting;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandingSetting>
 */
class BrandingSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'primary_hex' => '#0F4C81',
            'secondary_hex' => '#2E7D32',
            'accent_hex' => '#F9A825',
            'font_family' => null,
            'logo_path' => null,
            'icon_set' => null,
        ];
    }
}
