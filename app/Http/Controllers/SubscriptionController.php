<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Plan;
use App\Observers\CompanyMembershipObserver;
use App\Support\StripeGateway;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private readonly StripeGateway $stripe) {}

    /** Latest subscription of the company, any status; null when never subscribed. */
    public function show(Request $request, Company $company)
    {
        $this->authorize('viewSubscription', $company);

        $subscription = $company->subscriptions()->with('plan')->latest()->first();

        // A company that never subscribed is the normal initial state, not an error.
        if (! $subscription) {
            return response()->json(['data' => null]);
        }

        return new SubscriptionResource($subscription);
    }

    /**
     * Client secret the app confirms the card against ("completa pagamento").
     * A SetupIntent, not a PaymentIntent: the card is stored now and first
     * charged when the free trial ends.
     */
    public function setupIntent(Request $request, Company $company)
    {
        $this->authorize('manageSubscription', $company);

        return response()->json(
            $this->stripe->createSetupIntent($this->stripe->customerFor($company))
        );
    }

    public function store(StoreSubscriptionRequest $request, Company $company)
    {
        $this->authorize('manageSubscription', $company);

        $plan = Plan::findOrFail($request->validated('plan_id'));

        $stripeSubscription = $this->stripe->createSubscription(
            $this->stripe->customerFor($company),
            $this->stripe->priceFor($plan),
            $request->validated('payment_method_id'),
            $plan->trial_days,
        );

        $subscription = $company->subscriptions()->create([
            'plan_id' => $plan->getKey(),
            // Stripe decides: `trialing` while the free trial runs, `active` when
            // there is none. Anything unexpected is not treated as entitling.
            'status' => SubscriptionStatus::tryFrom($stripeSubscription['status']) ?? SubscriptionStatus::PastDue,
            'started_at' => now(),
            'current_period_end' => $stripeSubscription['period_end']
                ? now()->setTimestamp($stripeSubscription['period_end'])
                : null,
            'provider_ref' => $stripeSubscription['id'],
        ]);

        // Members whose personal plan is still active are now covered by the company.
        $company->memberships()
            ->where('status', MembershipStatus::Active)
            ->get()
            ->each(fn (CompanyMembership $membership) => CompanyMembershipObserver::supersedePersonalPlan($membership));

        return (new SubscriptionResource($subscription->load('plan')))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, Company $company)
    {
        $this->authorize('manageSubscription', $company);

        $subscription = $company->subscriptions()
            ->whereIn('status', SubscriptionStatus::entitling())
            ->latest()
            ->first();

        if ($subscription?->provider_ref) {
            $this->stripe->cancelSubscription($subscription->provider_ref);
        }

        $subscription?->update(['status' => SubscriptionStatus::Canceled, 'canceled_at' => now()]);

        return response()->noContent();
    }
}
