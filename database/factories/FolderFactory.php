<?php

namespace Database\Factories;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Folder>
 */
class FolderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => CategoryFactory::new(),
            'parent_folder_id' => null,
            'name' => fake()->unique()->words(2, true),
            'icon' => null,
            'position' => 0,
            'is_personal_of_user_id' => null,
            'created_by_id' => User::factory(),
        ];
    }
}
