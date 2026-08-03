<?php

namespace Database\Factories;

use App\Models\Checklist;
use App\Models\ChecklistSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistSection>
 */
class ChecklistSectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_id' => Checklist::factory(),
            'title' => fake()->words(3, true),
            'position' => 0,
        ];
    }
}
