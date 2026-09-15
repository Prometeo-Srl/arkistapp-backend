<?php

namespace Database\Factories;

use App\Models\ChecklistAnswer;
use App\Models\ChecklistQuestion;
use App\Models\ChecklistSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistAnswer>
 */
class ChecklistAnswerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_submission_id' => ChecklistSubmission::factory(),
            'checklist_question_id' => ChecklistQuestion::factory(),
            'value_text' => fake()->sentence(),
            'value_date' => null,
            'value_time' => null,
            'note_text' => null,
            'selected_option_ids' => null,
            'attachment_path' => null,
        ];
    }
}
