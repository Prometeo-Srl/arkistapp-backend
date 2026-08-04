<?php

namespace Database\Factories;

use App\Enums\MediaKind;
use App\Models\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'folder_id' => FolderFactory::new(),
            'document_type_id' => null,
            'name' => fake()->unique()->words(3, true).'.pdf',
            'media_kind' => MediaKind::Document,
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1024, 1024 * 1024),
            'current_version_id' => null,
            'issued_at' => now()->subMonths(2)->toDateString(),
            'requires_acknowledgement' => false,
            'owner_user_id' => null,
            'uploaded_by_id' => User::factory(),
        ];
    }
}
