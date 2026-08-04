<?php

namespace Database\Factories;

use App\Models\PrometeoContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrometeoContact>
 */
class PrometeoContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'display_name' => fake()->name(),
            'role_label' => fake()->randomElement(['Consulente', 'Responsabile Sicurezza', 'Operatore Prometeo']),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'avatar_path' => null,
            'position' => fake()->numberBetween(0, 100),
            'is_visible' => true,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_visible' => false]);
    }
}
