<?php

namespace Database\Factories;

use App\Enums\ChecklistStatus;
use App\Models\Checklist;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Checklist>
 */
class ChecklistFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'status' => ChecklistStatus::Draft,
            'created_by_id' => User::factory(),
        ];
    }

    /** Shared, and therefore frozen: no structural write is accepted any more. */
    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ChecklistStatus::Published,
            'published_at' => now(),
        ]);
    }
}
