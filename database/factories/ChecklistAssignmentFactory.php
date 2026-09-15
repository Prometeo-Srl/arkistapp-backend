<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Models\Checklist;
use App\Models\ChecklistAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistAssignment>
 */
class ChecklistAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_id' => Checklist::factory(),
            'assignee_user_id' => User::factory(),
            'due_at' => null,
            'status' => AssignmentStatus::Pending,
            'assigned_by_id' => User::factory(),
        ];
    }
}
