<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/** Piani mostrati in "Menu - Abbonamento" (Free / Premium mensile / Premium annuale). */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'free',
                'name' => 'Free',
                'billing_period' => null,
                'price_cents' => 0,
                'max_users' => 5,
                'features' => ['documents', 'near_miss'],
            ],
            [
                'code' => 'premium_monthly',
                'name' => 'Premium mensile',
                'billing_period' => 'monthly',
                'price_cents' => 2900,
                'max_users' => null,
                'features' => ['documents', 'near_miss', 'checklists', 'org_chart', 'support_chat'],
            ],
            [
                'code' => 'premium_yearly',
                'name' => 'Premium annuale',
                'billing_period' => 'yearly',
                'price_cents' => 29000,
                'max_users' => null,
                'features' => ['documents', 'near_miss', 'checklists', 'org_chart', 'support_chat'],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
