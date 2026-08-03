<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // 'personal' = workspace of a worker not associated with any company.
            // It holds their archive and their individual subscription without making
            // company_id nullable on categories/checklists/incident_reports/activities.
            $table->string('kind')->default('business')->after('name')->index();
            $table->foreignId('owner_user_id')->nullable()->after('kind')->constrained('users')->nullOnDelete();
        });

        Schema::table('company_memberships', function (Blueprint $table) {
            // Application permissions, kept apart from the D.Lgs 81/08 roles in org_roles:
            // whoever registered the company invites and manages the collaborators.
            $table->boolean('is_admin')->default(false)->after('status');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            // Individual plan deactivated because the company subscription covers it.
            $table->foreignId('superseded_by_id')->nullable()->after('canceled_at')
                ->constrained('subscriptions')->nullOnDelete();
        });

        // Invitation issued before the invitee has an account.
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('email')->index();
            $table->string('token', 64)->unique();
            $table->foreignId('org_role_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_admin')->default(false);
            $table->foreignId('invited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // One pending invitation per (company, email); consumed ones remain as history.
        DB::statement('
            CREATE UNIQUE INDEX invitations_pending_unique
            ON invitations (company_id, email)
            WHERE accepted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('superseded_by_id');
        });

        Schema::table('company_memberships', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropColumn('kind');
        });
    }
};
