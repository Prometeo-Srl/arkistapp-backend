<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\DocumentType;
use App\Models\Folder;
use App\Models\OrgRole;
use App\Models\User;
use Database\Factories\CategoryFactory;
use Database\Factories\FolderFactory;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\OrgRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

class DocumentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([OrgRoleSeeder::class, DocumentTypeSeeder::class]);
    }

    private function actingAsUser(?User $user = null): User
    {
        return tap($user ?? User::factory()->create(), fn (User $u) => Sanctum::actingAs($u));
    }

    /** @return array{0: User, 1: Company} */
    private function setUpCompany(): array
    {
        $admin = User::factory()->create();
        $company = Company::create(['name' => 'Acme Srl', 'owner_user_id' => $admin->id]);
        CompanyMembership::factory()->for($company)->for($admin)->admin()->create();

        return [$admin, $company];
    }

    private function attachMember(Company $company, ?User $user = null): User
    {
        $user ??= User::factory()->create();
        CompanyMembership::factory()->for($company)->for($user)->create();

        return $user;
    }

    /** @return array{0: Category, 1: Folder} */
    private function makeArchive(Company $company, array $folder = []): array
    {
        $category = CategoryFactory::new()->create(['company_id' => $company->id, 'created_by_id' => null]);
        $folder = FolderFactory::new()->create([
            'category_id' => $category->id,
            'created_by_id' => null,
            ...$folder,
        ]);

        return [$category, $folder];
    }

    /** @param  array<string, mixed>  $extra */
    private function uploadFile(int $folderId, array $extra = []): int
    {
        $response = $this->post('/api/files', [
            'folder_id' => $folderId,
            'file' => UploadedFile::fake()->create('cert.pdf', 100),
            ...$extra,
        ], ['Accept' => 'application/json']);

        $response->assertCreated();

        return $response->json('data.id');
    }

    /** The "informazioni file" card reads its whole payload off this endpoint. */
    public function test_file_show_carries_the_info_card_fields(): void
    {
        [$admin, $company] = $this->setUpCompany();
        $this->actingAsUser($admin);
        [, $folder] = $this->makeArchive($company, ['name' => 'locali']);

        $fileId = $this->uploadFile($folder->id, [
            'requires_acknowledgement' => 1,
            'requires_signature' => 1,
        ]);

        $this->getJson("/api/files/{$fileId}")
            ->assertOk()
            ->assertJsonPath('data.company_name', 'Acme Srl')
            ->assertJsonPath('data.folder.name', 'locali')
            ->assertJsonPath('data.requires_acknowledgement', true)
            ->assertJsonPath('data.requires_signature', true)
            ->assertJsonPath('data.expires_at', null);
    }

    public function test_document_types_catalog_lists_seeded_types(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/document-types')
            ->assertOk()
            ->assertJsonCount(12, 'data');

        $catalog = $this->getJson('/api/document-types')->json('data');
        $antincendio = collect($catalog)->firstWhere('code', 'attestato_antincendio');
        $this->assertSame(60, $antincendio['validity_months']);
    }

    public function test_admin_manages_categories_worker_can_only_read(): void
    {
        [$admin, $company] = $this->setUpCompany();
        $this->actingAsUser($admin);

        $this->postJson("/api/companies/{$company->id}/categories", ['name' => 'Attestati'])
            ->assertCreated()->assertJsonPath('data.name', 'Attestati');

        $category = CategoryFactory::new()->create(['company_id' => $company->id, 'created_by_id' => null]);

        $this->patchJson("/api/categories/{$category->id}", ['name' => 'Attestati 2026'])
            ->assertOk()->assertJsonPath('data.name', 'Attestati 2026');

        $worker = $this->attachMember($company);
        $this->actingAsUser($worker);

        $this->getJson("/api/companies/{$company->id}/categories")->assertOk();
        $this->postJson("/api/companies/{$company->id}/categories", ['name' => 'Nope'])
            ->assertForbidden();
    }

    public function test_folder_nested_inside_category(): void
    {
        [$admin, $company] = $this->setUpCompany();
        $this->actingAsUser($admin);
        $category = CategoryFactory::new()->create(['company_id' => $company->id, 'created_by_id' => null]);

        $parent = FolderFactory::new()->create(['category_id' => $category->id, 'created_by_id' => null]);
        $child = FolderFactory::new()->create([
            'category_id' => $category->id,
            'parent_folder_id' => $parent->id,
            'created_by_id' => null,
        ]);

        $this->postJson("/api/companies/{$company->id}/folders", [
            'category_id' => $category->id,
            'parent_folder_id' => $child->id,
            'name' => 'Annidati',
        ])->assertCreated()->assertJsonPath('data.parent_folder_id', $child->id);

        $this->getJson("/api/companies/{$company->id}/folders?category_id={$category->id}")
            ->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_file_upload_recalculates_expiry_and_expiring_filter(): void
    {
        Storage::fake('local');
        [$admin, $company] = $this->setUpCompany();
        $this->actingAsUser($admin);
        [, $folder] = $this->makeArchive($company);

        $antincendio = DocumentType::where('code', 'attestato_antincendio')->firstOrFail();
        $visita = DocumentType::where('code', 'visita_medica')->firstOrFail();

        $fileA = $this->uploadFile($folder->id, [
            'document_type_id' => $antincendio->id,
            'issued_at' => now()->subMonths(6)->toDateString(),
        ]);
        $fileB = $this->uploadFile($folder->id, [
            'document_type_id' => $visita->id,
            'issued_at' => now()->subMonths(6)->toDateString(),
        ]);

        // 60 months validity: issued six months ago -> expires in 54 months.
        $this->getJson("/api/files/{$fileA}")
            ->assertOk()
            ->assertJsonPath('data.expires_at', now()->subMonths(6)->addMonths(60)->toDateString());

        $this->getJson("/api/companies/{$company->id}/files")
            ->assertOk()->assertJsonCount(2, 'data');

        // The medical check (12 months) falls inside the 400-day window, the antincendio does not.
        $this->getJson("/api/companies/{$company->id}/files?expiring=400")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $fileB);
    }

    public function test_new_version_bumps_and_repoints_current_version(): void
    {
        Storage::fake('local');
        [$admin, $company] = $this->setUpCompany();
        $this->actingAsUser($admin);
        [, $folder] = $this->makeArchive($company);
        $fileId = $this->uploadFile($folder->id);

        $this->post("/api/files/{$fileId}/versions", [
            'file' => UploadedFile::fake()->create('cert-v2.pdf', 200),
            'replaced_reason' => 'Rinnovo',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.current_version.version_no', 2)
            ->assertJsonPath('data.current_version.replaced_reason', 'Rinnovo')
            ->assertJsonPath('data.size_bytes', 204800);

        $this->assertDatabaseCount('file_versions', 2);
    }

    /**
     * Downloads are served under the file's name, so an extension left over from the
     * replaced document hands the user a JPEG that every reader reads as a broken PDF.
     */
    public function test_new_version_of_another_kind_retitles_and_reclassifies_the_file(): void
    {
        Storage::fake('local');
        [$admin, $company] = $this->setUpCompany();
        $this->actingAsUser($admin);
        [, $folder] = $this->makeArchive($company);
        // Uploaded as cert.pdf, media_kind document.
        $fileId = $this->uploadFile($folder->id);

        $this->post("/api/files/{$fileId}/versions", [
            'file' => UploadedFile::fake()->image('scan.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            // The title stays the document's, the extension follows the new payload.
            ->assertJsonPath('data.name', 'cert.jpg')
            ->assertJsonPath('data.media_kind', 'image')
            ->assertJsonPath('data.mime_type', 'image/jpeg');

        $this->get("/api/files/{$fileId}/download")
            ->assertOk()
            ->assertDownload('cert.jpg');
    }

    public function test_acknowledgement_is_unique_per_version(): void
    {
        Storage::fake('local');
        [$admin, $company] = $this->setUpCompany();
        $this->actingAsUser($admin);
        [, $folder] = $this->makeArchive($company);
        $fileId = $this->uploadFile($folder->id, ['requires_acknowledgement' => true]);

        $worker = $this->attachMember($company);

        // Only a worker the document was shared with is asked to confirm it.
        AccessGrant::create([
            'grantable_type' => 'file',
            'grantable_id' => $fileId,
            'grantee_type' => 'user',
            'grantee_id' => $worker->id,
            'permission' => 'viewer',
            'granted_by_id' => $admin->id,
        ]);

        $this->actingAsUser($worker);

        $this->postJson("/api/files/{$fileId}/acknowledge")->assertCreated();
        $this->postJson("/api/files/{$fileId}/acknowledge")->assertOk();
        $this->assertDatabaseCount('acknowledgements', 1);

        $this->getJson("/api/files/{$fileId}/acknowledgements")
            ->assertOk()
            ->assertJsonPath('data.0.user_id', $worker->id);

        // A new version demands a new confirmation: the receipt is bound to the version.
        $this->actingAsUser($admin);
        $this->post("/api/files/{$fileId}/versions", [
            'file' => UploadedFile::fake()->create('v2.pdf', 100),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->actingAsUser($worker);
        $this->postJson("/api/files/{$fileId}/acknowledge")->assertCreated();
        $this->assertDatabaseCount('acknowledgements', 2);
    }

    public function test_non_member_download_requires_an_active_grant(): void
    {
        Storage::fake('local');
        [$admin, $company] = $this->setUpCompany();
        $this->actingAsUser($admin);
        [, $folder] = $this->makeArchive($company);
        $fileId = $this->uploadFile($folder->id);

        $stranger = $this->actingAsUser();
        $this->get("/api/files/{$fileId}/download")->assertForbidden();

        $this->actingAsUser($admin);
        $this->postJson('/api/grants', [
            'grantable_type' => 'file',
            'grantable_id' => $fileId,
            'grantee_type' => 'user',
            'grantee_id' => $stranger->id,
            'permission' => 'viewer',
        ])->assertCreated();

        $this->actingAsUser($stranger);
        $this->get("/api/files/{$fileId}/download")->assertOk();

        // Morph aliases are stored, never FQCNs.
        $this->assertDatabaseHas('access_grants', [
            'grantable_type' => 'file',
            'grantee_type' => 'user',
            'grantee_id' => $stranger->id,
        ]);
    }

    public function test_org_role_grant_opens_a_personal_folder_file(): void
    {
        Storage::fake('local');
        [$admin, $company] = $this->setUpCompany();
        $this->actingAsUser($admin);

        $other = User::factory()->create();
        [, $personalFolder] = $this->makeArchive($company, ['is_personal_of_user_id' => $other->id]);
        $fileId = $this->uploadFile($personalFolder->id);

        $preposto = OrgRole::where('code', 'preposto')->firstOrFail();
        $worker = $this->attachMember($company);
        $membership = CompanyMembership::where('company_id', $company->id)->where('user_id', $worker->id)->firstOrFail();
        $membership->orgRoles()->attach($preposto->id, ['appointed_at' => now()->toDateString()]);

        // A colleague's personal folder stays locked without a grant.
        $this->actingAsUser($worker);
        $this->get("/api/files/{$fileId}/download")->assertForbidden();

        $this->actingAsUser($admin);
        $this->postJson('/api/grants', [
            'grantable_type' => 'file',
            'grantable_id' => $fileId,
            'grantee_type' => 'org_role',
            'grantee_id' => $preposto->id,
            'permission' => 'viewer',
        ])->assertCreated();

        $grant = AccessGrant::where('grantable_id', $fileId)->firstOrFail();
        $this->actingAsUser($worker);
        $this->get("/api/files/{$fileId}/download")->assertOk();

        // Revoking the grant removes the access again.
        $this->actingAsUser($admin);
        $this->deleteJson("/api/grants/{$grant->id}")->assertNoContent();

        $this->actingAsUser($worker);
        $this->get("/api/files/{$fileId}/download")->assertForbidden();
    }

    public function test_personal_folder_visible_only_to_owner_and_admins(): void
    {
        [$admin, $company] = $this->setUpCompany();
        $this->actingAsUser($admin);

        $worker = $this->attachMember($company);
        $peer = $this->attachMember($company);
        [, $personalFolder] = $this->makeArchive($company, ['is_personal_of_user_id' => $worker->id]);

        // Counts are relative: a business workspace ships with the default archive.
        $this->actingAsUser($worker);
        $this->assertContains($personalFolder->id, $this->visibleFolderIds($company));

        $this->actingAsUser($peer);
        $this->assertNotContains($personalFolder->id, $this->visibleFolderIds($company));

        $this->actingAsUser($admin);
        $this->assertContains($personalFolder->id, $this->visibleFolderIds($company));
    }

    public function test_company_folders_stay_private_until_shared(): void
    {
        Storage::fake('local');
        [$admin, $company] = $this->setUpCompany();
        $this->actingAsUser($admin);

        [$category, $folder] = $this->makeArchive($company);
        $child = FolderFactory::new()->create([
            'category_id' => $category->id,
            'parent_folder_id' => $folder->id,
            'created_by_id' => null,
        ]);
        $fileId = $this->uploadFile($child->id);

        // The datore di lavoro's own filing structure: nothing shared yet.
        $worker = $this->attachMember($company);
        $this->actingAsUser($worker);
        // Their own personal branch is all they get: none of the company tree.
        $own = Folder::where('is_personal_of_user_id', $worker->id)->pluck('id')->all();
        $this->assertEqualsCanonicalizing($own, $this->visibleFolderIds($company));
        $this->getJson("/api/companies/{$company->id}/files")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/files/{$fileId}")->assertForbidden();

        // Sharing the parent folder cascades to the sub-folder and its files.
        $this->actingAsUser($admin);
        $this->postJson('/api/grants', [
            'grantable_type' => 'folder',
            'grantable_id' => $folder->id,
            'grantee_type' => 'user',
            'grantee_id' => $worker->id,
            'permission' => 'viewer',
        ])->assertCreated();

        $this->actingAsUser($worker);
        $this->assertEqualsCanonicalizing([$folder->id, $child->id, ...$own], $this->visibleFolderIds($company));
        $this->getJson("/api/companies/{$company->id}/files")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/files/{$fileId}")->assertOk();
        $this->get("/api/files/{$fileId}/download")->assertOk();
    }

    /** @return array<int, int> */

    /**
     * "cronologia" (prototype 234). The trail is written by observers, so the test
     * drives the real endpoints rather than seeding audit rows: a factory-built log
     * would pass while nothing in the app ever recorded anything — which is exactly
     * the state this table was in.
     */
    public function test_file_history_records_upload_rename_and_share(): void
    {
        [$admin, $company] = $this->setUpCompany();
        $this->actingAsUser($admin);
        [, $folder] = $this->makeArchive($company);

        $fileId = $this->uploadFile($folder->id);

        $this->patchJson("/api/files/{$fileId}", ['name' => 'nuovo nome.pdf'])->assertOk();

        $this->postJson('/api/grants', [
            'grantable_type' => 'file',
            'grantable_id' => $fileId,
            'grantee_type' => 'user',
            'email' => $this->attachMember($company)->email,
            'permission' => 'viewer',
        ])->assertSuccessful();

        $response = $this->getJson("/api/files/{$fileId}/history")->assertOk();

        // Newest first, and the upload's own `current_version_id` write must not be
        // mistaken for a replacement.
        $this->assertSame(
            ['file.shared', 'file.renamed', 'file.uploaded'],
            array_column($response->json('data'), 'action')
        );
        $this->assertSame($admin->id, $response->json('data.0.user.id'));
        $this->assertSame($admin->name, $response->json('data.0.user.name'));
    }

    /**
     * "gestisci configurazione" (prototype 235–236): the expiry is a field of the
     * form, so a hand-set date has to survive the model's own recalculation — and
     * clearing the toggle has to leave it empty.
     */
    public function test_file_expiry_can_be_set_and_cleared_by_hand(): void
    {
        [$admin, $company] = $this->setUpCompany();
        $this->actingAsUser($admin);
        [, $folder] = $this->makeArchive($company);

        $fileId = $this->uploadFile($folder->id);

        $this->patchJson("/api/files/{$fileId}", [
            'expires_at' => '2027-01-01',
            'requires_signature' => true,
        ])->assertOk()
            ->assertJsonPath('data.expires_at', '2027-01-01')
            ->assertJsonPath('data.requires_signature', true);

        $this->patchJson("/api/files/{$fileId}", ['expires_at' => null])
            ->assertOk()
            ->assertJsonPath('data.expires_at', null);
    }

    /** A replacement is a version landing on a file that already had one. */
    public function test_file_history_records_a_replaced_version(): void
    {
        [$admin, $company] = $this->setUpCompany();
        $this->actingAsUser($admin);
        [, $folder] = $this->makeArchive($company);

        $fileId = $this->uploadFile($folder->id);

        $this->post("/api/files/{$fileId}/versions", [
            'file' => UploadedFile::fake()->create('cert-v2.pdf', 120),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(
            ['file.replaced', 'file.uploaded'],
            array_column($this->getJson("/api/files/{$fileId}/history")->json('data'), 'action')
        );
    }

    /** The trail of a document is as visible as the document, and no more. */
    public function test_file_history_is_denied_to_an_outsider(): void
    {
        [$admin, $company] = $this->setUpCompany();
        $this->actingAsUser($admin);
        [, $folder] = $this->makeArchive($company);
        $fileId = $this->uploadFile($folder->id);

        $this->actingAsUser();
        $this->getJson("/api/files/{$fileId}/history")->assertForbidden();
    }

    private function visibleFolderIds(Company $company): array
    {
        return $this->getJson("/api/companies/{$company->id}/folders")
            ->assertOk()
            ->json('data.*.id');
    }

    public function test_worker_uploads_only_into_own_personal_folder(): void
    {
        Storage::fake('local');
        [$admin, $company] = $this->setUpCompany();

        $worker = $this->attachMember($company);
        [, $personalFolder] = $this->makeArchive($company, ['is_personal_of_user_id' => $worker->id]);
        [, $sharedFolder] = $this->makeArchive($company);

        $this->actingAsUser($worker);
        $this->uploadFile($personalFolder->id);

        $this->post('/api/files', [
            'folder_id' => $sharedFolder->id,
            'file' => UploadedFile::fake()->create('nope.pdf', 100),
        ], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_branding_update_or_create(): void
    {
        [$admin, $company] = $this->setUpCompany();
        $this->actingAsUser($admin);

        $this->patchJson("/api/companies/{$company->id}/branding", [
            'primary_hex' => '#0F4C81',
            'font_family' => 'Inter',
        ])->assertOk()->assertJsonPath('data.primary_hex', '#0F4C81');

        $this->patchJson("/api/companies/{$company->id}/branding", [
            'accent_hex' => '#F9A825',
        ])->assertOk();

        $this->assertDatabaseCount('branding_settings', 1);

        $worker = $this->attachMember($company);
        $this->actingAsUser($worker);
        $this->getJson("/api/companies/{$company->id}/branding")->assertOk();
        $this->patchJson("/api/companies/{$company->id}/branding", ['font_family' => 'Roboto'])
            ->assertForbidden();
    }

    public function test_deleting_a_folder_cascades_to_the_whole_subtree(): void
    {
        [$admin, $company] = $this->setUpCompany();
        [$category, $parent] = $this->makeArchive($company);
        $child = FolderFactory::new()->create([
            'category_id' => $category->id,
            'parent_folder_id' => $parent->id,
            'created_by_id' => null,
        ]);

        $this->actingAsUser($admin);
        $parentFileId = $this->uploadFile($parent->id);
        $childFileId = $this->uploadFile($child->id);

        // A member the branch was shared with must lose it, not keep a dangling grant.
        $member = $this->attachMember($company);
        AccessGrant::factory()->create([
            'grantable_type' => 'folder',
            'grantable_id' => $parent->id,
            'grantee_type' => 'user',
            'grantee_id' => $member->id,
            'permission' => 'viewer',
            'granted_by_id' => $admin->id,
        ]);

        $this->deleteJson("/api/folders/{$parent->id}")->assertNoContent();

        $this->assertSoftDeleted('folders', ['id' => $parent->id]);
        $this->assertSoftDeleted('folders', ['id' => $child->id]);
        $this->assertSoftDeleted('files', ['id' => $parentFileId]);
        $this->assertSoftDeleted('files', ['id' => $childFileId]);
        $this->assertDatabaseMissing('access_grants', [
            'grantable_type' => 'folder',
            'grantable_id' => $parent->id,
        ]);

        // The member keeps their own personal folders; the shared branch is gone.
        $this->actingAsUser($member);
        $visible = $this->getJson("/api/companies/{$company->id}/folders")->assertOk()->json('data.*.id');
        $this->assertNotContains($parent->id, $visible);
        $this->assertNotContains($child->id, $visible);
        $this->getJson("/api/files/{$childFileId}")->assertNotFound();
    }

    public function test_folder_download_zips_the_whole_subtree(): void
    {
        Storage::fake('local');
        [$admin, $company] = $this->setUpCompany();
        $this->actingAsUser($admin);

        [$category, $parent] = $this->makeArchive($company, ['name' => 'Contratti 2025']);
        $child = FolderFactory::new()->create([
            'category_id' => $category->id,
            'parent_folder_id' => $parent->id,
            'created_by_id' => null,
            'name' => 'Allegati',
        ]);
        $this->uploadFile($parent->id);
        $this->uploadFile($child->id);

        $response = $this->get("/api/folders/{$parent->id}/download")->assertOk();
        $response->assertDownload('contratti-2025.zip');

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);
        $this->assertNotFalse($zip->locateName('Contratti 2025/cert.pdf'));
        $this->assertNotFalse($zip->locateName('Contratti 2025/Allegati/cert.pdf'));
        $zip->close();
    }

    public function test_folder_download_is_denied_without_access(): void
    {
        Storage::fake('local');
        [$admin, $company] = $this->setUpCompany();
        $this->actingAsUser($admin);
        [, $folder] = $this->makeArchive($company);
        $this->uploadFile($folder->id);

        $this->actingAsUser($this->attachMember($company));
        $this->get("/api/folders/{$folder->id}/download")->assertForbidden();
    }
}
