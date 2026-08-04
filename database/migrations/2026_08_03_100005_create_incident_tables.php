<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->index();
            // under_40_days | over_40_days, derived from absence_days.
            $table->string('severity_bucket')->nullable()->index();
            $table->boolean('is_anonymous')->default(false);
            // Must be NULL when is_anonymous: an anonymous report may not be traceable
            // back to the worker (requirement from the specification).
            $table->foreignId('reported_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->string('location')->nullable();
            $table->string('department')->nullable();
            $table->text('description')->nullable();
            $table->text('causes')->nullable();
            $table->text('actions_taken')->nullable();
            $table->string('injured_person_name')->nullable();
            $table->unsignedSmallInteger('absence_days')->nullable();
            $table->string('inail_ref')->nullable();
            $table->string('status')->default('draft')->index();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('incident_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_report_id')->constrained()->cascadeOnDelete();
            $table->string('storage_path');
            $table->string('media_kind')->default('image');
            $table->string('caption')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_attachments');
        Schema::dropIfExists('incident_reports');
    }
};
