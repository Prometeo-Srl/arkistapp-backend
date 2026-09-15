<?php

namespace Database\Factories;

use App\Models\ChecklistOption;
use App\Models\ChecklistQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistOption>
 */
class ChecklistOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_question_id' => ChecklistQuestion::factory(),
            'label' => fake()->word(),
            'image_path' => null,
            'position' => 0,
        ];
    }
}
