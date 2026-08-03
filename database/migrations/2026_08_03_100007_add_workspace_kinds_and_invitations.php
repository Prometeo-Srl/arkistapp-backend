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
            // 'personal' = workspace del lavoratore non associato ad alcuna azienda.
            // Regge il suo archivio e il suo abbonamento individuale senza rendere
            // company_id nullable su categories/checklists/incident_reports/activities.
            $table->string('kind')->default('business')->after('name')->index();
            $table->foreignId('owner_user_id')->nullable()->after('kind')->constrained('users')->nullOnDelete();
        });

        Schema::table('company_memberships', function (Blueprint $table) {
            // Permessi applicativi, distinti dai ruoli D.Lgs 81/08 di org_roles:
            // chi ha registrato l'azienda invita e gestisce i collaboratori.
            $table->boolean('is_admin')->default(false)->after('status');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            // Piano individuale disattivato perché coperto dall'abbonamento aziendale.
            $table->foreignId('superseded_by_id')->nullable()->after('canceled_at')
                ->constrained('subscriptions')->nullOnDelete();
        });

        // Invito emesso prima che l'invitato abbia un account.
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

        // Un solo invito pendente per (azienda, email); i consumati restano come storico.
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
