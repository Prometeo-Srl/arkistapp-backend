<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\QuestionType;
use App\Enums\SubmissionStatus;
use App\Http\Requests\SubmitChecklistRequest;
use App\Http\Resources\ChecklistAssignmentResource;
use App\Http\Resources\ChecklistSubmissionResource;
use App\Models\ChecklistAssignment;
use App\Models\ChecklistQuestion;
use App\Models\ChecklistSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChecklistExecutionController extends Controller
{
    /**
     * The worker's list: their own assegnazioni and nothing else. Deliberately a
     * different endpoint from the author's list rather than one that returns a
     * different dataset per caller.
     */
    public function mine(Request $request)
    {
        $user = $request->user();

        $assignments = ChecklistAssignment::query()
            ->where('assignee_user_id', $user->getKey())
            ->where('status', '!=', AssignmentStatus::Cancelled)
            // An archived membership ends every derived permission: a worker who
            // left the company keeps no open work.
            ->whereHas('checklist', fn (Builder $query) => $query->whereIn('company_id', $user->activeCompanyIds()))
            ->with(['checklist.sections.questions.options', 'submission'])
            ->latest()
            ->get();

        return ChecklistAssignmentResource::collection($assignments);
    }

    /**
     * Telemetry only: it stamps started_at so the author's oversight view can show
     * who has begun. There is no partial save to resume - the wizard holds the
     * answers client-side and hands the compilazione over whole.
     */
    public function start(Request $request, ChecklistAssignment $assignment)
    {
        $user = $request->user();

        abort_unless($this->targetsUser($user, $assignment), 403, 'This assignment is not addressed to you.');
        abort_if($assignment->submission()->exists(), 409, 'A submission already exists for this assignment.');

        $submission = $assignment->submission()->create([
            'submitted_by_id' => $user->getKey(),
            'started_at' => now(),
            'status' => SubmissionStatus::InProgress,
        ]);

        $assignment->update(['status' => AssignmentStatus::InProgress]);

        return (new ChecklistSubmissionResource($submission))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * The compilazione, handed over in one act. The unique constraint on
     * checklist_assignment_id is what keeps it to one per assegnazione.
     */
    public function submit(SubmitChecklistRequest $request, ChecklistAssignment $assignment)
    {
        $user = $request->user();

        // Only the person it was addressed to: nobody answers in somebody else's
        // name, an author included.
        abort_unless($this->targetsUser($user, $assignment), 403, 'This assignment is not addressed to you.');
        abort_if(
            $assignment->status === AssignmentStatus::Cancelled,
            409,
            'This assignment has been withdrawn.'
        );

        $checklist = $assignment->checklist()->with('sections.questions.options')->firstOrFail();
        $questions = $checklist->sections->flatMap->questions->keyBy('id');
        $answers = $request->validated('answers');

        // Every submitted question must belong to this checklist.
        foreach ($answers as $answer) {
            abort_unless(
                $questions->has($answer['question_id']),
                422,
                "Question {$answer['question_id']} does not belong to this checklist."
            );
        }

        // Every obbligatorio question must be answered, and the error names the
        // ones that are not: the worker should not have to hunt for what is missing.
        $answeredIds = collect($answers)->pluck('question_id');
        $missing = $questions->filter(
            fn (ChecklistQuestion $question) => $question->is_required && ! $answeredIds->contains($question->getKey())
        );
        abort_if(
            $missing->isNotEmpty(),
            422,
            'Missing required questions: '.$missing->map(
                fn (ChecklistQuestion $question) => strip_tags($question->label)
            )->implode('; ')
        );

        abort_if(
            $assignment->submission?->status === SubmissionStatus::Completed,
            409,
            'This checklist has already been handed in.'
        );

        // Every answer is checked before any of them is written: a compilazione is
        // handed over whole, so a rejected submit must leave nothing behind rather
        // than storing the answers it got through before the bad one.
        foreach ($answers as $answer) {
            $this->validateAnswer($questions->get($answer['question_id']), $answer);
        }

        $submission = DB::transaction(function () use ($assignment, $user, $answers, $questions) {
            // Start is implicit when the worker submits without calling start. Reuse
            // the existing submission rather than firstOrCreate(), which would match
            // on started_at too and write a second row a second later.
            $submission = $assignment->submission ?? $assignment->submission()->create([
                'submitted_by_id' => $user->getKey(),
                'started_at' => now(),
                'status' => SubmissionStatus::InProgress,
            ]);

            foreach ($answers as $answer) {
                /** @var ChecklistQuestion $question */
                $question = $questions->get($answer['question_id']);

                $submission->answers()->updateOrCreate(
                    ['checklist_question_id' => $question->getKey()],
                    $this->valuePayload($question, $answer)
                );
            }

            $submission->update(['submitted_at' => now(), 'status' => SubmissionStatus::Completed]);
            $assignment->update(['status' => AssignmentStatus::Completed]);

            return $submission;
        });

        return new ChecklistSubmissionResource($submission->load('answers'));
    }

    /** Read-back: its submitter, or an author of the checklist. */
    public function show(Request $request, ChecklistSubmission $submission)
    {
        $user = $request->user();
        $assignment = $submission->assignment()->with('checklist.company')->firstOrFail();

        $allowed = $submission->submitted_by_id === $user->getKey()
            || $this->targetsUser($user, $assignment)
            || $user->isAdminOf($assignment->checklist->company);

        abort_unless($allowed, 403);

        return new ChecklistSubmissionResource($submission->load(['answers.question', 'submittedBy']));
    }

    /** The named assignee, whose membership in the checklist's company is still active. */
    private function targetsUser(User $user, ChecklistAssignment $assignment): bool
    {
        return $assignment->assignee_user_id === $user->getKey()
            && $user->activeCompanyIds()->contains($assignment->checklist->company_id);
    }

    /**
     * @param  array<string, mixed>  $answer
     */
    private function validateAnswer(ChecklistQuestion $question, array $answer): void
    {
        abort_if(
            ! $question->allows_note && filled($answer['note_text'] ?? null),
            422,
            "Question '{$this->plain($question)}' does not accept a note."
        );

        abort_if(
            ! $question->allows_attachment && filled($answer['attachment_path'] ?? null),
            422,
            "Question '{$this->plain($question)}' does not accept an attachment."
        );

        match ($question->type) {
            QuestionType::Text => abort_unless(
                is_string($answer['value_text'] ?? null) && $answer['value_text'] !== '',
                422,
                "Question '{$this->plain($question)}' requires a text value."
            ),
            QuestionType::Date => abort_unless(
                isset($answer['value_date']) && strtotime((string) $answer['value_date']) !== false,
                422,
                "Question '{$this->plain($question)}' requires a valid date."
            ),
            QuestionType::Time => abort_unless(
                isset($answer['value_time']) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) $answer['value_time']) === 1,
                422,
                "Question '{$this->plain($question)}' requires a time in H:i format."
            ),
            QuestionType::SingleChoice, QuestionType::MultiChoice => $this->validateSelectedOptions($question, $answer),
        };
    }

    /**
     * @param  array<string, mixed>  $answer
     */
    private function validateSelectedOptions(ChecklistQuestion $question, array $answer): void
    {
        $ids = $answer['selected_option_ids'] ?? null;
        $label = $this->plain($question);

        abort_unless(is_array($ids) && $ids !== [], 422, "Question '{$label}' requires at least one selected option.");

        if ($question->type === QuestionType::SingleChoice) {
            abort_unless(count($ids) === 1, 422, "Question '{$label}' accepts exactly one option.");
        }

        $validIds = $question->options->pluck('id');
        abort_unless(collect($ids)->diff($validIds)->isEmpty(), 422, "Question '{$label}' received options that do not belong to it.");
    }

    /**
     * @param  array<string, mixed>  $answer
     * @return array<string, mixed>
     */
    private function valuePayload(ChecklistQuestion $question, array $answer): array
    {
        return [
            ...match ($question->type) {
                QuestionType::Text => ['value_text' => $answer['value_text']],
                QuestionType::Date => ['value_date' => $answer['value_date']],
                QuestionType::Time => ['value_time' => $answer['value_time']],
                QuestionType::SingleChoice, QuestionType::MultiChoice => ['selected_option_ids' => $answer['selected_option_ids']],
            },
            'note_text' => $question->allows_note ? ($answer['note_text'] ?? null) : null,
            'attachment_path' => $question->allows_attachment ? ($answer['attachment_path'] ?? null) : null,
        ];
    }

    /** A question label carries markup; an error message should not. */
    private function plain(ChecklistQuestion $question): string
    {
        return strip_tags($question->label);
    }
}
