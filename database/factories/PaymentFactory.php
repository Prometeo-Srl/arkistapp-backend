<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'amount_cents' => fake()->numberBetween(1000, 99900),
            'currency' => 'EUR',
            'status' => 'paid',
            'paid_at' => now()->subDays(fake()->numberBetween(0, 60)),
            'provider_ref' => fake()->uuid(),
            'invoice_path' => fake()->optional()->filePath(),
        ];
    }
}
