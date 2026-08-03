<?php

namespace Database\Factories;

use App\Enums\IncidentKind;
use App\Enums\IncidentStatus;
use App\Models\Company;
use App\Models\IncidentReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentReport>
 */
class IncidentReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'kind' => IncidentKind::Injury,
            'is_anonymous' => false,
            'reported_by_id' => User::factory(),
            'occurred_at' => now()->subDay(),
            'reported_at' => now(),
            'location' => fake()->streetName(),
            'department' => fake()->randomElement(['Produzione', 'Magazzino', 'Amministrazione']),
            'description' => fake()->sentence(),
            'injured_person_name' => fake()->name(),
            'absence_days' => fake()->numberBetween(1, 60),
            'status' => IncidentStatus::Draft,
        ];
    }

    /** The saving observer nulls reported_by_id and derives severity_bucket. */
    public function anonymous(): static
    {
        return $this->state(fn () => ['is_anonymous' => true, 'reported_by_id' => null]);
    }

    public function submitted(): static
    {
        return $this->state(fn () => ['status' => IncidentStatus::Submitted]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => IncidentStatus::Closed,
            'closed_at' => now(),
            'reviewed_by_id' => User::factory(),
        ]);
    }
}
