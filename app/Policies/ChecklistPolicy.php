<?php

namespace App\Policies;

use App\Enums\AssignmentStatus;
use App\Models\Checklist;
use App\Models\User;

/**
 * Two audiences, two abilities. `manage` is the DDL authoring side: list, create,
 * read, edit the structure, share, duplicate, delete. `view` is everyone who may
 * see a questionnaire at all, which adds the people it was shared with - they
 * need it to answer, and to download the PDF.
 *
 * Carrying an assignment out (start, submit) concerns one assignment rather than
 * the checklist, so those checks stay in the execution controller.
 *
 * ponytail: authoring is gated on the workspace admin check, so a datore di
 * lavoro who holds the DDL org role without being the admin cannot author. See
 * the spec (docs/specs/0001) - widening it is a change to this method alone,
 * driven by User::activeOrgRoleIds(), deliberately deferred rather than guessed.
 */
class ChecklistPolicy
{
    /** The super admin operates across every tenant. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isOperator() ? true : null;
    }

    public function manage(User $user, Checklist $checklist): bool
    {
        return $user->isAdminOf($checklist->company);
    }

    /** An author, or somebody the checklist was shared with and not withdrawn from. */
    public function view(User $user, Checklist $checklist): bool
    {
        if ($this->manage($user, $checklist)) {
            return true;
        }

        // An archived membership ends every derived permission, assignments included.
        if (! $user->activeCompanyIds()->contains($checklist->company_id)) {
            return false;
        }

        return $checklist->assignments()
            ->where('assignee_user_id', $user->getKey())
            ->where('status', '!=', AssignmentStatus::Cancelled)
            ->exists();
    }
}
