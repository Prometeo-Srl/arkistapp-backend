<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            OrgRoleSeeder::class,
            DocumentTypeSeeder::class,
            PlanSeeder::class,
        ]);

        // The reference seeders above are idempotent (updateOrCreate) and run on every
        // deploy. This one is not safe outside local: it mints a cross-tenant operator
        // with a factory password.
        if (! app()->isProduction()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'type' => UserType::PrometeoOperator,
            ]);
        }
    }
}
