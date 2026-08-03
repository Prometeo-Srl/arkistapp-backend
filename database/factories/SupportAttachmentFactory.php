<?php

namespace Database\Factories;

use App\Enums\MediaKind;
use App\Models\SupportAttachment;
use App\Models\SupportMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportAttachment>
 */
class SupportAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'support_message_id' => SupportMessage::factory(),
            'storage_path' => 'support/'.fake()->uuid().'.jpg',
            'media_kind' => MediaKind::Image,
            'size_bytes' => fake()->numberBetween(1024, 5_000_000),
        ];
    }
}
