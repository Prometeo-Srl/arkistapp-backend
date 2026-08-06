<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/** Plans shown in "Menu - Abbonamento" (Free / Premium monthly / Premium yearly). */
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
                // "€10,00 per mese", "7 giorni prova gratuita" (screen 042).
                'trial_days' => 7,
                'price_cents' => 1000,
                'max_users' => null,
                'features' => ['documents', 'near_miss', 'checklists', 'org_chart', 'support_chat'],
            ],
            [
                'code' => 'premium_yearly',
                'name' => 'Premium annuale',
                'billing_period' => 'yearly',
                // "€100,00 per anno", "1 mese prova gratuita" (screen 041).
                'trial_days' => 30,
                'price_cents' => 10000,
                'max_users' => null,
                'features' => ['documents', 'near_miss', 'checklists', 'org_chart', 'support_chat'],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
