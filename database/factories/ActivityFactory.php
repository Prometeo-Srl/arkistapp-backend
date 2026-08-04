<?php

namespace Database\Factories;

use App\Enums\ActivityKind;
use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'subject_type' => 'company',
            'subject_id' => Company::factory(),
            'assignee_user_id' => User::factory(),
            'kind' => fake()->randomElement(ActivityKind::cases()),
            'status' => ActivityStatus::Todo,
            'due_at' => now()->addDays(7),
        ];
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'status' => ActivityStatus::Done,
            'completed_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => ActivityStatus::Overdue,
            'due_at' => now()->subDay(),
        ]);
    }
}
