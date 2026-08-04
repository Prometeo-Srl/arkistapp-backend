<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\Company;
use App\Models\User;

/**
 * Archive management: creating categories is an admin action, reading them is
 * open to every active member of the company.
 */
class CategoryPolicy
{
    /** The super admin operates across every tenant. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isOperator() ? true : null;
    }

    public function create(User $user, Company $company): bool
    {
        return $user->isAdminOf($company);
    }

    public function view(User $user, Category $category): bool
    {
        return $user->isMemberOf($category->company);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->isAdminOf($category->company);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->update($user, $category);
    }
}
