<?php

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\ChecklistQuestion;
use App\Models\ChecklistSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistQuestion>
 */
class ChecklistQuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_section_id' => ChecklistSection::factory(),
            'label' => fake()->sentence(4),
            'help_text' => null,
            'type' => QuestionType::Text,
            'is_required' => true,
            'allows_attachment' => false,
            'position' => 0,
        ];
    }

    public function singleChoice(): static
    {
        return $this->state(fn () => ['type' => QuestionType::SingleChoice]);
    }
}
