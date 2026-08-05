<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // "Imposta Organigramma" step 1 offers four bands (micro / piccola /
            // media / grande), which does not round-trip through employees_count.
            $table->string('size_band')->nullable()->after('employees_count');
        });

        Schema::table('membership_roles', function (Blueprint $table) {
            // "rlst (rls territoriale)" is a checkbox on an RLS entry, not a role of
            // its own: the external union rep holds the same RLS appointment.
            $table->boolean('is_territorial')->default(false)->after('org_role_id');
        });
    }

    public function down(): void
    {
        Schema::table('membership_roles', function (Blueprint $table) {
            $table->dropColumn('is_territorial');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('size_band');
        });
    }
};
