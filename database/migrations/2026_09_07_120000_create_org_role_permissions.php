<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Gestisci autorizzazioni" (prototype 086): per D.Lgs 81/08 role, what the
        // people holding it may see. Application permissions, so it sits beside
        // company_memberships.is_admin rather than inside the org chart itself.
        Schema::create('org_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('org_role_id')->constrained()->cascadeOnDelete();
            // Default granted: the flags landed on companies that were already
            // running, and defaulting them off would have revoked the organigramma
            // and the segnalazioni from every role at once.
            $table->boolean('can_view_org_chart')->default(true);
            $table->boolean('can_view_incidents')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'org_role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_role_permissions');
    }
};
