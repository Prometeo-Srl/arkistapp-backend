<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Invitation;
use App\Models\OrgRole;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\OrgRoleSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenancyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([OrgRoleSeeder::class, PlanSeeder::class]);
    }

    private function actingAsUser(?User $user = null): User
    {
        return tap($user ?? User::factory()->create(), fn (User $u) => Sanctum::actingAs($u));
    }

    public function test_the_workspace_list_carries_the_membership_and_its_appointments(): void
    {
        $employer = $this->actingAsUser();
        $company = Company::factory()->create();
        $membership = CompanyMembership::factory()->for($company)->for($employer)->admin()->create();
        $membership->orgRoles()->attach(OrgRole::where('code', 'datore_lavoro')->firstOrFail());

        // The pivot needs its own key selected for orgRoles() to resolve at all;
        // without it the client sees an unprivileged membership and hides the
        // flows the appointment unlocks.
        $this->getJson('/api/companies')
            ->assertOk()
            ->assertJsonPath('data.0.membership.is_admin', true)
            ->assertJsonPath('data.0.membership.roles', ['datore_lavoro']);
    }

    public function test_worker_lists_workspaces_and_gets_a_personal_one(): void
    {
        $worker = $this->actingAsUser();

        // Listing is a pure read: it no longer creates the personal workspace.
        $this->getJson('/api/companies')->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseMissing('companies', ['owner_user_id' => $worker->id]);

        $this->postJson('/api/companies/personal')->assertCreated();
        // Idempotent: asking twice returns the same workspace.
        $this->postJson('/api/companies/personal')->assertOk();

        $this->assertDatabaseHas('companies', [
            'kind' => 'personal',
            'owner_user_id' => $worker->id,
        ]);
        $this->assertSame(1, Company::where('owner_user_id', $worker->id)->count());
        $this->assertTrue($worker->isAdminOf($worker->personalWorkspace));
    }

    public function test_worker_creates_a_business_company_and_becomes_its_admin(): void
    {
        $worker = $this->actingAsUser();

        $this->postJson('/api/companies', [
            'name' => 'Acme Srl',
            'vat_number' => '12345678901',
            'employees_count' => 12,
        ])->assertCreated()->assertJsonPath('data.name', 'Acme Srl');

        $company = Company::where('name', 'Acme Srl')->firstOrFail();

        $this->assertSame('business', $company->kind->value);
        $this->assertSame($worker->id, $company->owner_user_id);
        $this->assertTrue($worker->isAdminOf($company));
    }

    public function test_members_are_visible_to_workers_but_managed_only_by_admins(): void
    {
        $admin = $this->actingAsUser();
        $company = Company::create(['name' => 'Acme Srl', 'owner_user_id' => $admin->id]);
        CompanyMembership::factory()->for($company)->for($admin)->admin()->create();
        CompanyMembership::factory()->for($company)->for(User::factory()->create())->create();

        $this->getJson("/api/companies/{$company->id}/members")->assertOk();

        $worker = $this->actingAsUser();
        CompanyMembership::factory()->for($company)->for($worker)->create();

        $this->getJson("/api/companies/{$company->id}/members")->assertOk();
        $this->postJson("/api/companies/{$company->id}/members", [
            'user_id' => User::factory()->create()->id,
        ])->assertForbidden();
    }

    public function test_admin_attaches_an_existing_user_with_org_roles(): void
    {
        $admin = $this->actingAsUser();
        $company = Company::create(['name' => 'Acme Srl', 'owner_user_id' => $admin->id]);
        CompanyMembership::factory()->for($company)->for($admin)->admin()->create();

        $member = User::factory()->create();
        $preposto = OrgRole::where('code', 'preposto')->firstOrFail();

        $this->postJson("/api/companies/{$company->id}/members", [
            'user_id' => $member->id,
            'is_admin' => false,
            'department' => 'Produzione',
            'org_role_ids' => [$preposto->id],
        ])->assertCreated()->assertJsonPath('data.user.email', $member->email);

        $membership = CompanyMembership::where('company_id', $company->id)->where('user_id', $member->id)->firstOrFail();
        $this->assertTrue($membership->orgRoles->contains($preposto));
    }

    public function test_duplicate_member_attachment_is_rejected(): void
    {
        $admin = $this->actingAsUser();
        $company = Company::create(['name' => 'Acme Srl', 'owner_user_id' => $admin->id]);
        CompanyMembership::factory()->for($company)->for($admin)->admin()->create();
        $member = User::factory()->create();
        CompanyMembership::factory()->for($company)->for($member)->create();

        $this->postJson("/api/companies/{$company->id}/members", ['user_id' => $member->id])
            ->assertUnprocessable();
    }

    public function test_invitation_accepts_only_with_matching_email(): void
    {
        $admin = $this->actingAsUser();
        $company = Company::create(['name' => 'Acme Srl', 'owner_user_id' => $admin->id]);
        CompanyMembership::factory()->for($company)->for($admin)->admin()->create();

        $invitation = Invitation::create([
            'company_id' => $company->id,
            'email' => 'nuovo@example.it',
            'org_role_id' => OrgRole::where('code', 'lavoratore')->firstOrFail()->id,
            'invited_by_id' => $admin->id,
        ]);

        // Wrong account redeeming the token.
        $this->actingAsUser();
        $this->postJson('/api/invitations/accept', ['token' => $invitation->token])
            ->assertUnprocessable();

        $this->assertTrue($invitation->fresh()->isPending());

        // The invited email redeems it: membership created, invitation consumed.
        $invitee = $this->actingAsUser(User::factory()->create(['email' => 'nuovo@example.it']));
        $this->postJson('/api/invitations/accept', ['token' => $invitation->token])
            ->assertCreated();

        $this->assertTrue($invitee->isMemberOf($company));
        $this->assertFalse($invitation->fresh()->isPending());
        $this->assertSame($invitee->id, $invitation->fresh()->accepted_user_id);
    }

    public function test_company_subscription_supersedes_members_personal_plans(): void
    {
        $worker = User::factory()->create();
        $personal = Company::personalFor($worker);
        $personalSubscription = Subscription::create([
            'company_id' => $personal->id,
            'plan_id' => Plan::where('code', 'premium_monthly')->firstOrFail()->id,
            'status' => SubscriptionStatus::Active,
            'started_at' => now(),
        ]);

        $admin = $this->actingAsUser();
        $company = Company::create(['name' => 'Acme Srl', 'owner_user_id' => $admin->id]);
        CompanyMembership::factory()->for($company)->for($admin)->admin()->create();
        CompanyMembership::factory()->for($company)->for($worker)->create();

        $this->fakeStripe();

        $this->postJson("/api/companies/{$company->id}/subscription", [
            'plan_id' => Plan::where('code', 'premium_yearly')->firstOrFail()->id,
            'payment_method_id' => 'pm_card_visa',
        ])->assertCreated()->assertJsonPath('data.status', 'trialing');

        $this->assertSame(SubscriptionStatus::Superseded, $personalSubscription->fresh()->status);

        $this->getJson("/api/companies/{$company->id}/subscription")->assertOk();
    }

    public function test_catalogs_list_plans_and_org_roles(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/plans')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/org-roles')->assertOk()->assertJsonCount(9, 'data');
    }

    public function test_worker_without_admin_cannot_manage_invitations(): void
    {
        $admin = User::factory()->create();
        $company = Company::create(['name' => 'Acme Srl', 'owner_user_id' => $admin->id]);
        CompanyMembership::factory()->for($company)->for($admin)->admin()->create();

        $worker = $this->actingAsUser();
        CompanyMembership::factory()->for($company)->for($worker)->create();

        $this->postJson("/api/companies/{$company->id}/invitations", ['email' => 'x@example.it'])
            ->assertForbidden();
    }

    public function test_operator_can_manage_any_company(): void
    {
        $operator = $this->actingAsUser(User::factory()->operator()->create());

        $this->postJson('/api/companies', ['name' => 'Acme Srl'])
            ->assertCreated()->assertJsonPath('data.kind', 'business');

        $company = Company::where('name', 'Acme Srl')->firstOrFail();
        $this->assertSame($operator->id, $company->created_by_operator_id);
        $this->assertNull($company->owner_user_id);

        $this->getJson("/api/companies/{$company->id}/members")->assertOk();
    }
}
