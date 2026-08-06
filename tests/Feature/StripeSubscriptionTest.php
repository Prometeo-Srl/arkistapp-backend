<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\StripeGateway;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Tests\TestCase;

/** "attiva il tuo abbonamento" → "completa pagamento" → Stripe keeps us in sync. */
class StripeSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    /** @return array{0: User, 1: Company} */
    private function adminOfCompany(): array
    {
        $admin = User::factory()->create();
        $company = Company::create(['name' => 'Acme Srl', 'owner_user_id' => $admin->id]);
        CompanyMembership::factory()->for($company)->for($admin)->admin()->create();

        Sanctum::actingAs($admin);

        return [$admin, $company];
    }

    private function plan(string $code = 'premium_monthly'): Plan
    {
        return Plan::where('code', $code)->firstOrFail();
    }

    public function test_plans_carry_the_prices_and_trials_from_the_design(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/plans')
            ->assertOk()
            ->assertJsonPath('data.1.price_cents', 1000)
            ->assertJsonPath('data.1.trial_days', 7)
            ->assertJsonPath('data.2.price_cents', 10000)
            ->assertJsonPath('data.2.trial_days', 30);
    }

    public function test_admin_gets_a_setup_intent_client_secret(): void
    {
        [, $company] = $this->adminOfCompany();
        $this->fakeStripe();

        $this->postJson("/api/companies/{$company->id}/subscription/setup-intent")
            ->assertOk()
            ->assertJsonPath('client_secret', 'seti_test_secret');
    }

    public function test_non_admin_member_cannot_start_a_payment(): void
    {
        [, $company] = $this->adminOfCompany();
        $worker = User::factory()->create();
        CompanyMembership::factory()->for($company)->for($worker)->create();

        Sanctum::actingAs($worker);
        $this->fakeStripe();

        $this->postJson("/api/companies/{$company->id}/subscription/setup-intent")->assertForbidden();
    }

    public function test_subscribing_stores_the_stripe_subscription_and_starts_the_trial(): void
    {
        [, $company] = $this->adminOfCompany();
        $trialEnd = now()->addDays(7)->timestamp;

        $stripe = $this->fakeStripe(periodEnd: $trialEnd);

        $this->postJson("/api/companies/{$company->id}/subscription", [
            'plan_id' => $this->plan()->id,
            'payment_method_id' => 'pm_card_visa',
        ])->assertCreated()->assertJsonPath('data.status', 'trialing');

        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'status' => 'trialing',
            'provider_ref' => 'sub_test',
        ]);

        $this->assertSame(
            $trialEnd,
            Subscription::firstOrFail()->current_period_end->timestamp,
        );

        // The trial length comes from the plan, not from the client.
        $stripe->shouldHaveReceived('createSubscription')
            ->withArgs(fn (...$args) => $args[2] === 'pm_card_visa' && $args[3] === 7);
    }

    public function test_subscribing_without_a_payment_method_is_rejected(): void
    {
        [, $company] = $this->adminOfCompany();
        $this->fakeStripe();

        $this->postJson("/api/companies/{$company->id}/subscription", [
            'plan_id' => $this->plan()->id,
        ])->assertJsonValidationErrorFor('payment_method_id');

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_canceling_also_cancels_at_stripe(): void
    {
        [, $company] = $this->adminOfCompany();
        $stripe = $this->fakeStripe();

        $this->postJson("/api/companies/{$company->id}/subscription", [
            'plan_id' => $this->plan()->id,
            'payment_method_id' => 'pm_card_visa',
        ])->assertCreated();

        $this->deleteJson("/api/companies/{$company->id}/subscription")->assertNoContent();

        $stripe->shouldHaveReceived('cancelSubscription')->with('sub_test');
        $this->assertSame(SubscriptionStatus::Canceled, Subscription::firstOrFail()->status);
    }

    private function fakeWebhook(string $type, array $object): MockInterface
    {
        return $this->mock(StripeGateway::class, function (MockInterface $stripe) use ($type, $object) {
            $stripe->shouldReceive('parseWebhook')->andReturn(
                Event::constructFrom(['type' => $type, 'data' => ['object' => $object]])
            );
        });
    }

    private function subscriptionFor(Company $company): Subscription
    {
        return Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $this->plan()->id,
            'status' => SubscriptionStatus::Trialing,
            'started_at' => now(),
            'provider_ref' => 'sub_test',
        ]);
    }

    public function test_paid_invoice_webhook_records_the_payment_once(): void
    {
        $company = Company::create(['name' => 'Acme Srl']);
        $subscription = $this->subscriptionFor($company);

        $this->fakeWebhook('invoice.paid', [
            'id' => 'in_test',
            'subscription' => 'sub_test',
            'amount_paid' => 1000,
            'currency' => 'eur',
        ]);

        // Stripe retries: the same invoice must not double-charge our ledger.
        $this->postJson('/api/stripe/webhook')->assertNoContent();
        $this->postJson('/api/stripe/webhook')->assertNoContent();

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'provider_ref' => 'in_test',
            'amount_cents' => 1000,
            'currency' => 'EUR',
            'status' => 'paid',
        ]);
    }

    public function test_failed_invoice_webhook_marks_the_subscription_past_due(): void
    {
        $company = Company::create(['name' => 'Acme Srl']);
        $this->subscriptionFor($company);

        $this->fakeWebhook('invoice.payment_failed', [
            'id' => 'in_failed',
            'subscription' => 'sub_test',
            'amount_due' => 1000,
            'currency' => 'eur',
        ]);

        $this->postJson('/api/stripe/webhook')->assertNoContent();

        $this->assertSame(SubscriptionStatus::PastDue, Subscription::firstOrFail()->status);
        $this->assertDatabaseHas('payments', ['provider_ref' => 'in_failed', 'status' => 'failed', 'paid_at' => null]);
    }

    public function test_subscription_updated_webhook_mirrors_status_and_period(): void
    {
        $company = Company::create(['name' => 'Acme Srl']);
        $this->subscriptionFor($company);
        $periodEnd = now()->addMonth()->timestamp;

        $this->fakeWebhook('customer.subscription.updated', [
            'id' => 'sub_test',
            'status' => 'active',
            'trial_end' => null,
            'items' => ['data' => [['current_period_end' => $periodEnd]]],
        ]);

        $this->postJson('/api/stripe/webhook')->assertNoContent();

        $subscription = Subscription::firstOrFail();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame($periodEnd, $subscription->current_period_end->timestamp);
    }

    public function test_a_status_we_do_not_model_stops_entitling(): void
    {
        $company = Company::create(['name' => 'Acme Srl']);
        $this->subscriptionFor($company);

        $this->fakeWebhook('customer.subscription.updated', ['id' => 'sub_test', 'status' => 'unpaid']);

        $this->postJson('/api/stripe/webhook')->assertNoContent();

        $this->assertSame(SubscriptionStatus::PastDue, Subscription::firstOrFail()->status);
    }

    public function test_an_unsigned_webhook_is_refused(): void
    {
        $this->mock(StripeGateway::class, function (MockInterface $stripe) {
            $stripe->shouldReceive('parseWebhook')->andThrow(new SignatureVerificationException('bad signature'));
        });

        $this->postJson('/api/stripe/webhook', ['type' => 'invoice.paid'])->assertStatus(400);
    }
}
