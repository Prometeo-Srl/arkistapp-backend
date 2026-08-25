<?php

namespace Tests\Feature;

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
 * "Gestisci accesso" (prototype 161): the same grants as "Condividi", over the
 * other audience.
 *
 * The two screens must not show each other's rows — "condividi" addresses outside
 * guests by email, "gestisci accesso" ticks people off the org chart — and the line
 * between them is `company_memberships.is_guest`.
 */
class ManageAccessTest extends TestCase
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

    public function test_a_share_lets_an_outsider_in_as_a_guest_not_as_an_employee(): void
    {
        $outsider = User::factory()->create(['email' => 'consulente@example.com']);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/grants', [
            'grantable_type' => 'folder',
            'grantable_id' => $this->folder->id,
            'grantee_type' => 'user',
            'email' => 'consulente@example.com',
            'permission' => 'viewer',
        ])->assertCreated();

        $this->assertDatabaseHas('company_memberships', [
            'company_id' => $this->company->id,
            'user_id' => $outsider->id,
            'is_guest' => true,
        ]);
    }

    public function test_a_guest_never_appears_on_the_org_chart(): void
    {
        $employee = User::factory()->create();
        CompanyMembership::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
        ]);

        $guest = User::factory()->create();
        CompanyMembership::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $guest->id,
            'is_guest' => true,
        ]);

        Sanctum::actingAs($this->admin);

        $listed = $this->getJson("/api/companies/{$this->company->id}/members")
            ->assertOk()
            ->json('data.*.user_id');

        $this->assertContains($employee->id, $listed);
        $this->assertNotContains($guest->id, $listed);
    }

    /**
     * Sharing with somebody already on the org chart must not demote them: the guest
     * flag is set when the membership is created, never on an existing row.
     */
    public function test_sharing_with_an_employee_leaves_their_membership_alone(): void
    {
        $employee = User::factory()->create(['email' => 'dipendente@example.com']);
        CompanyMembership::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
        ]);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/grants', [
            'grantable_type' => 'folder',
            'grantable_id' => $this->folder->id,
            'grantee_type' => 'user',
            'email' => 'dipendente@example.com',
            'permission' => 'viewer',
        ])->assertCreated();

        $this->assertDatabaseHas('company_memberships', [
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'is_guest' => false,
        ]);
    }

    public function test_the_two_screens_read_disjoint_halves_of_the_same_grants(): void
    {
        $employee = User::factory()->create();
        CompanyMembership::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
        ]);

        $guest = User::factory()->create(['email' => 'studio@example.com']);
        CompanyMembership::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $guest->id,
            'is_guest' => true,
        ]);

        Sanctum::actingAs($this->admin);

        foreach ([$employee->id, $guest->id] as $granteeId) {
            $this->postJson('/api/grants', [
                'grantable_type' => 'folder',
                'grantable_id' => $this->folder->id,
                'grantee_type' => 'user',
                'grantee_id' => $granteeId,
                'permission' => 'viewer',
            ])->assertCreated();
        }

        // An address with no account yet: external by construction, so it belongs to
        // "condividi" even though there is no membership to read a flag from.
        $this->postJson('/api/grants', [
            'grantable_type' => 'folder',
            'grantable_id' => $this->folder->id,
            'grantee_type' => 'user',
            'email' => 'nessuno@example.com',
            'permission' => 'viewer',
        ])->assertCreated();

        $query = [
            'grantable_type' => 'folder',
            'grantable_id' => $this->folder->id,
        ];

        $this->getJson('/api/grants?'.http_build_query($query + ['audience' => 'org_chart']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.grantee_id', $employee->id);

        $guests = $this->getJson('/api/grants?'.http_build_query($query + ['audience' => 'guests']))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->json('data.*.email');

        $this->assertEqualsCanonicalizing(
            ['studio@example.com', 'nessuno@example.com'],
            $guests
        );

        // No audience: the endpoint still answers with every grant on the node.
        $this->getJson('/api/grants?'.http_build_query($query))
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }
}
