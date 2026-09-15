<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\ChecklistStatus;
use App\Enums\SubmissionStatus;
use App\Models\Checklist;
use App\Models\ChecklistAssignment;
use App\Models\ChecklistOption;
use App\Models\ChecklistQuestion;
use App\Models\ChecklistSection;
use App\Models\ChecklistSubmission;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use Database\Seeders\OrgRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The checklist slice end to end: authoring a bozza, sharing it, answering it.
 *
 * These assert what a caller of the API can observe - the status code, the body,
 * and what a later request returns. The reconciler behind PUT .../structure has
 * no seam of its own on purpose: it has no meaning apart from that endpoint, and
 * a test that would fail if it were rewritten to the same outcome would be
 * testing the implementation.
 */
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

    /**
     * One section, one obbligatorio single-choice question, two options - the
     * smallest tree that exercises every level of the reconciler.
     *
     * @return array<string, mixed>
     */
    private function tree(array $uuids, string $sectionTitle = 'Presidi antincendio'): array
    {
        return [
            'sections' => [
                [
                    'uuid' => $uuids['section'],
                    'title' => $sectionTitle,
                    'position' => 0,
                    'questions' => [
                        [
                            'uuid' => $uuids['question'],
                            'label' => 'Lo stato del presidio?',
                            'type' => 'single_choice',
                            'is_required' => true,
                            'allows_note' => true,
                            'position' => 0,
                            'options' => [
                                ['uuid' => $uuids['ok'], 'label' => 'Conforme', 'position' => 0],
                                ['uuid' => $uuids['ko'], 'label' => 'Scaduto', 'position' => 1],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, string> */
    private function uuids(string ...$keys): array
    {
        return collect($keys)->mapWithKeys(fn (string $key) => [$key => (string) Str::uuid()])->all();
    }

    public function test_admin_creates_and_reads_a_bozza(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);

        $this->postJson("/api/companies/{$company->id}/checklists", [
            'title' => 'Verifica estintori',
            'description' => 'Controllo delle attrezzature',
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Verifica estintori')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonCount(0, 'data.sections');

        $checklist = Checklist::firstOrFail();
        $this->assertSame(ChecklistStatus::Draft, $checklist->status);
        $this->assertSame($admin->id, $checklist->created_by_id);

        $this->getJson("/api/checklists/{$checklist->id}")->assertOk()
            ->assertJsonPath('data.title', 'Verifica estintori');
    }

    public function test_worker_cannot_author_checklists(): void
    {
        $admin = User::factory()->create();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);
        $this->actingAsUser($this->makeWorker($company));

        $this->postJson("/api/companies/{$company->id}/checklists", ['title' => 'x'])->assertForbidden();
        $this->getJson("/api/companies/{$company->id}/checklists")->assertForbidden();
        $this->patchJson("/api/checklists/{$checklist->id}", ['title' => 'y'])->assertForbidden();
        $this->deleteJson("/api/checklists/{$checklist->id}")->assertForbidden();
        $this->putJson("/api/checklists/{$checklist->id}/structure", ['sections' => []])->assertForbidden();
        $this->postJson("/api/checklists/{$checklist->id}/share", [
            'assignee_user_ids' => [$admin->id],
        ])->assertForbidden();
        $this->postJson("/api/checklists/{$checklist->id}/duplicate")->assertForbidden();
    }

    public function test_the_whole_tree_round_trips(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);
        $uuids = $this->uuids('section', 'question', 'ok', 'ko');

        $this->putJson("/api/checklists/{$checklist->id}/structure", $this->tree($uuids))
            ->assertOk()
            ->assertJsonPath('data.sections.0.uuid', $uuids['section'])
            ->assertJsonPath('data.sections.0.questions.0.uuid', $uuids['question'])
            ->assertJsonPath('data.sections.0.questions.0.allows_note', true)
            ->assertJsonCount(2, 'data.sections.0.questions.0.options');

        $this->getJson("/api/checklists/{$checklist->id}")->assertOk()
            ->assertJsonPath('data.sections.0.title', 'Presidi antincendio')
            ->assertJsonPath('data.sections.0.position', 0)
            ->assertJsonPath('data.sections.0.questions.0.label', 'Lo stato del presidio?')
            ->assertJsonPath('data.sections.0.questions.0.is_required', true)
            ->assertJsonPath('data.sections.0.questions.0.options.1.label', 'Scaduto')
            ->assertJsonPath('data.sections.0.questions.0.options.1.position', 1);
    }

    /**
     * The claim that justifies client-generated uuids: a save retried after a
     * timeout costs nothing, because the second one reconciles onto the first.
     */
    public function test_the_same_payload_twice_produces_one_tree(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);
        $payload = $this->tree($this->uuids('section', 'question', 'ok', 'ko'));

        $this->putJson("/api/checklists/{$checklist->id}/structure", $payload)->assertOk();
        $this->putJson("/api/checklists/{$checklist->id}/structure", $payload)->assertOk();

        $this->assertSame(1, ChecklistSection::count());
        $this->assertSame(1, ChecklistQuestion::count());
        $this->assertSame(2, ChecklistOption::count());
    }

    public function test_a_payload_that_omits_a_question_deletes_it(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);
        $uuids = $this->uuids('section', 'question', 'ok', 'ko');

        $this->putJson("/api/checklists/{$checklist->id}/structure", $this->tree($uuids))->assertOk();

        $emptied = $this->tree($uuids);
        $emptied['sections'][0]['questions'] = [];

        $this->putJson("/api/checklists/{$checklist->id}/structure", $emptied)->assertOk()
            ->assertJsonCount(0, 'data.sections.0.questions');

        $this->assertSame(0, ChecklistQuestion::count());
        // The options went with it.
        $this->assertSame(0, ChecklistOption::count());
    }

    /**
     * Cross-section drag, which the granular endpoints could only have expressed
     * as a delete plus a create. The question keeps its id, so its answers would
     * survive the move.
     */
    public function test_a_question_moved_to_another_section_comes_back_under_it(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);
        $uuids = $this->uuids('section', 'question', 'ok', 'ko');
        $second = (string) Str::uuid();

        $payload = $this->tree($uuids);
        $payload['sections'][] = ['uuid' => $second, 'title' => 'Uscite', 'position' => 1, 'questions' => []];

        $this->putJson("/api/checklists/{$checklist->id}/structure", $payload)->assertOk();
        $questionId = ChecklistQuestion::firstOrFail()->id;

        // Same tree, with the question hanging off the second section instead.
        $moved = $payload;
        $moved['sections'][1]['questions'] = $moved['sections'][0]['questions'];
        $moved['sections'][0]['questions'] = [];

        $this->putJson("/api/checklists/{$checklist->id}/structure", $moved)->assertOk()
            ->assertJsonCount(0, 'data.sections.0.questions')
            ->assertJsonCount(1, 'data.sections.1.questions')
            ->assertJsonPath('data.sections.1.questions.0.uuid', $uuids['question']);

        $this->assertSame(1, ChecklistQuestion::count());
        $this->assertSame($questionId, ChecklistQuestion::firstOrFail()->id);
    }

    public function test_the_structure_refuses_a_uuid_belonging_to_another_checklist(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $mine = $this->makeChecklist($company, $admin);
        $theirs = $this->makeChecklist($company, $admin);
        $uuids = $this->uuids('section', 'question', 'ok', 'ko');

        $this->putJson("/api/checklists/{$theirs->id}/structure", $this->tree($uuids))->assertOk();

        $this->putJson("/api/checklists/{$mine->id}/structure", $this->tree($uuids))
            ->assertStatus(422);
    }

    /** Only <b> <i> <u> <br> survive, and stripped of their attributes. */
    public function test_rich_text_is_sanitised_on_write(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);
        $uuids = $this->uuids('section', 'question', 'ok', 'ko');

        $payload = $this->tree($uuids, '<b onclick="steal()">Presidi</b><script>alert(1)</script>');
        $payload['sections'][0]['questions'][0]['label'] = '<u>Stato</u> <img src=x onerror=y>?';

        $this->putJson("/api/checklists/{$checklist->id}/structure", $payload)->assertOk()
            ->assertJsonPath('data.sections.0.title', '<b>Presidi</b>alert(1)')
            ->assertJsonPath('data.sections.0.questions.0.label', '<u>Stato</u> ?');
    }

    public function test_granular_endpoints_still_write_one_node_at_a_time(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);

        $this->postJson("/api/checklists/{$checklist->id}/sections", ['title' => 'Sezione 1'])
            ->assertCreated()->assertJsonPath('data.title', 'Sezione 1');

        $section = ChecklistSection::firstOrFail();
        // The server fills in a uuid for a row the builder did not key itself.
        $this->assertNotNull($section->uuid);

        $this->postJson("/api/checklists/sections/{$section->id}/questions", [
            'label' => 'Lo stato del presidio antincendio?',
            'type' => 'single_choice',
            'is_required' => true,
            'options' => [
                ['label' => 'OK'],
                ['label' => 'Scaduto'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.type', 'single_choice')
            ->assertJsonCount(2, 'data.options');

        $this->getJson("/api/checklists/{$checklist->id}")->assertOk()
            ->assertJsonCount(1, 'data.sections')
            ->assertJsonCount(1, 'data.sections.0.questions');
    }

    public function test_duplicating_a_question_places_the_copy_right_after_it(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);
        $section = ChecklistSection::factory()->for($checklist)->create();
        $first = ChecklistQuestion::factory()->for($section, 'section')->singleChoice()
            ->create(['label' => 'Prima', 'position' => 0, 'image_path' => 'checklist-images/a.jpg']);
        ChecklistOption::factory()->for($first, 'question')->create(['label' => 'OK', 'position' => 0]);
        $last = ChecklistQuestion::factory()->for($section, 'section')->create(['label' => 'Ultima', 'position' => 1]);

        $this->postJson("/api/checklists/questions/{$first->id}/duplicate")->assertCreated()
            ->assertJsonPath('data.label', 'Prima')
            ->assertJsonPath('data.position', 1)
            // The image comes with it rather than being re-uploaded.
            ->assertJsonPath('data.image_path', 'checklist-images/a.jpg')
            ->assertJsonCount(1, 'data.options');

        // The question that followed made room for it.
        $this->assertSame(2, $last->fresh()->position);
        $this->assertSame(3, ChecklistQuestion::count());
    }

    public function test_sharing_publishes_and_assigns_in_one_call(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);
        $one = $this->makeWorker($company);
        $two = $this->makeWorker($company);

        $this->postJson("/api/checklists/{$checklist->id}/share", [
            'assignee_user_ids' => [$one->id, $two->id],
            'due_at' => '2026-12-31T12:00:00+00:00',
        ])->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonCount(2, 'data.assignments');

        $this->assertNotNull($checklist->fresh()->published_at);
        $this->assertDatabaseHas('checklist_assignments', [
            'checklist_id' => $checklist->id,
            'assignee_user_id' => $one->id,
            'status' => 'pending',
        ]);

        // Re-sharing adds the person who was missed and leaves the others alone.
        $three = $this->makeWorker($company);
        $this->postJson("/api/checklists/{$checklist->id}/share", [
            'assignee_user_ids' => [$one->id, $three->id],
        ])->assertOk()->assertJsonCount(3, 'data.assignments');
    }

    /** Publishing and assigning are one act: neither half survives the other failing. */
    public function test_sharing_with_an_outsider_publishes_nothing(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);
        $outsider = User::factory()->create();

        $this->postJson("/api/checklists/{$checklist->id}/share", [
            'assignee_user_ids' => [$outsider->id],
        ])->assertStatus(422);

        $this->assertSame(ChecklistStatus::Draft, $checklist->fresh()->status);
        $this->assertNull($checklist->fresh()->published_at);
        $this->assertSame(0, ChecklistAssignment::count());
    }

    /** 409, not 403: the caller is permitted, the checklist is not in a state that accepts it. */
    public function test_a_shared_checklist_rejects_every_structural_write(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);
        $section = ChecklistSection::factory()->for($checklist)->create();
        $question = ChecklistQuestion::factory()->for($section, 'section')->create();
        $worker = $this->makeWorker($company);

        $this->postJson("/api/checklists/{$checklist->id}/share", [
            'assignee_user_ids' => [$worker->id],
        ])->assertOk();

        $uuids = $this->uuids('section', 'question', 'ok', 'ko');

        $this->putJson("/api/checklists/{$checklist->id}/structure", $this->tree($uuids))->assertStatus(409);
        $this->patchJson("/api/checklists/{$checklist->id}", ['title' => 'nuovo'])->assertStatus(409);
        $this->postJson("/api/checklists/{$checklist->id}/sections", ['title' => 'x'])->assertStatus(409);
        $this->patchJson("/api/checklists/sections/{$section->id}", ['title' => 'x'])->assertStatus(409);
        $this->deleteJson("/api/checklists/sections/{$section->id}")->assertStatus(409);
        $this->postJson("/api/checklists/sections/{$section->id}/questions", [
            'label' => 'x', 'type' => 'text',
        ])->assertStatus(409);
        $this->patchJson("/api/checklists/questions/{$question->id}", ['label' => 'x'])->assertStatus(409);
        $this->deleteJson("/api/checklists/questions/{$question->id}")->assertStatus(409);
        $this->postJson("/api/checklists/questions/{$question->id}/duplicate")->assertStatus(409);
    }

    public function test_duplicating_a_shared_checklist_yields_an_editable_bozza(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);
        $uuids = $this->uuids('section', 'question', 'ok', 'ko');
        $worker = $this->makeWorker($company);

        $this->putJson("/api/checklists/{$checklist->id}/structure", $this->tree($uuids))->assertOk();
        $this->postJson("/api/checklists/{$checklist->id}/share", [
            'assignee_user_ids' => [$worker->id],
        ])->assertOk();

        $response = $this->postJson("/api/checklists/{$checklist->id}/duplicate")->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.sections.0.title', 'Presidi antincendio')
            ->assertJsonCount(2, 'data.sections.0.questions.0.options');

        $copyId = $response->json('data.id');
        $this->assertNotSame($checklist->id, $copyId);
        // A different tree: the copy must not reconcile onto the original.
        $this->assertNotSame($uuids['section'], $response->json('data.sections.0.uuid'));

        // And it accepts writes, which the original no longer does.
        $this->putJson("/api/checklists/{$copyId}/structure", [
            'sections' => [['uuid' => (string) Str::uuid(), 'title' => 'Rifatta', 'position' => 0, 'questions' => []]],
        ])->assertOk();
    }

    public function test_the_two_lists_return_different_sets_for_the_same_checklist(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $shared = $this->makeChecklist($company, $admin);
        $bozza = $this->makeChecklist($company, $admin);
        $worker = $this->makeWorker($company);

        $this->postJson("/api/checklists/{$shared->id}/share", [
            'assignee_user_ids' => [$worker->id],
        ])->assertOk();

        // The author sees both, the bozza included.
        $this->getJson("/api/companies/{$company->id}/checklists")->assertOk()->assertJsonCount(2, 'data');

        $this->actingAsUser($worker);

        $this->getJson('/api/my/checklists')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.checklist.id', $shared->id);

        // A bozza is invisible to the people who only answer.
        $this->getJson("/api/checklists/{$bozza->id}")->assertForbidden();
        $this->getJson("/api/checklists/{$shared->id}")->assertOk();
    }

    public function test_assignee_starts_and_a_second_start_conflicts(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = Checklist::factory()->for($company)->published()->create(['created_by_id' => $admin->id]);
        $assignee = $this->makeWorker($company);
        $assignment = ChecklistAssignment::factory()->for($checklist)->create([
            'assignee_user_id' => $assignee->id,
            'assigned_by_id' => $admin->id,
        ]);

        $this->actingAsUser($assignee);

        $this->postJson("/api/assignments/{$assignment->id}/start")->assertCreated()
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertSame(AssignmentStatus::InProgress, $assignment->fresh()->status);

        $this->postJson("/api/assignments/{$assignment->id}/start")->assertStatus(409);
    }

    public function test_a_missing_obbligatorio_question_blocks_the_submit_and_names_itself(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = Checklist::factory()->for($company)->published()->create(['created_by_id' => $admin->id]);
        $section = ChecklistSection::factory()->for($checklist)->create();
        $required = ChecklistQuestion::factory()->for($section, 'section')->singleChoice()
            ->create(['label' => '<b>Estintore</b> revisionato?']);
        $option = ChecklistOption::factory()->for($required, 'question')->create(['label' => 'OK']);

        $assignee = $this->makeWorker($company);
        $assignment = ChecklistAssignment::factory()->for($checklist)->create([
            'assignee_user_id' => $assignee->id,
            'assigned_by_id' => $admin->id,
        ]);

        $this->actingAsUser($assignee);

        // The message carries the label, without its markup, so the worker is not
        // left hunting for what is missing.
        $this->postJson("/api/assignments/{$assignment->id}/submit", [
            'answers' => [['question_id' => $required->id, 'selected_option_ids' => []]],
        ])->assertStatus(422)
            ->assertSee('Estintore revisionato?');

        // A question from another checklist is refused too.
        $this->postJson("/api/assignments/{$assignment->id}/submit", [
            'answers' => [['question_id' => 9999, 'selected_option_ids' => [$option->id]]],
        ])->assertStatus(422);
    }

    public function test_notes_and_attachments_land_when_allowed_and_are_refused_when_not(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = Checklist::factory()->for($company)->published()->create(['created_by_id' => $admin->id]);
        $section = ChecklistSection::factory()->for($checklist)->create();
        $open = ChecklistQuestion::factory()->for($section, 'section')
            ->create(['label' => 'Descrivi', 'allows_note' => true, 'allows_attachment' => true]);
        $closed = ChecklistQuestion::factory()->for($section, 'section')
            ->create(['label' => 'Secca', 'is_required' => false]);

        $assignee = $this->makeWorker($company);
        $assignment = ChecklistAssignment::factory()->for($checklist)->create([
            'assignee_user_id' => $assignee->id,
            'assigned_by_id' => $admin->id,
        ]);

        $this->actingAsUser($assignee);

        $this->postJson("/api/assignments/{$assignment->id}/submit", [
            'answers' => [
                ['question_id' => $closed->id, 'value_text' => 'no', 'note_text' => 'di nascosto'],
                ['question_id' => $open->id, 'value_text' => 'tutto ok'],
            ],
        ])->assertStatus(422);

        // The refused attempt left nothing behind: this one creates the compilazione,
        // which is what makes it a 201.
        $this->assertSame(0, ChecklistSubmission::count());

        $this->postJson("/api/assignments/{$assignment->id}/submit", [
            'answers' => [
                ['question_id' => $open->id, 'value_text' => 'anomalia', 'note_text' => 'sostituito ieri', 'attachment_path' => 'incidents/x.jpg'],
            ],
        ])->assertSuccessful();

        $this->assertDatabaseHas('checklist_answers', [
            'checklist_question_id' => $open->id,
            'note_text' => 'sostituito ieri',
            'attachment_path' => 'incidents/x.jpg',
        ]);
    }

    public function test_submit_persists_the_compilazione_and_completes_the_assegnazione(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = Checklist::factory()->for($company)->published()->create(['created_by_id' => $admin->id]);
        $section = ChecklistSection::factory()->for($checklist)->create();
        $choice = ChecklistQuestion::factory()->for($section, 'section')->singleChoice()->create();
        $option = ChecklistOption::factory()->for($choice, 'question')->create(['label' => 'OK']);
        $free = ChecklistQuestion::factory()->for($section, 'section')->create(['label' => 'Note', 'is_required' => false]);

        $assignee = $this->makeWorker($company);
        $assignment = ChecklistAssignment::factory()->for($checklist)->create([
            'assignee_user_id' => $assignee->id,
            'assigned_by_id' => $admin->id,
        ]);

        $this->actingAsUser($assignee);
        $this->postJson("/api/assignments/{$assignment->id}/start")->assertCreated();

        $this->postJson("/api/assignments/{$assignment->id}/submit", [
            'answers' => [
                ['question_id' => $choice->id, 'selected_option_ids' => [$option->id]],
                ['question_id' => $free->id, 'value_text' => 'Tutto in regola'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonCount(2, 'data.answers');

        $submission = ChecklistSubmission::firstOrFail();
        $this->assertSame(SubmissionStatus::Completed, $submission->status);
        $this->assertSame(AssignmentStatus::Completed, $assignment->fresh()->status);

        // One compilazione per assegnazione: handing it in again conflicts.
        $this->postJson("/api/assignments/{$assignment->id}/submit", [
            'answers' => [['question_id' => $choice->id, 'selected_option_ids' => [$option->id]]],
        ])->assertStatus(409);

        $this->getJson("/api/submissions/{$submission->id}")->assertOk()
            ->assertJsonCount(2, 'data.answers');
    }

    public function test_withdrawing_an_assegnazione_cancels_it_and_keeps_what_was_answered(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = Checklist::factory()->for($company)->published()->create(['created_by_id' => $admin->id]);
        $section = ChecklistSection::factory()->for($checklist)->create();
        $question = ChecklistQuestion::factory()->for($section, 'section')->create(['label' => 'Stato']);

        $assignee = $this->makeWorker($company);
        $assignment = ChecklistAssignment::factory()->for($checklist)->create([
            'assignee_user_id' => $assignee->id,
            'assigned_by_id' => $admin->id,
        ]);

        $this->actingAsUser($assignee);
        // 201 rather than 200: handing in without calling start first creates the
        // compilazione in this same request.
        $this->postJson("/api/assignments/{$assignment->id}/submit", [
            'answers' => [['question_id' => $question->id, 'value_text' => 'fatto']],
        ])->assertSuccessful();
        $submission = ChecklistSubmission::firstOrFail();

        $this->actingAsUser($admin);
        $this->deleteJson("/api/assignments/{$assignment->id}")->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        // The obligation ended; the record did not.
        $this->assertDatabaseHas('checklist_assignments', ['id' => $assignment->id, 'status' => 'cancelled']);
        $this->getJson("/api/submissions/{$submission->id}")->assertOk()
            ->assertJsonPath('data.answers.0.value_text', 'fatto');

        // And it leaves the worker's queue.
        $this->actingAsUser($assignee);
        $this->getJson('/api/my/checklists')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_questionnaire_downloads_as_a_pdf_for_an_author_and_an_assignee(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);
        $worker = $this->makeWorker($company);

        $this->putJson("/api/checklists/{$checklist->id}/structure", $this->tree($this->uuids('section', 'question', 'ok', 'ko')))
            ->assertOk();
        $this->postJson("/api/checklists/{$checklist->id}/share", [
            'assignee_user_ids' => [$worker->id],
        ])->assertOk();

        $this->get("/api/checklists/{$checklist->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAsUser($worker);
        $this->get("/api/checklists/{$checklist->id}/pdf")->assertOk();
    }

    public function test_an_author_uploads_an_image_for_a_question(): void
    {
        $admin = $this->actingAsUser();
        $company = $this->makeCompany($admin);
        $checklist = $this->makeChecklist($company, $admin);

        $response = $this->postJson("/api/checklists/{$checklist->id}/images", [
            'image' => UploadedFile::fake()->image('estintore.jpg'),
        ])->assertCreated();

        $path = $response->json('data.image_path');
        $this->assertStringStartsWith('checklist-images/', $path);

        $this->get("/api/checklists/{$checklist->id}/images/".basename($path))->assertOk();
    }
}
