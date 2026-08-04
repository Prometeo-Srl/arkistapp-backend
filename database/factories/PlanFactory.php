<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'billing_period' => 'monthly',
            'price_cents' => fake()->numberBetween(900, 9900),
            'max_users' => null,
            'features' => ['documents', 'checklists'],
            'is_active' => true,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'code' => 'free',
            'name' => 'Free',
            'billing_period' => null,
            'price_cents' => 0,
            'max_users' => 5,
            'features' => ['documents'],
        ]);
    }
}
