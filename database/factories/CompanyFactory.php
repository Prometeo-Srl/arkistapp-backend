<?php

namespace Database\Factories;

use App\Enums\WorkspaceKind;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'kind' => WorkspaceKind::Business,
            'vat_number' => fake()->numerify('###########'),
            'legal_address' => fake()->address(),
            'employees_count' => fake()->numberBetween(1, 250),
            'status' => 'active',
        ];
    }

    /** Personal workspace of an unassociated worker. */
    public function personal(?User $owner = null): static
    {
        return $this->state(fn () => [
            'kind' => WorkspaceKind::Personal,
            'owner_user_id' => $owner?->getKey() ?? User::factory(),
            'vat_number' => null,
            'employees_count' => null,
        ]);
    }

    public function createdByOperator(?User $operator = null): static
    {
        return $this->state(fn () => [
            'created_by_operator_id' => $operator?->getKey() ?? User::factory()->operator(),
        ]);
    }
}
