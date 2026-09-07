<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

/**
 * Every ability in the onboarding/tenancy slice is really an ability over the company:
 * memberships, invitations and subscriptions all inherit their authorization from it.
 */
class CompanyPolicy
{
    /** The super admin operates across every tenant. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isOperator() ? true : null;
    }

    public function view(User $user, Company $company): bool
    {
        return $user->isMemberOf($company);
    }

    public function update(User $user, Company $company): bool
    {
        return $user->isAdminOf($company);
    }

    /**
     * Reading the org chart is open to every member — the prototype shows it to
     * workers too — unless the employer has taken it away from their role in
     * "gestisci autorizzazioni" (086).
     *
     * This is the single gate for the whole organigramma: the directory
     * (`/members`), the chart itself and the authorizations screen all authorize
     * through it.
     */
    public function viewMembers(User $user, Company $company): bool
    {
        return $user->isMemberOf($company) && $user->canViewOrgChartIn($company);
    }

    public function manageMembers(User $user, Company $company): bool
    {
        return $user->isAdminOf($company);
    }

    public function manageInvitations(User $user, Company $company): bool
    {
        return $user->isAdminOf($company);
    }

    public function viewSubscription(User $user, Company $company): bool
    {
        return $user->isMemberOf($company);
    }

    public function manageSubscription(User $user, Company $company): bool
    {
        return $user->isAdminOf($company);
    }
}
