<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('surname')->nullable()->after('name');
            $table->string('type')->default('company_user')->after('email')->index();
            $table->string('phone')->nullable();
            $table->string('fiscal_code', 16)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('locale', 5)->default('it');
            $table->boolean('must_change_password')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('vat_number', 20)->nullable();
            $table->string('tax_code', 20)->nullable();
            $table->string('legal_address')->nullable();
            $table->string('ateco_code', 20)->nullable();
            $table->unsignedInteger('employees_count')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('status')->default('active')->index();
            $table->foreignId('created_by_operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('company_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('employee_code')->nullable();
            $table->string('department')->nullable();
            $table->date('hired_at')->nullable();
            $table->string('status')->default('active')->index();
            $table->foreignId('invited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
        });

        // Ruoli dell'organigramma ex D.Lgs 81/08. Seed statico, non gestito da UI.
        Schema::create('org_roles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('label');
            $table->boolean('is_unique_per_company')->default(false);
            $table->unsignedTinyInteger('min_required')->default(0);
            $table->unsignedSmallInteger('position')->default(0);
        });

        Schema::create('membership_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('org_role_id')->constrained()->cascadeOnDelete();
            $table->date('appointed_at')->nullable();
            $table->date('revoked_at')->nullable();
            // FK aggiunta in create_document_tables: la tabella files non esiste ancora.
            $table->unsignedBigInteger('appointment_file_id')->nullable();
            $table->timestamps();

            $table->unique(['company_membership_id', 'org_role_id']);
        });

        Schema::create('branding_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('primary_hex', 7)->nullable();
            $table->string('secondary_hex', 7)->nullable();
            $table->string('accent_hex', 7)->nullable();
            $table->string('font_family')->default('Montserrat');
            $table->string('logo_path')->nullable();
            $table->string('icon_set')->nullable();
            $table->timestamps();
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind');
            $table->string('source_path');
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_ok')->default(0);
            $table->unsignedInteger('rows_failed')->default(0);
            $table->json('report')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('branding_settings');
        Schema::dropIfExists('membership_roles');
        Schema::dropIfExists('org_roles');
        Schema::dropIfExists('company_memberships');
        Schema::dropIfExists('companies');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'surname', 'type', 'phone', 'fiscal_code', 'birth_date', 'avatar_path',
                'locale', 'must_change_password', 'last_login_at', 'deleted_at',
            ]);
        });
    }
};
