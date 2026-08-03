<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Regole di scadenza dinamica: validity_months guida il calcolo di files.expires_at.
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('label');
            $table->string('kind')->default('generic');
            $table->unsignedSmallInteger('validity_months')->nullable();
            $table->json('reminder_offsets')->nullable();
            $table->boolean('requires_acknowledgement_default')->default(false);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->string('color', 7)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_folder_id')->nullable()->constrained('folders')->cascadeOnDelete();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            // Cartella individuale del lavoratore: destinazione del caricamento massivo attestati.
            $table->foreignId('is_personal_of_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('media_kind')->default('document');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->boolean('requires_acknowledgement')->default(false);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('file_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('storage_path');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum')->nullable();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('replaced_reason')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['file_id', 'version_no']);
        });

        Schema::table('files', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('file_versions')->nullOnDelete();
        });

        Schema::table('membership_roles', function (Blueprint $table) {
            $table->foreign('appointment_file_id')->references('id')->on('files')->nullOnDelete();
        });

        // Condivisione granulare: grantable = categoria|cartella|file, grantee = utente|ruolo organigramma.
        Schema::create('access_grants', function (Blueprint $table) {
            $table->id();
            $table->string('grantable_type');
            $table->unsignedBigInteger('grantable_id');
            $table->string('grantee_type');
            $table->unsignedBigInteger('grantee_id');
            $table->string('permission');
            $table->foreignId('granted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['grantable_type', 'grantable_id'], 'access_grants_grantable_index');
            $table->index(['grantee_type', 'grantee_id'], 'access_grants_grantee_index');
            $table->unique(
                ['grantable_type', 'grantable_id', 'grantee_type', 'grantee_id'],
                'access_grants_unique'
            );
        });

        Schema::create('acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('file_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('required_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            // La presa visione vale per una singola versione: nuova versione = nuova conferma.
            $table->unique(['file_version_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('membership_roles', function (Blueprint $table) {
            $table->dropForeign(['appointment_file_id']);
        });

        Schema::table('files', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('acknowledgements');
        Schema::dropIfExists('access_grants');
        Schema::dropIfExists('file_versions');
        Schema::dropIfExists('files');
        Schema::dropIfExists('folders');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('document_types');
    }
};
