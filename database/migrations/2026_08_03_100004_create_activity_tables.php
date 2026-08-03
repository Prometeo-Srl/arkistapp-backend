<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Alimenta "Monitora attività". Materializzata (e non vista) perché entrambi
        // i prototipi la filtrano e paginano per stato/tipo/scadenza.
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('kind');
            $table->string('status')->default('todo');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id'], 'activities_subject_index');
            $table->index(['company_id', 'status', 'due_at'], 'activities_board_index');
            $table->index(['assignee_user_id', 'status'], 'activities_assignee_index');
        });

        // Schema compatibile con Illuminate\Notifications\DatabaseNotification
        // (id uuid + notifiable morph + data), più le colonne di dominio Prometeo.
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel')->default('push');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id'], 'notifications_subject_index');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_auditable_index');
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('activities');
    }
};
