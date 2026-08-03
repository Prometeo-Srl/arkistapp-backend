<?php

namespace Tests\Feature;

use App\Enums\ChecklistStatus;
use App\Models\Checklist;
use App\Models\ChecklistAssignment;
use App\Models\ChecklistOption;
use App\Models\ChecklistQuestion;
use App\Models\ChecklistSection;
use App\Models\ChecklistSubmission;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\OrgRole;
use App\Models\User;
use Database\Seeders\OrgRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChecklistApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([OrgRoleSeeder::class]);
    }

    private function actingAsUser(?User $user = null): User
    {
        return tap($user ?? User::factory()->create(), fn (User $u) => Sanctum::actingAs($u));
    }

    private function makeCompany(User $admin): Company
    {
        $company = Company::create(['name' => 'Acme Srl', 'owner_user_id' => $admin->id]);
        CompanyMembership::factory()->for($company)->for($admin)->admin()->create();

        return $company;
    }

    private function makeWorker(Company $company): User
    {
        $worker = User::factory()->create();
        CompanyMembership::factory()->for($company)->for($worker)->create();

        return $worker;
    }

    private function makeChecklist(Company $company, User $admin): Checklist
    {
        return Checklist::factory()->for($company)->create(['created_by_id' => $admin->id]);
    }

    public function test_admin_creates_and_reads_a_checklist_template(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);

        $this->postJson("/api/companies/{$company->id}/checklists", [
            'title' => 'Verifica estintori',
            'description' => 'Controllo periodico delle attrezzature',
            'frequency' => 'monthly',
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Verifica estintori')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonCount(0, 'data.sections');

        $checklist = Checklist::firstOrFail();
        $this->assertTrue($checklist->status === ChecklistStatus::Draft);
        $this->assertSame($admin->id, $checklist->created_by_id);

        $this->getJson("/api/checklists/{$checklist->id}")->assertOk()
            ->assertJsonPath('data.title', 'Verifica estintori');
    }

    public function test_worker_cannot_manage_checklist_templates(): void
    {
        $admin = User::factory()->create();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);
        $this->actingAsUser($this->makeWorker($company));

        $this->postJson("/api/companies/{$company->id}/checklists", ['title' => 'x'])->assertForbidden();
        $this->getJson("/api/checklists/{$checklist->id}")->assertForbidden();
        $this->patchJson("/api/checklists/{$checklist->id}", ['title' => 'y'])->assertForbidden();
        $this->deleteJson("/api/checklists/{$checklist->id}")->assertForbidden();
        $this->postJson("/api/checklists/{$checklist->id}/publish")->assertForbidden();
        $this->postJson("/api/checklists/{$checklist->id}/assignments", [
            'assignee_type' => 'user',
            'assignee_id' => $admin->id,
        ])->assertForbidden();
    }

    public function test_admin_adds_sections_questions_and_options(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);

        $this->postJson("/api/checklists/{$checklist->id}/sections", ['title' => 'Sezione 1'])
            ->assertCreated()->assertJsonPath('data.title', 'Sezione 1');

        $section = ChecklistSection::firstOrFail();

        $this->postJson("/api/checklists/sections/{$section->id}/questions", [
            'label' => 'Lo stato del presidio antincendio?',
            'type' => 'single_choice',
            'is_required' => true,
            'options' => [
                ['label' => 'OK', 'is_non_conformity' => false],
                ['label' => 'Scaduto', 'is_non_conformity' => true],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.type', 'single_choice')
            ->assertJsonCount(2, 'data.options');

        $this->getJson("/api/checklists/{$checklist->id}")->assertOk()
            ->assertJsonCount(1, 'data.sections')
            ->assertJsonCount(1, 'data.sections.0.questions')
            ->assertJsonPath('data.sections.0.questions.0.options.1.is_non_conformity', true);
    }

    public function test_admin_publishes_a_checklist_idempotently(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);

        $this->postJson("/api/checklists/{$checklist->id}/publish")->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertSame(ChecklistStatus::Published, $checklist->fresh()->status);
        $this->assertNotNull($checklist->fresh()->published_at);

        // Republishing an already published template stays idempotent.
        $this->postJson("/api/checklists/{$checklist->id}/publish")->assertOk()
            ->assertJsonPath('data.status', 'published');
    }

    public function test_admin_assigns_a_checklist_to_a_user(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);
        $assignee = $this->makeWorker($company);

        $this->postJson("/api/checklists/{$checklist->id}/assignments", [
            'assignee_type' => 'user',
            'assignee_id' => $assignee->id,
        ])->assertCreated()
            ->assertJsonPath('data.assignee_type', 'user')
            ->assertJsonPath('data.assignee_user.id', $assignee->id);

        $this->assertDatabaseHas('checklist_assignments', [
            'checklist_id' => $checklist->id,
            'assignee_type' => 'user',
            'assignee_id' => $assignee->id,
            'status' => 'pending',
        ]);

        $this->getJson("/api/checklists/{$checklist->id}/assignments")->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_my_checklists_shows_only_my_assignments(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);

        $me = $this->makeWorker($company);
        $other = $this->makeWorker($company);

        ChecklistAssignment::factory()->for($checklist)->create([
            'assignee_id' => $me->id,
            'assigned_by_id' => $admin->id,
        ]);
        ChecklistAssignment::factory()->for($checklist)->create([
            'assignee_id' => $other->id,
            'assigned_by_id' => $admin->id,
        ]);

        $this->actingAsUser($me);

        $this->getJson('/api/my/checklists')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.checklist.id', $checklist->id);
    }

    public function test_assignee_starts_and_a_second_start_conflicts(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);
        $assignee = $this->makeWorker($company);
        $assignment = ChecklistAssignment::factory()->for($checklist)->create([
            'assignee_id' => $assignee->id,
            'assigned_by_id' => $admin->id,
        ]);

        $this->actingAsUser($assignee);

        $this->postJson("/api/assignments/{$assignment->id}/start")->assertCreated()
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertSame('in_progress', $assignment->fresh()->status->value);
        $this->assertDatabaseHas('checklist_submissions', [
            'checklist_assignment_id' => $assignment->id,
            'submitted_by_id' => $assignee->id,
            'status' => 'in_progress',
        ]);

        $this->postJson("/api/assignments/{$assignment->id}/start")->assertStatus(409);
    }

    public function test_only_the_assignee_can_start_an_assignment(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);
        $assignee = $this->makeWorker($company);
        $intruder = $this->makeWorker($company);
        $assignment = ChecklistAssignment::factory()->for($checklist)->create([
            'assignee_id' => $assignee->id,
            'assigned_by_id' => $admin->id,
        ]);

        $this->actingAsUser($intruder);

        $this->postJson("/api/assignments/{$assignment->id}/start")->assertForbidden();
    }

    public function test_submit_rejects_missing_required_questions(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);
        $section = ChecklistSection::factory()->for($checklist)->create();
        $required = ChecklistQuestion::factory()->for($section, 'section')->singleChoice()->create();
        $option = ChecklistOption::factory()->for($required, 'question')->create(['label' => 'OK']);

        $assignee = $this->makeWorker($company);
        $assignment = ChecklistAssignment::factory()->for($checklist)->create([
            'assignee_id' => $assignee->id,
            'assigned_by_id' => $admin->id,
        ]);

        $this->actingAsUser($assignee);
        $this->postJson("/api/assignments/{$assignment->id}/start")->assertCreated();

        // The required question is simply skipped.
        $this->postJson("/api/assignments/{$assignment->id}/submit", ['answers' => []])
            ->assertUnprocessable();

        // A foreign question id is rejected too.
        $this->postJson("/api/assignments/{$assignment->id}/submit", [
            'answers' => [['question_id' => 9999, 'selected_option_ids' => [$option->id]]],
        ])->assertStatus(422);
    }

    public function test_submit_persists_answers_and_completes_the_assignment(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);
        $section = ChecklistSection::factory()->for($checklist)->create();
        $choice = ChecklistQuestion::factory()->for($section, 'section')->singleChoice()->create();
        $option = ChecklistOption::factory()->for($choice, 'question')->create(['label' => 'OK']);
        $note = ChecklistQuestion::factory()->for($section, 'section')->create(['label' => 'Note', 'is_required' => false]);

        $assignee = $this->makeWorker($company);
        $assignment = ChecklistAssignment::factory()->for($checklist)->create([
            'assignee_id' => $assignee->id,
            'assigned_by_id' => $admin->id,
        ]);

        $this->actingAsUser($assignee);
        $this->postJson("/api/assignments/{$assignment->id}/start")->assertCreated();

        $this->postJson("/api/assignments/{$assignment->id}/submit", [
            'answers' => [
                ['question_id' => $choice->id, 'selected_option_ids' => [$option->id]],
                ['question_id' => $note->id, 'value_text' => 'Tutto in regola'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonCount(2, 'data.answers');

        $submission = ChecklistSubmission::firstOrFail();
        $this->assertSame('completed', $submission->status);
        $this->assertNotNull($submission->submitted_at);
        $this->assertSame('completed', $assignment->fresh()->status->value);
        $this->assertDatabaseHas('checklist_answers', [
            'checklist_submission_id' => $submission->id,
            'checklist_question_id' => $choice->id,
            'value_text' => null,
        ]);

        // The submitter can read the submission back with its answers.
        $this->getJson("/api/submissions/{$submission->id}")->assertOk()
            ->assertJsonCount(2, 'data.answers');
    }

    public function test_org_role_assignment_appears_in_my_checklists(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);

        $worker = $this->makeWorker($company);
        $preposto = OrgRole::where('code', 'preposto')->firstOrFail();
        $worker->memberships()->where('company_id', $company->id)->first()
            ->orgRoles()->attach($preposto->id, ['appointed_at' => now()->toDateString()]);

        ChecklistAssignment::factory()->for($checklist)->toOrgRole($preposto->id)->create([
            'assigned_by_id' => $admin->id,
        ]);

        $this->actingAsUser($worker);

        $this->getJson('/api/my/checklists')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.assignee_type', 'org_role')
            ->assertJsonPath('data.0.assignee_id', $preposto->id);
    }
}
