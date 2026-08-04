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
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
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

    public function store(StoreSubscriptionRequest $request, Company $company)
    {
        $this->authorize('manageSubscription', $company);

        $plan = Plan::findOrFail($request->validated('plan_id'));

        $subscription = $company->subscriptions()->create([
            'plan_id' => $plan->getKey(),
            'status' => SubscriptionStatus::Active,
            'started_at' => now(),
            'current_period_end' => match ($plan->billing_period) {
                'yearly' => now()->addYear(),
                default => now()->addMonth(),
            },
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

        $company->subscriptions()
            ->whereIn('status', SubscriptionStatus::entitling())
            ->latest()
            ->first()
            ?->update(['status' => SubscriptionStatus::Canceled, 'canceled_at' => now()]);

        return response()->noContent();
    }
}
