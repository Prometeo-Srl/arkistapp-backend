<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

/**
 * Branding is keyed to the company, so the abilities receive the company from
 * the route while auto-discovery binds this policy to BrandingSetting.
 */
class BrandingSettingPolicy
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
}
