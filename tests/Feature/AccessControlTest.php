<?php

namespace Tests\Feature;

use App\Enums\AccessPermission;
use App\Enums\GranteeType;
use App\Enums\MembershipStatus;
use App\Models\AccessGrant;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Folder;
use App\Models\Invitation;
use App\Models\User;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\OrgRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The three sharing levels used to be stored and then ignored: permission was
 * validated on the way in, but every write only ever asked "is this an admin?".
 * These tests pin the levels to actual behaviour.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Company $company;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([OrgRoleSeeder::class, DocumentTypeSeeder::class]);

        $this->admin = User::factory()->create();
        $this->company = Company::factory()->create();
        CompanyMembership::factory()->admin()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
        ]);
        $this->category = Category::factory()->create(['company_id' => $this->company->id]);
    }

    private function member(): User
    {
        $user = User::factory()->create();
        CompanyMembership::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
        ]);

        return $user;
    }

    private function fileIn(Folder $folder): File
    {
        $file = File::factory()->create([
            'folder_id' => $folder->id,
            'name' => 'attestato.pdf',
        ]);
        $version = FileVersion::factory()->create(['file_id' => $file->id, 'version_no' => 1]);
        $file->update(['current_version_id' => $version->id]);

        return $file;
    }

    private function grant(User $user, string $type, int $id, AccessPermission $permission): void
    {
        AccessGrant::create([
            'grantable_type' => $type,
            'grantable_id' => $id,
            'grantee_type' => GranteeType::User,
            'grantee_id' => $user->id,
            'permission' => $permission,
            'granted_by_id' => $this->admin->id,
        ]);
    }

    public function test_a_viewer_grant_does_not_allow_writing(): void
    {
        $folder = Folder::factory()->create(['category_id' => $this->category->id]);
        $file = $this->fileIn($folder);
        $viewer = $this->member();
        $this->grant($viewer, 'file', $file->id, AccessPermission::Viewer);

        Sanctum::actingAs($viewer);

        $this->patchJson("/api/files/{$file->id}", ['name' => 'renamed.pdf'])->assertForbidden();
        $this->deleteJson("/api/files/{$file->id}")->assertForbidden();
    }

    public function test_a_custodian_manages_expiry_but_cannot_delete(): void
    {
        Storage::fake();
        $folder = Folder::factory()->create(['category_id' => $this->category->id]);
        $file = $this->fileIn($folder);
        $custodian = $this->member();
        $this->grant($custodian, 'file', $file->id, AccessPermission::Custodian);

        Sanctum::actingAs($custodian);

        $this->patchJson("/api/files/{$file->id}", ['issued_at' => '2026-01-15'])->assertOk();

        $this->post("/api/files/{$file->id}/versions", [
            'file' => UploadedFile::fake()->create('nuovo.pdf', 32, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->deleteJson("/api/files/{$file->id}")->assertForbidden();
    }

    public function test_an_editor_grant_on_the_category_cascades_to_its_files(): void
    {
        $folder = Folder::factory()->create(['category_id' => $this->category->id]);
        $file = $this->fileIn($folder);
        $editor = $this->member();

        // Granted on the category, exercised on a file two levels down.
        $this->grant($editor, 'category', $this->category->id, AccessPermission::Editor);

        Sanctum::actingAs($editor);

        $this->patchJson("/api/files/{$file->id}", ['name' => 'renamed.pdf'])->assertOk();
        $this->deleteJson("/api/files/{$file->id}")->assertNoContent();
    }

    public function test_a_worker_manages_the_documents_in_their_own_personal_folder(): void
    {
        Storage::fake();
        $worker = $this->member();
        $personal = Folder::factory()->create([
            'category_id' => $this->category->id,
            'is_personal_of_user_id' => $worker->id,
        ]);

        Sanctum::actingAs($worker);

        $fileId = $this->post('/api/files', [
            'folder_id' => $personal->id,
            'file' => UploadedFile::fake()->create('mio-attestato.pdf', 32, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        // Upload used to work while renaming and deleting did not.
        $this->patchJson("/api/files/{$fileId}", ['name' => 'attestato-2026.pdf'])->assertOk();
        $this->deleteJson("/api/files/{$fileId}")->assertNoContent();
    }

    public function test_a_worker_cannot_touch_someone_elses_personal_folder(): void
    {
        Storage::fake();
        $worker = $this->member();
        $colleague = $this->member();
        $theirFolder = Folder::factory()->create([
            'category_id' => $this->category->id,
            'is_personal_of_user_id' => $colleague->id,
        ]);
        $theirFile = $this->fileIn($theirFolder);

        Sanctum::actingAs($worker);

        $this->post('/api/files', [
            'folder_id' => $theirFolder->id,
            'file' => UploadedFile::fake()->create('intruso.pdf', 16, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->getJson("/api/files/{$theirFile->id}")->assertForbidden();
        $this->deleteJson("/api/files/{$theirFile->id}")->assertForbidden();
    }

    public function test_a_worker_creates_subfolders_only_inside_their_own_branch(): void
    {
        $worker = $this->member();
        $personal = Folder::factory()->create([
            'category_id' => $this->category->id,
            'is_personal_of_user_id' => $worker->id,
        ]);

        Sanctum::actingAs($worker);

        $this->postJson("/api/companies/{$this->company->id}/folders", [
            'category_id' => $this->category->id,
            'parent_folder_id' => $personal->id,
            'name' => 'Attestati 2026',
        ])->assertCreated();

        // At the root of a category the worker has no say.
        $this->postJson("/api/companies/{$this->company->id}/folders", [
            'category_id' => $this->category->id,
            'name' => 'Cartella abusiva',
        ])->assertForbidden();

        // Nor may they rename the personal folder the company created for them.
        $this->patchJson("/api/folders/{$personal->id}", ['name' => 'rinominata'])->assertForbidden();
    }

    public function test_re_inviting_an_archived_member_reactivates_the_membership(): void
    {
        $worker = User::factory()->create();
        $membership = CompanyMembership::factory()->archived()->create([
            'company_id' => $this->company->id,
            'user_id' => $worker->id,
        ]);

        $invitation = Invitation::factory()->create([
            'company_id' => $this->company->id,
            'email' => $worker->email,
            'is_admin' => true,
        ]);

        Sanctum::actingAs($worker);

        $this->postJson('/api/invitations/accept', ['token' => $invitation->token])
            ->assertCreated();

        $membership->refresh();
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $this->assertTrue($membership->is_admin);
        $this->assertSame(1, CompanyMembership::where('company_id', $this->company->id)
            ->where('user_id', $worker->id)->count());
    }
}
