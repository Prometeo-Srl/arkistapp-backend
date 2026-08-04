<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([OrgRoleSeeder::class, PlanSeeder::class]);
    }

    private function subscribe(Company $company, string $planCode = 'premium_monthly'): Subscription
    {
        return Subscription::create([
            'company_id' => $company->id,
            'plan_id' => Plan::where('code', $planCode)->firstOrFail()->id,
            'status' => SubscriptionStatus::Active,
            'started_at' => now(),
        ]);
    }

    public function test_unassociated_worker_is_entitled_by_a_personal_subscription(): void
    {
        $worker = User::factory()->create(['name' => 'Mario', 'surname' => 'Rossi']);

        $this->assertFalse($worker->hasEntitlingSubscription());

        $workspace = Company::personalFor($worker);

        $this->assertTrue($workspace->isPersonal());
        $this->assertTrue($worker->isAdminOf($workspace));
        $this->assertFalse($worker->hasEntitlingSubscription());

        $this->subscribe($workspace);

        $this->assertTrue($worker->hasEntitlingSubscription());
        $this->assertCount(0, $worker->businessCompanies()->get());
    }

    public function test_joining_a_subscribed_company_supersedes_the_personal_plan(): void
    {
        $worker = User::factory()->create();
        $personal = $this->subscribe(Company::personalFor($worker));

        $company = Company::create(['name' => 'Acme Srl']);
        $companySubscription = $this->subscribe($company, 'premium_yearly');

        CompanyMembership::create([
            'company_id' => $company->id,
            'user_id' => $worker->id,
            'status' => MembershipStatus::Active,
        ]);

        $personal->refresh();

        $this->assertSame(SubscriptionStatus::Superseded, $personal->status);
        $this->assertSame($companySubscription->id, $personal->superseded_by_id);
        $this->assertNotNull($personal->canceled_at);

        // Access stays covered, now by the company subscription.
        $this->assertTrue($worker->hasEntitlingSubscription());
        $this->assertTrue($company->hasEntitlingSubscription());
    }

    public function test_joining_a_company_without_subscription_leaves_the_personal_plan_alone(): void
    {
        $worker = User::factory()->create();
        $personal = $this->subscribe(Company::personalFor($worker));

        $company = Company::create(['name' => 'Senza Piano Srl']);
        CompanyMembership::create([
            'company_id' => $company->id,
            'user_id' => $worker->id,
            'status' => MembershipStatus::Active,
        ]);

        $this->assertSame(SubscriptionStatus::Active, $personal->refresh()->status);
    }

    public function test_a_pending_invitation_is_unique_per_company_and_email(): void
    {
        $admin = User::factory()->create();
        $company = Company::create(['name' => 'Acme Srl', 'owner_user_id' => $admin->id]);

        $invitation = Invitation::create([
            'company_id' => $company->id,
            'email' => 'nuovo@example.it',
            'org_role_id' => OrgRole::where('code', 'lavoratore')->firstOrFail()->id,
            'invited_by_id' => $admin->id,
        ]);

        $this->assertTrue($invitation->isPending());
        $this->assertNotEmpty($invitation->token);

        $this->expectException(QueryException::class);
        Invitation::create(['company_id' => $company->id, 'email' => 'nuovo@example.it']);
    }
}
