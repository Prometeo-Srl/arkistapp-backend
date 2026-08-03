<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyMembership>
 */
class CompanyMembershipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'status' => MembershipStatus::Active,
            'is_admin' => false,
            'department' => fake()->randomElement(['Produzione', 'Magazzino', 'Amministrazione', 'Manutenzione']),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['is_admin' => true]);
    }

    public function invited(): static
    {
        return $this->state(fn () => ['status' => MembershipStatus::Invited]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => MembershipStatus::Archived]);
    }
}
