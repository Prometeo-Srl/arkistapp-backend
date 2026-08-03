<?php

namespace Database\Factories;

use App\Enums\SupportMessageKind;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportMessage>
 */
class SupportMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'support_thread_id' => SupportThread::factory(),
            'sender_id' => User::factory(),
            'body' => fake()->sentence(),
            'kind' => SupportMessageKind::Text,
            'call_duration_seconds' => null,
            'read_at' => null,
        ];
    }

    public function call(): static
    {
        return $this->state(fn () => [
            'kind' => SupportMessageKind::CallLog,
            'body' => 'Chiamata di supporto',
            'call_duration_seconds' => fake()->numberBetween(30, 3600),
        ]);
    }

    public function read(): static
    {
        return $this->state(fn () => ['read_at' => now()]);
    }
}
