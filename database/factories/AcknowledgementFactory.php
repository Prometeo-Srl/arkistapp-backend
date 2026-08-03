<?php

namespace Database\Factories;

use App\Models\Acknowledgement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Acknowledgement>
 */
class AcknowledgementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_id' => FileFactory::new(),
            'file_version_id' => FileVersionFactory::new(),
            'user_id' => User::factory(),
            'required_at' => now(),
            'viewed_at' => now(),
            'confirmed_at' => now(),
            'ip_address' => fake()->ipv4(),
        ];
    }
}
