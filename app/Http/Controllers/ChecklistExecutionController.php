<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\GranteeType;
use App\Enums\QuestionType;
use App\Http\Requests\SubmitChecklistRequest;
use App\Http\Resources\ChecklistAssignmentResource;
use App\Http\Resources\ChecklistSubmissionResource;
use App\Models\ChecklistAssignment;
use App\Models\ChecklistQuestion;
use App\Models\ChecklistSubmission;
use App\Models\MembershipRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ChecklistExecutionController extends Controller
{
    /** Assignments addressed to the current user, directly or through an org role they hold. */
    public function mine(Request $request)
    {
        $user = $request->user();
        $roleIds = $this->roleIdsFor($user);
        $companyIds = $user->memberships()->pluck('company_id');

        $assignments = ChecklistAssignment::query()
            ->where(function (Builder $query) use ($user, $roleIds, $companyIds) {
                $query->where(fn (Builder $q) => $q
                    ->where('assignee_type', GranteeType::User)
                    ->where('assignee_id', $user->getKey()));

                if ($roleIds->isNotEmpty()) {
                    $query->orWhere(fn (Builder $q) => $q
                        ->where('assignee_type', GranteeType::OrgRole)
                        ->whereIn('assignee_id', $roleIds)
                        ->whereHas('checklist', fn (Builder $cq) => $cq->whereIn('company_id', $companyIds)));
                }
            })
            ->with(['checklist.sections.questions.options', 'submission'])
            ->latest()
            ->get();

        return ChecklistAssignmentResource::collection($assignments);
    }

    /** Only the assignee may open an assignment; a second start conflicts. */
    public function start(Request $request, ChecklistAssignment $assignment)
    {
        $user = $request->user();

        abort_unless($this->targetsUser($user, $assignment), 403, 'This assignment is not addressed to you.');
        abort_if($assignment->submission()->exists(), 409, 'A submission already exists for this assignment.');

        $submission = $assignment->submission()->create([
            'submitted_by_id' => $user->getKey(),
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        $assignment->update(['status' => AssignmentStatus::InProgress]);

        return (new ChecklistSubmissionResource($submission))
            ->response()
            ->setStatusCode(201);
    }

    /** The assignee or a company admin may finalize a submission. */
    public function submit(SubmitChecklistRequest $request, ChecklistAssignment $assignment)
    {
        $user = $request->user();
        $this->authorizeExecution($user, $assignment);

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

        // Every required question must have an answer.
        $answeredIds = collect($answers)->pluck('question_id');
        $missing = $questions->filter(fn (ChecklistQuestion $question) => $question->is_required && ! $answeredIds->contains($question->getKey()));
        abort_if($missing->isNotEmpty(), 422, 'Missing required questions: '.$missing->pluck('id')->implode(', '));

        // Start is implicit when the assignee submits without calling start.
        $submission = $assignment->submission()->firstOrCreate([
            'submitted_by_id' => $user->getKey(),
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        foreach ($answers as $answer) {
            /** @var ChecklistQuestion $question */
            $question = $questions->get($answer['question_id']);
            $this->validateAnswer($question, $answer);

            $submission->answers()->updateOrCreate(
                ['checklist_question_id' => $question->getKey()],
                $this->valuePayload($question, $answer)
            );
        }

        $submission->update(['submitted_at' => now(), 'status' => 'completed']);
        $assignment->update(['status' => AssignmentStatus::Completed]);

        return new ChecklistSubmissionResource($submission->load('answers'));
    }

    /** Read-back of a submission: its submitter, the assignee, or a company admin. */
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

    private function authorizeExecution(User $user, ChecklistAssignment $assignment): void
    {
        $allowed = $this->targetsUser($user, $assignment)
            || $user->isAdminOf($assignment->checklist->company);

        abort_unless($allowed, 403);
    }

    /** Direct (user) assignment, or the user holds the targeted org role. */
    private function targetsUser(User $user, ChecklistAssignment $assignment): bool
    {
        return match ($assignment->assignee_type) {
            GranteeType::User => $assignment->assignee_id === $user->getKey(),
            GranteeType::OrgRole => $this->roleIdsFor($user)->contains($assignment->assignee_id),
        };
    }

    /** The user's org roles, mirroring AccessGrant::scopeForUser. */
    private function roleIdsFor(User $user): Collection
    {
        return MembershipRole::query()
            ->whereNull('revoked_at')
            ->whereHas('membership', fn (Builder $query) => $query->where('user_id', $user->getKey()))
            ->pluck('org_role_id');
    }

    private function validateAnswer(ChecklistQuestion $question, array $answer): void
    {
        match ($question->type) {
            QuestionType::Text => abort_unless(
                is_string($answer['value_text'] ?? null) && $answer['value_text'] !== '',
                422,
                "Question '{$question->label}' requires a text value."
            ),
            QuestionType::Date => abort_unless(
                isset($answer['value_date']) && strtotime((string) $answer['value_date']) !== false,
                422,
                "Question '{$question->label}' requires a valid date."
            ),
            QuestionType::Time => abort_unless(
                isset($answer['value_time']) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) $answer['value_time']) === 1,
                422,
                "Question '{$question->label}' requires a time in H:i format."
            ),
            QuestionType::Number => abort_unless(
                isset($answer['value_number']) && is_numeric($answer['value_number']),
                422,
                "Question '{$question->label}' requires a numeric value."
            ),
            QuestionType::Image => abort_unless(
                is_string($answer['attachment_path'] ?? null) && $answer['attachment_path'] !== '',
                422,
                "Question '{$question->label}' requires an attachment."
            ),
            QuestionType::SingleChoice, QuestionType::MultiChoice => $this->validateSelectedOptions($question, $answer),
        };
    }

    private function validateSelectedOptions(ChecklistQuestion $question, array $answer): void
    {
        $ids = $answer['selected_option_ids'] ?? null;

        abort_unless(is_array($ids) && $ids !== [], 422, "Question '{$question->label}' requires at least one selected option.");

        if ($question->type === QuestionType::SingleChoice) {
            abort_unless(count($ids) === 1, 422, "Question '{$question->label}' accepts exactly one option.");
        }

        $validIds = $question->options->pluck('id');
        abort_unless(collect($ids)->diff($validIds)->isEmpty(), 422, "Question '{$question->label}' received options that do not belong to it.");
    }

    /**
     * @return array<string, mixed>
     */
    private function valuePayload(ChecklistQuestion $question, array $answer): array
    {
        return match ($question->type) {
            QuestionType::Text => ['value_text' => $answer['value_text']],
            QuestionType::Date => ['value_date' => $answer['value_date']],
            QuestionType::Time => ['value_time' => $answer['value_time']],
            QuestionType::Number => ['value_number' => $answer['value_number']],
            QuestionType::Image => ['attachment_path' => $answer['attachment_path']],
            QuestionType::SingleChoice, QuestionType::MultiChoice => ['selected_option_ids' => $answer['selected_option_ids']],
        };
    }
}
