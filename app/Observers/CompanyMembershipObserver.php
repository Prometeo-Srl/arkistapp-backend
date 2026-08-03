<?php

namespace App\Observers;

use App\Enums\MembershipStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WorkspaceKind;
use App\Models\CompanyMembership;
use App\Models\Subscription;

class CompanyMembershipObserver
{
    /**
     * A worker joining a company with an active subscription must no longer pay for
     * an individual plan: the subscription of their personal workspace gets absorbed.
     */
    public function saved(CompanyMembership $membership): void
    {
        if ($membership->status !== MembershipStatus::Active) {
            return;
        }

        static::supersedePersonalPlan($membership);
    }

    /** Reused by the subscribe flow, which covers members that joined before the company subscribed. */
    public static function supersedePersonalPlan(CompanyMembership $membership): void
    {
        $company = $membership->company;

        if (! $company || $company->isPersonal()) {
            return;
        }

        $companySubscription = $company->entitlingSubscription;

        if (! $companySubscription) {
            return;
        }

        Subscription::query()
            ->whereIn('status', SubscriptionStatus::entitling())
            ->whereHas('company', fn ($query) => $query
                ->where('kind', WorkspaceKind::Personal)
                ->where('owner_user_id', $membership->user_id))
            ->each(fn (Subscription $personal) => $personal->supersedeWith($companySubscription));
    }
}
