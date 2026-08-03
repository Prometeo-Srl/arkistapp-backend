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
     * Un lavoratore associato a un'azienda con abbonamento attivo non deve più pagare
     * il piano individuale: l'abbonamento del suo workspace personale viene assorbito.
     */
    public function saved(CompanyMembership $membership): void
    {
        if ($membership->status !== MembershipStatus::Active) {
            return;
        }

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
