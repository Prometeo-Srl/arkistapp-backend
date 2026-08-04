<?php

namespace App\Policies;

use App\Models\Checklist;
use App\Models\User;

/**
 * Checklist templates are company assets: admins build them (create, edit,
 * publish, structure, assign). Carrying an assignment out (start, submit)
 * concerns the assignee, so those checks live in the execution controller
 * because assignments and submissions have no policy of their own.
 */
class ChecklistPolicy
{
    /** The super admin operates across every tenant. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isOperator() ? true : null;
    }

    /** Building and managing the template: list, create, read, update, delete, publish, structure, assign. */
    public function manage(User $user, Checklist $checklist): bool
    {
        return $user->isAdminOf($checklist->company);
    }
}
