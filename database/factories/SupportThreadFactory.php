<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportThread>
 */
class SupportThreadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'opened_by_id' => User::factory(),
            'assigned_operator_id' => null,
            'channel' => 'chat',
            'status' => 'open',
            'last_message_at' => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'closed', 'closed_at' => now()]);
    }
}
