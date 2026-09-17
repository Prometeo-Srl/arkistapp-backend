<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\QuestionType;
use App\Models\Category;
use App\Models\Checklist;
use App\Models\ChecklistAssignment;
use App\Models\ChecklistQuestion;
use App\Models\ChecklistSection;
use App\Models\ChecklistSubmission;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Folder;
use App\Models\Invitation;
use App\Models\User;
use Database\Seeders\OrgRoleSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression tests for defects found in code review. Each one failed before the
 * corresponding fix; none of them is a happy path already covered elsewhere.
 */
class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([OrgRoleSeeder::class, PlanSeeder::class]);
    }

    /** @return array{0: User, 1: Company} */
    private function adminOfNewCompany(): array
    {
        $admin = User::factory()->create();
        $company = Company::factory()->create();
        CompanyMembership::factory()->admin()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
        ]);

        return [$admin, $company];
    }

    public function test_an_admin_cannot_touch_a_membership_of_another_company(): void
    {
        [$attacker, $companyA] = $this->adminOfNewCompany();
        [$victim, $companyB] = $this->adminOfNewCompany();

        $victimMembership = CompanyMembership::where('company_id', $companyB->id)
            ->where('user_id', $victim->id)
            ->firstOrFail();

        Sanctum::actingAs($attacker);

        $this->patchJson(
            "/api/companies/{$companyA->id}/members/{$victimMembership->id}",
            ['is_admin' => false, 'department' => 'pwned']
        )->assertNotFound();

        $this->deleteJson("/api/companies/{$companyA->id}/members/{$victimMembership->id}")
            ->assertNotFound();

        $victimMembership->refresh();
        $this->assertTrue($victimMembership->is_admin);
        $this->assertSame(MembershipStatus::Active, $victimMembership->status);
        $this->assertNotSame('pwned', $victimMembership->department);
    }

    public function test_an_admin_cannot_delete_an_invitation_of_another_company(): void
    {
        [$attacker, $companyA] = $this->adminOfNewCompany();
        $companyB = Company::factory()->create();
        $invitation = Invitation::factory()->create(['company_id' => $companyB->id]);

        Sanctum::actingAs($attacker);

        $this->deleteJson("/api/companies/{$companyA->id}/invitations/{$invitation->id}")
            ->assertNotFound();

        $this->assertModelExists($invitation);
    }

    public function test_an_executable_document_cannot_be_uploaded(): void
    {
        Storage::fake();
        [$admin, $company] = $this->adminOfNewCompany();
        $folder = Folder::factory()->create([
            'category_id' => Category::factory()->create(['company_id' => $company->id])->id,
        ]);

        Sanctum::actingAs($admin);

        $this->post('/api/files', [
            'folder_id' => $folder->id,
            'file' => UploadedFile::fake()->createWithContent('payload.html', '<script>alert(1)</script>'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('file');

        // A renamed payload must not pass either. UploadedFile::fake() derives the MIME
        // type from the extension, so this needs a real file for finfo to sniff.
        $path = tempnam(sys_get_temp_dir(), 'probe').'.pdf';
        file_put_contents($path, '<html><script>alert(1)</script></html>');

        $this->post('/api/files', [
            'folder_id' => $folder->id,
            'file' => new UploadedFile($path, 'certificate.pdf', null, null, true),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('file');

        @unlink($path);
    }

    public function test_a_download_is_never_rendered_by_the_browser(): void
    {
        Storage::fake();
        [$admin, $company] = $this->adminOfNewCompany();
        $folder = Folder::factory()->create([
            'category_id' => Category::factory()->create(['company_id' => $company->id])->id,
        ]);

        Sanctum::actingAs($admin);

        $fileId = $this->post('/api/files', [
            'folder_id' => $folder->id,
            'file' => UploadedFile::fake()->create('certificate.pdf', 64, 'application/pdf'),
        ])->assertCreated()->json('data.id');

        $response = $this->get("/api/files/{$fileId}/download")->assertOk();

        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_login_is_rate_limited(): void
    {
        RateLimiter::clear('login:victim@example.it');
        $user = User::factory()->create(['email' => 'victim@example.it']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
                'device_name' => 'probe',
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'probe',
        ])->assertStatus(429);
    }

    /**
     * Archiving a membership must end every permission derived from it, an
     * assegnazione included: leaving the company ends the obligation with it.
     */
    public function test_an_archived_member_loses_access_to_their_assignments(): void
    {
        $worker = User::factory()->create();
        $company = Company::factory()->create();
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'user_id' => $worker->id,
        ]);

        $checklist = Checklist::factory()->published()->create(['company_id' => $company->id]);
        $assignment = ChecklistAssignment::factory()->create([
            'checklist_id' => $checklist->id,
            'assignee_user_id' => $worker->id,
        ]);

        $membership->update(['status' => MembershipStatus::Archived]);

        Sanctum::actingAs($worker);

        $this->getJson('/api/my/checklists')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/assignments/{$assignment->id}/start")->assertForbidden();
        $this->getJson("/api/checklists/{$checklist->id}")->assertForbidden();
    }

    public function test_a_company_without_a_subscription_reports_none_instead_of_failing(): void
    {
        [$admin, $company] = $this->adminOfNewCompany();

        Sanctum::actingAs($admin);

        $this->getJson("/api/companies/{$company->id}/subscription")
            ->assertOk()
            ->assertJson(['data' => null]);
    }

    public function test_starting_then_submitting_keeps_a_single_submission(): void
    {
        $worker = User::factory()->create();
        $company = Company::factory()->create();
        CompanyMembership::factory()->create(['company_id' => $company->id, 'user_id' => $worker->id]);

        $checklist = Checklist::factory()->create(['company_id' => $company->id]);
        $section = ChecklistSection::factory()->create(['checklist_id' => $checklist->id]);
        $question = ChecklistQuestion::factory()->create([
            'checklist_section_id' => $section->id,
            'type' => QuestionType::Text,
            'is_required' => true,
        ]);
        $assignment = ChecklistAssignment::factory()->create([
            'checklist_id' => $checklist->id,
            'assignee_user_id' => $worker->id,
        ]);

        Sanctum::actingAs($worker);

        $this->postJson("/api/assignments/{$assignment->id}/start")->assertCreated();

        // Nobody fills in a checklist within the same second they opened it.
        $this->travel(30)->seconds();

        $this->postJson("/api/assignments/{$assignment->id}/submit", [
            'answers' => [['question_id' => $question->id, 'value_text' => 'done']],
        ])->assertOk();

        $this->assertSame(
            1,
            ChecklistSubmission::where('checklist_assignment_id', $assignment->id)->count()
        );
    }

    /**
     * A checklist belongs to one tenant, and an id from another one must be a 404
     * rather than a 403: telling the caller the row exists is already a leak.
     */
    public function test_a_checklist_from_another_tenant_is_unreachable(): void
    {
        [$admin, $company] = $this->adminOfNewCompany();
        $stranger = Checklist::factory()->published()->create();
        $assignment = ChecklistAssignment::factory()->create(['checklist_id' => $stranger->id]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/checklists/{$stranger->id}")->assertForbidden();
        $this->getJson("/api/checklists/{$stranger->id}/pdf")->assertForbidden();
        $this->putJson("/api/checklists/{$stranger->id}/structure", ['sections' => []])->assertForbidden();
        $this->postJson("/api/assignments/{$assignment->id}/submit", [
            'answers' => [['question_id' => 1, 'value_text' => 'x']],
        ])->assertForbidden();
    }

    /** The author list is an author list, not the same list filtered per caller. */
    public function test_a_non_author_member_is_refused_the_company_checklist_list(): void
    {
        [$admin, $company] = $this->adminOfNewCompany();
        $worker = User::factory()->create();
        CompanyMembership::factory()->create(['company_id' => $company->id, 'user_id' => $worker->id]);

        Sanctum::actingAs($worker);

        $this->getJson("/api/companies/{$company->id}/checklists")->assertForbidden();
    }

    /** Nobody answers in somebody else's name, an author included. */
    public function test_an_assignment_can_only_be_answered_by_the_person_it_names(): void
    {
        [$admin, $company] = $this->adminOfNewCompany();
        $assignee = User::factory()->create();
        $intruder = User::factory()->create();
        foreach ([$assignee, $intruder] as $user) {
            CompanyMembership::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        }

        $checklist = Checklist::factory()->published()->create(['company_id' => $company->id]);
        $section = ChecklistSection::factory()->create(['checklist_id' => $checklist->id]);
        $question = ChecklistQuestion::factory()->create([
            'checklist_section_id' => $section->id,
            'type' => QuestionType::Text,
        ]);
        $assignment = ChecklistAssignment::factory()->create([
            'checklist_id' => $checklist->id,
            'assignee_user_id' => $assignee->id,
        ]);

        $payload = ['answers' => [['question_id' => $question->id, 'value_text' => 'per conto suo']]];

        Sanctum::actingAs($intruder);
        $this->postJson("/api/assignments/{$assignment->id}/submit", $payload)->assertForbidden();

        // Not even the admin who shared it.
        Sanctum::actingAs($admin);
        $this->postJson("/api/assignments/{$assignment->id}/submit", $payload)->assertForbidden();
    }

    /** The sniffed type decides, not the extension the caller typed. */
    public function test_a_disguised_upload_is_refused_as_a_checklist_image(): void
    {
        [$admin, $company] = $this->adminOfNewCompany();
        $checklist = Checklist::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($admin);

        // UploadedFile::fake() derives the MIME type from the extension, so a
        // renamed payload needs a real file for finfo to sniff.
        $path = tempnam(sys_get_temp_dir(), 'probe').'.jpg';
        file_put_contents($path, '<?php echo 1; ?>');

        $this->postJson("/api/checklists/{$checklist->id}/images", [
            'image' => new UploadedFile($path, 'estintore.jpg', null, null, true),
        ])->assertStatus(422);

        @unlink($path);

        // And a real file of a type the builder does not render.
        $this->postJson("/api/checklists/{$checklist->id}/images", [
            'image' => UploadedFile::fake()->create('verbale.pdf', 10, 'application/pdf'),
        ])->assertStatus(422);
    }

    public function test_a_client_cannot_declare_a_pdf_to_be_an_image(): void
    {
        Storage::fake();
        [$admin, $company] = $this->adminOfNewCompany();
        $folder = Folder::factory()->create([
            'category_id' => Category::factory()->create(['company_id' => $company->id])->id,
        ]);

        Sanctum::actingAs($admin);

        // media_kind used to be taken from the request, which walked a PDF past
        // the thumbnail endpoint's "images only" guard and into Imagick's
        // delegates. The sniffed type is the only thing that may decide this.
        $fileId = $this->post('/api/files', [
            'folder_id' => $folder->id,
            'media_kind' => 'image',
            'file' => UploadedFile::fake()->create('payload.pdf', 64, 'application/pdf'),
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/files/{$fileId}")
            ->assertOk()
            ->assertJsonPath('data.media_kind', 'document');

        $this->get("/api/files/{$fileId}/thumbnail")->assertNotFound();
    }

    public function test_a_checklist_image_path_cannot_escape_its_directory(): void
    {
        $admin = User::factory()->create();
        $company = Company::factory()->create();
        CompanyMembership::factory()->admin()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
        ]);
        $checklist = Checklist::factory()->create([
            'company_id' => $company->id,
            'created_by_id' => $admin->id,
        ]);

        Sanctum::actingAs($admin);

        // Storage::path() is plain concatenation, and the PDF view renders the
        // result: a traversing value embedded any readable image in the app root
        // — another tenant's incident photo included — in the returned PDF.
        $this->putJson("/api/checklists/{$checklist->id}/structure", [
            'sections' => [[
                'uuid' => '11111111-1111-4111-8111-111111111111',
                'title' => 'Sezione',
                'position' => 0,
                'questions' => [[
                    'uuid' => '22222222-2222-4222-8222-222222222222',
                    'label' => 'Domanda',
                    'type' => QuestionType::Text->value,
                    'position' => 0,
                    'image_path' => '../incidents/other-tenant.jpg',
                ]],
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors('sections.0.questions.0.image_path');
    }
}
