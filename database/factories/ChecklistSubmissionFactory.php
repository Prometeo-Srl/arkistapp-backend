<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\ChecklistAssignment;
use App\Models\ChecklistSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistSubmission>
 */
class ChecklistSubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_assignment_id' => ChecklistAssignment::factory(),
            'submitted_by_id' => User::factory(),
            'started_at' => null,
            'submitted_at' => null,
            'status' => SubmissionStatus::InProgress,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'started_at' => now(),
            'status' => SubmissionStatus::InProgress,
        ]);
    }
}
