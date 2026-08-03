<?php

namespace Tests\Feature;

use App\Enums\ActivityKind;
use App\Enums\ActivityStatus;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Factories\ActivityFactory;
use Database\Factories\AuditLogFactory;
use Database\Factories\PaymentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MonitorApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(?User $user = null): User
    {
        return tap($user ?? User::factory()->create(), fn (User $u) => Sanctum::actingAs($u));
    }

    /** @return array{0: User, 1: Company} */
    private function makeCompanyWithAdmin(): array
    {
        $admin = User::factory()->create();
        $company = Company::create(['name' => 'Acme Srl', 'owner_user_id' => $admin->id]);
        CompanyMembership::factory()->for($company)->for($admin)->admin()->create();

        return [$admin, $company];
    }

    private function attachMember(User $user, Company $company): void
    {
        CompanyMembership::factory()->for($company)->for($user)->create();
    }

    public function test_board_lists_company_activities_filtered(): void
    {
        [$admin, $company] = $this->makeCompanyWithAdmin();
        $worker = $this->actingAsUser();
        $this->attachMember($worker, $company);

        ActivityFactory::new()->create([
            'company_id' => $company->id,
            'assignee_user_id' => $worker->id,
            'kind' => ActivityKind::ReadDocument,
            'status' => ActivityStatus::Todo,
        ]);
        ActivityFactory::new()->done()->create([
            'company_id' => $company->id,
            'assignee_user_id' => $admin->id,
            'kind' => ActivityKind::FillChecklist,
        ]);

        $this->getJson("/api/companies/{$company->id}/activities")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/companies/{$company->id}/activities?status=todo")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'todo');

        $this->getJson("/api/companies/{$company->id}/activities?kind=fill_checklist")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kind', 'fill_checklist');

        $this->getJson("/api/companies/{$company->id}/activities?assignee_user_id={$worker->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.assignee_user_id', $worker->id);
    }

    public function test_assignee_completes_own_activity_sets_completed_at(): void
    {
        [$admin, $company] = $this->makeCompanyWithAdmin();
        $worker = $this->actingAsUser();
        $this->attachMember($worker, $company);

        $activity = ActivityFactory::new()->create([
            'company_id' => $company->id,
            'assignee_user_id' => $worker->id,
            'status' => ActivityStatus::Todo,
        ]);

        $this->patchJson("/api/activities/{$activity->id}", ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.completed_at', $activity->fresh()->completed_at?->toISOString());

        $this->assertNotNull($activity->fresh()->completed_at);
        $this->assertSame('done', $activity->fresh()->status->value);
    }

    public function test_admin_can_reassign_activity(): void
    {
        [$admin, $company] = $this->makeCompanyWithAdmin();
        $workerA = User::factory()->create();
        $workerB = User::factory()->create();
        $this->attachMember($workerA, $company);
        $this->attachMember($workerB, $company);
        $this->actingAsUser($admin);

        $activity = ActivityFactory::new()->create([
            'company_id' => $company->id,
            'assignee_user_id' => $workerA->id,
        ]);

        $this->patchJson("/api/activities/{$activity->id}", [
            'assignee_user_id' => $workerB->id,
            'due_at' => '2030-01-01T00:00:00+00:00',
        ])->assertOk()->assertJsonPath('data.assignee_user_id', $workerB->id);

        $this->assertSame($workerB->id, $activity->fresh()->assignee_user_id);
    }

    public function test_worker_cannot_reassign_or_reschedule(): void
    {
        [$admin, $company] = $this->makeCompanyWithAdmin();
        $worker = $this->actingAsUser();
        $this->attachMember($worker, $company);
        $other = User::factory()->create();

        $activity = ActivityFactory::new()->create([
            'company_id' => $company->id,
            'assignee_user_id' => $worker->id,
            'due_at' => '2026-09-01T00:00:00+00:00',
        ]);

        $this->patchJson("/api/activities/{$activity->id}", [
            'status' => 'done',
            'assignee_user_id' => $other->id,
            'due_at' => '2030-01-01T00:00:00+00:00',
        ])->assertOk()->assertJsonPath('data.status', 'done');

        $fresh = $activity->fresh();
        $this->assertSame($worker->id, $fresh->assignee_user_id);
        $this->assertSame('2026-09-01 00:00:00', $fresh->due_at->format('Y-m-d H:i:s'));
    }

    public function test_non_member_cannot_view_board(): void
    {
        [$admin, $company] = $this->makeCompanyWithAdmin();
        $outsider = $this->actingAsUser();

        ActivityFactory::new()->create(['company_id' => $company->id]);

        $this->getJson("/api/companies/{$company->id}/activities")->assertForbidden();
        $this->assertFalse($outsider->isMemberOf($company));
    }

    public function test_audit_log_is_admin_and_operator_only(): void
    {
        [$admin, $company] = $this->makeCompanyWithAdmin();
        $worker = $this->actingAsUser();
        $this->attachMember($worker, $company);

        AuditLogFactory::new()->count(2)->create(['company_id' => $company->id]);

        $this->getJson("/api/companies/{$company->id}/audit-log")->assertForbidden();

        $this->actingAsUser($admin);
        $this->getJson("/api/companies/{$company->id}/audit-log")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $operator = $this->actingAsUser(User::factory()->operator()->create());
        $this->getJson("/api/companies/{$company->id}/audit-log?action=created")
            ->assertOk()
            ->assertJsonPath('data.0.action', 'created');
        $this->assertTrue($operator->isOperator());
    }

    public function test_payments_visible_to_members_only(): void
    {
        [$admin, $company] = $this->makeCompanyWithAdmin();
        $subscription = Subscription::factory()->create([
            'company_id' => $company->id,
            'plan_id' => Plan::factory()->create()->id,
        ]);
        PaymentFactory::new()->count(2)->create(['subscription_id' => $subscription->id]);

        // Member sees the payments, latest first.
        $worker = $this->actingAsUser();
        $this->attachMember($worker, $company);
        $this->getJson("/api/subscriptions/{$subscription->id}/payments")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // Outsider is rejected.
        $this->actingAsUser();
        $this->getJson("/api/subscriptions/{$subscription->id}/payments")->assertForbidden();
    }

    public function test_operator_can_update_any_activity(): void
    {
        $company = Company::create(['name' => 'Acme Srl']);
        $activity = ActivityFactory::new()->create([
            'company_id' => $company->id,
            'status' => ActivityStatus::Overdue,
        ]);

        $this->actingAsUser(User::factory()->operator()->create());

        $this->patchJson("/api/activities/{$activity->id}", ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('data.status', 'done');

        $this->assertSame(ActivityStatus::Done, $activity->fresh()->status);
    }
}
