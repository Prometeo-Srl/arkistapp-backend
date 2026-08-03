<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\SupportThread;
use App\Models\User;

/**
 * Support threads are tenant-scoped for members; the operator (via before())
 * sees and answers threads of every company.
 */
class SupportThreadPolicy
{
    /** The super admin operates across every tenant. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isOperator() ? true : null;
    }

    public function viewAny(User $user, Company $company): bool
    {
        return $user->isMemberOf($company);
    }

    public function create(User $user, Company $company): bool
    {
        return $user->isMemberOf($company);
    }

    public function view(User $user, SupportThread $thread): bool
    {
        return $user->isMemberOf($thread->company);
    }

    public function message(User $user, SupportThread $thread): bool
    {
        return $user->isMemberOf($thread->company);
    }

    public function read(User $user, SupportThread $thread): bool
    {
        return $user->isMemberOf($thread->company);
    }

    public function close(User $user, SupportThread $thread): bool
    {
        return $user->isMemberOf($thread->company);
    }
}
