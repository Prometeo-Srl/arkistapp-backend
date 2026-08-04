<?php

namespace Tests\Feature;

use App\Enums\GranteeType;
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
use App\Models\OrgRole;
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

    public function test_an_archived_member_loses_role_derived_access(): void
    {
        $worker = User::factory()->create();
        $company = Company::factory()->create();
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'user_id' => $worker->id,
        ]);
        $preposto = OrgRole::where('code', 'preposto')->firstOrFail();
        $membership->orgRoles()->attach($preposto);

        $checklist = Checklist::factory()->create(['company_id' => $company->id]);
        $assignment = ChecklistAssignment::factory()->create([
            'checklist_id' => $checklist->id,
            'assignee_type' => GranteeType::OrgRole,
            'assignee_id' => $preposto->id,
        ]);

        $membership->update(['status' => MembershipStatus::Archived]);

        Sanctum::actingAs($worker);

        $this->getJson('/api/my/checklists')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/assignments/{$assignment->id}/start")->assertForbidden();
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
            'assignee_type' => GranteeType::User,
            'assignee_id' => $worker->id,
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
}
