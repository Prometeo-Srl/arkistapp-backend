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
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // The three structure tables carry a uuid the *client* generated. It is what
        // PUT /checklists/{checklist}/structure reconciles on, and what makes a retried
        // save idempotent rather than duplicating the tree (ADR-0004).
        //
        // They are hard deleted, never soft: that only ever happens to a bozza, because
        // sharing freezes the structure (ADR-0005).
        Schema::create('checklist_sections', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('checklist_id')->constrained()->cascadeOnDelete();
            // Rich text, sanitised on write to <b> <i> <u> <br>.
            $table->text('title');
            $table->unsignedSmallInteger('position')->default(0);
        });

        // The prototype's drag & drop rewrites (checklist_section_id, position), including
        // across sections - both arrive in one reconciler payload.
        Schema::create('checklist_questions', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('checklist_section_id')->constrained()->cascadeOnDelete();
            $table->text('label');
            $table->text('help_text')->nullable();
            $table->string('type');
            // An image the *author* pins to the question, distinct from allows_attachment,
            // which lets the *filler* upload evidence.
            $table->string('image_path')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('allows_attachment')->default(false);
            // The prototype's "spazio note": a free-text box under the options.
            $table->boolean('allows_note')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
        });

        Schema::create('checklist_options', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('checklist_question_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
        });

        Schema::create('checklist_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained()->cascadeOnDelete();
            // Named people only: a role-targeted assignment cannot answer "who still owes
            // me this?" once the role's membership changes.
            $table->foreignId('assignee_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->string('status')->default('pending')->index();
            $table->foreignId('assigned_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['checklist_id', 'assignee_user_id']);
        });

        Schema::create('checklist_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_assignment_id')->constrained()->cascadeOnDelete()->unique();
            $table->foreignId('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('status')->default('in_progress');
            $table->timestamps();
        });

        Schema::create('checklist_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_question_id')->constrained()->cascadeOnDelete();
            $table->text('value_text')->nullable();
            $table->date('value_date')->nullable();
            $table->time('value_time')->nullable();
            $table->json('selected_option_ids')->nullable();
            // The filler's note, when the question allows one.
            $table->text('note_text')->nullable();
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
