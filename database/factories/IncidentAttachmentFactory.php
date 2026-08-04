<?php

namespace Database\Factories;

use App\Enums\MediaKind;
use App\Models\IncidentAttachment;
use App\Models\IncidentReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentAttachment>
 */
class IncidentAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incident_report_id' => IncidentReport::factory(),
            'storage_path' => 'incidents/'.fake()->uuid().'.jpg',
            'media_kind' => MediaKind::Image,
            'caption' => null,
        ];
    }
}
