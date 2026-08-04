<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

/**
 * Per-activity abilities in the monitor slice. The board itself is a plain
 * company check in the controller; here the company is resolved through the
 * activity's relations.
 */
class ActivityPolicy
{
    /** The super admin operates across every tenant. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isOperator() ? true : null;
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->isMemberOf($activity->company);
    }

    /** The assignee completes their own work; admins may reassign or reschedule. */
    public function update(User $user, Activity $activity): bool
    {
        return $user->isAdminOf($activity->company)
            || $activity->assignee_user_id === $user->getKey();
    }
}
