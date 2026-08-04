<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Company::personalFor() relies on firstOrCreate, which is not race-safe on its
        // own: two concurrent calls would leave a user with two personal workspaces.
        DB::statement("
            CREATE UNIQUE INDEX companies_personal_owner_unique
            ON companies (owner_user_id)
            WHERE kind = 'personal' AND deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS companies_personal_owner_unique');
    }
};
