<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('frequency')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('checklist_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedSmallInteger('position')->default(0);
        });

        // Il drag&drop del prototipo riscrive (checklist_section_id, position).
        Schema::create('checklist_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_section_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->text('help_text')->nullable();
            $table->string('type');
            $table->boolean('is_required')->default(false);
            $table->boolean('allows_attachment')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
        });

        Schema::create('checklist_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_question_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_non_conformity')->default(false);
        });

        Schema::create('checklist_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained()->cascadeOnDelete();
            $table->string('assignee_type');
            $table->unsignedBigInteger('assignee_id');
            $table->timestamp('due_at')->nullable();
            $table->string('status')->default('pending')->index();
            $table->foreignId('assigned_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['assignee_type', 'assignee_id'], 'checklist_assignments_assignee_index');
        });

        Schema::create('checklist_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('status')->default('draft');
            $table->string('export_pdf_path')->nullable();
            $table->timestamps();
        });

        Schema::create('checklist_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_question_id')->constrained()->cascadeOnDelete();
            $table->text('value_text')->nullable();
            $table->date('value_date')->nullable();
            $table->time('value_time')->nullable();
            $table->decimal('value_number', 12, 4)->nullable();
            $table->json('selected_option_ids')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamps();

            $table->unique(['checklist_submission_id', 'checklist_question_id'], 'checklist_answers_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_answers');
        Schema::dropIfExists('checklist_submissions');
        Schema::dropIfExists('checklist_assignments');
        Schema::dropIfExists('checklist_options');
        Schema::dropIfExists('checklist_questions');
        Schema::dropIfExists('checklist_sections');
        Schema::dropIfExists('checklists');
    }
};
