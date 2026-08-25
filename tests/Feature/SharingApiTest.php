<?php

namespace Tests\Feature;

use App\Enums\AccessPermission;
use App\Enums\GranteeType;
use App\Enums\MembershipStatus;
use App\Models\AccessGrant;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Folder;
use App\Models\User;
use Database\Seeders\OrgRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "Condividi" (prototypes 169-175): sharing a node with a person by email.
 *
 * Nothing is asked of the recipient — no token, no accepting. Sharing lets them
 * into the workspace on the spot, and an address with no account yet gets its
 * documents the moment one is created.
 */
class SharingApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Company $company;

    private Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([OrgRoleSeeder::class]);

        $this->admin = User::factory()->create();
        $this->company = Company::factory()->create(['owner_user_id' => $this->admin->id]);
        CompanyMembership::factory()->admin()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
        ]);
        $category = Category::factory()->create(['company_id' => $this->company->id]);
        $this->folder = Folder::factory()->create(['category_id' => $category->id]);
    }

    public function test_sharing_with_an_existing_user_resolves_the_email_to_the_account(): void
    {
        $member = User::factory()->create(['email' => 'Federica@example.com']);
        CompanyMembership::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $member->id,
        ]);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/grants', [
            'grantable_type' => 'folder',
            'grantable_id' => $this->folder->id,
            'grantee_type' => 'user',
            // Case is not part of an address: the same person, typed differently.
            'email' => 'federica@example.com',
            'permission' => 'viewer',
        ])->assertCreated()->assertJsonPath('data.grantee_id', $member->id);
    }

    public function test_sharing_lets_an_outside_account_into_the_workspace_at_once(): void
    {
        $outsider = User::factory()->create(['email' => 'esterno@example.com']);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/grants', [
            'grantable_type' => 'folder',
            'grantable_id' => $this->folder->id,
            'grantee_type' => 'user',
            'email' => 'esterno@example.com',
            'permission' => 'custodian',
        ])->assertCreated();

        $this->assertDatabaseHas('company_memberships', [
            'company_id' => $this->company->id,
            'user_id' => $outsider->id,
            'status' => MembershipStatus::Active->value,
            'is_admin' => false,
        ]);

        // No hoop to jump through: the folder is readable on the next request.
        Sanctum::actingAs($outsider);
        $this->getJson("/api/companies/{$this->company->id}/folders")
            ->assertOk()
            ->assertJsonFragment(['id' => $this->folder->id]);
    }

    public function test_sharing_with_an_unknown_email_waits_for_the_account(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/grants', [
            'grantable_type' => 'folder',
            'grantable_id' => $this->folder->id,
            'grantee_type' => 'user',
            'email' => 'nuovo@example.com',
            'permission' => 'custodian',
        ])->assertCreated()->assertJsonPath('data.email', 'nuovo@example.com');

        $this->assertDatabaseHas('access_grants', [
            'grantee_id' => null,
            'invited_email' => 'nuovo@example.com',
        ]);
    }

    public function test_creating_the_account_claims_the_share_and_its_membership(): void
    {
        $grant = AccessGrant::create([
            'grantable_type' => 'folder',
            'grantable_id' => $this->folder->id,
            'grantee_type' => GranteeType::User,
            'invited_email' => 'Nuovo@example.com',
            'permission' => AccessPermission::Viewer,
            'granted_by_id' => $this->admin->id,
        ]);

        $newcomer = User::factory()->create(['email' => 'nuovo@example.com']);

        $this->assertSame($newcomer->id, $grant->fresh()->grantee_id);
        $this->assertNull($grant->fresh()->invited_email);
        $this->assertDatabaseHas('company_memberships', [
            'company_id' => $this->company->id,
            'user_id' => $newcomer->id,
            'status' => MembershipStatus::Active->value,
        ]);
    }

    public function test_resharing_the_same_email_updates_the_row_instead_of_duplicating_it(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'grantable_type' => 'folder',
            'grantable_id' => $this->folder->id,
            'grantee_type' => 'user',
            'email' => 'nuovo@example.com',
            'permission' => 'viewer',
        ];

        $this->postJson('/api/grants', $payload)->assertCreated();
        $this->postJson('/api/grants', [...$payload, 'permission' => 'editor'])->assertOk();

        $this->assertSame(1, AccessGrant::query()->count());
        $this->assertSame(AccessPermission::Editor, AccessGrant::query()->first()->permission);
    }

    public function test_a_temporary_share_carries_its_expiry(): void
    {
        $member = User::factory()->create(['email' => 'federica@example.com']);

        Sanctum::actingAs($this->admin);

        $expiry = now()->addDay();

        $this->postJson('/api/grants', [
            'grantable_type' => 'folder',
            'grantable_id' => $this->folder->id,
            'grantee_type' => 'user',
            'email' => $member->email,
            'permission' => 'viewer',
            'expires_at' => $expiry->toIso8601String(),
        ])->assertCreated();

        $this->assertNotNull(AccessGrant::query()->first()->expires_at);

        // Switching "accesso limitato" back off is an omitted expiry, not a null one.
        $this->postJson('/api/grants', [
            'grantable_type' => 'folder',
            'grantable_id' => $this->folder->id,
            'grantee_type' => 'user',
            'email' => $member->email,
            'permission' => 'viewer',
        ])->assertOk();

        $this->assertNull(AccessGrant::query()->first()->expires_at);
    }

    public function test_the_list_names_the_owner_and_every_grant(): void
    {
        $member = User::factory()->create(['email' => 'simone@example.com']);
        AccessGrant::create([
            'grantable_type' => 'folder',
            'grantable_id' => $this->folder->id,
            'grantee_type' => GranteeType::User,
            'grantee_id' => $member->id,
            'permission' => AccessPermission::Editor,
            'granted_by_id' => $this->admin->id,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/grants?grantable_type=folder&grantable_id='.$this->folder->id)
            ->assertOk();

        $response->assertJsonPath('owner.name', $this->company->name);
        $response->assertJsonPath('owner.email', $this->admin->email);
        $response->assertJsonPath('data.0.email', 'simone@example.com');
        $response->assertJsonPath('data.0.permission', 'editor');
    }

    public function test_a_member_without_write_access_cannot_read_the_sharing_list(): void
    {
        $outsider = User::factory()->create();
        CompanyMembership::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $outsider->id,
        ]);

        Sanctum::actingAs($outsider);

        $this->getJson('/api/grants?grantable_type=folder&grantable_id='.$this->folder->id)
            ->assertForbidden();
    }

    public function test_revoking_a_grant_removes_it(): void
    {
        $grant = AccessGrant::create([
            'grantable_type' => 'folder',
            'grantable_id' => $this->folder->id,
            'grantee_type' => GranteeType::User,
            'grantee_id' => User::factory()->create()->id,
            'permission' => AccessPermission::Viewer,
            'granted_by_id' => $this->admin->id,
        ]);

        Sanctum::actingAs($this->admin);

        $this->deleteJson("/api/grants/{$grant->id}")->assertNoContent();
        $this->assertDatabaseMissing('access_grants', ['id' => $grant->id]);
    }
}
