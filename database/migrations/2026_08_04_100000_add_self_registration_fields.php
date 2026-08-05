<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Self-registration ("Registrazione" step 3) asks for email and password only:
            // the personal name is genuinely unknown until the user fills in their profile.
            $table->string('name')->nullable()->change();
        });

        Schema::table('companies', function (Blueprint $table) {
            // "Registrazione" step 2 collects the address as four separate fields
            // (indirizzo, cap, comune, provincia); legal_address holds the street only.
            $table->string('postal_code', 10)->nullable()->after('legal_address');
            $table->string('city')->nullable()->after('postal_code');
            $table->string('province', 2)->nullable()->after('city');
        });

        // Self-registration is open to the public, so nothing but this index stops two
        // strangers from registering the same company. Partial: personal workspaces have
        // no VAT number, and a soft-deleted company must not block re-registration.
        DB::statement("
            CREATE UNIQUE INDEX companies_business_vat_unique
            ON companies (vat_number)
            WHERE kind = 'business' AND vat_number IS NOT NULL AND deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS companies_business_vat_unique');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['postal_code', 'city', 'province']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });
    }
};
