<?php

namespace Database\Factories;

use App\Models\FileVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileVersion>
 */
class FileVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_id' => FileFactory::new(),
            'version_no' => 1,
            'storage_path' => 'documents/'.fake()->numberBetween(1, 99).'/'.fake()->sha256().'.pdf',
            'size_bytes' => fake()->numberBetween(1024, 1024 * 1024),
            'checksum' => fake()->sha256(),
            'uploaded_by_id' => User::factory(),
            'replaced_reason' => null,
        ];
    }
}
