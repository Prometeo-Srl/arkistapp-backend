<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\Folder;
use App\Models\User;
use App\Support\EffectiveAccess;

class FolderPolicy
{
    /** The super admin operates across every tenant. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isOperator() ? true : null;
    }

    /**
     * Creating at the root of a category stays an admin action. Inside an existing
     * folder it also works for the owner of a personal branch and for an editor,
     * which is what the worker prototype's "Crea nuova cartella" needs.
     */
    public function create(User $user, Company $company, ?Folder $parent = null): bool
    {
        if ($user->isAdminOf($company)) {
            return true;
        }

        if ($parent === null) {
            return false;
        }

        return EffectiveAccess::ownsPersonalBranch($user, $parent)
            || EffectiveAccess::forFolder($user, $parent)?->canWrite() === true;
    }

    public function view(User $user, Folder $folder): bool
    {
        return $user->isMemberOf($folder->category->company) && $this->visibleTo($user, $folder);
    }

    public function update(User $user, Folder $folder): bool
    {
        return $this->writeAllowed($user, $folder);
    }

    public function delete(User $user, Folder $folder): bool
    {
        return $this->writeAllowed($user, $folder);
    }

    /** Personal folders are visible only to their owner and to company admins. */
    public function visibleTo(User $user, Folder $folder): bool
    {
        if ($folder->is_personal_of_user_id === null) {
            return true;
        }

        return $folder->is_personal_of_user_id === $user->getKey()
            || $user->isAdminOf($folder->category->company);
    }

    private function writeAllowed(User $user, Folder $folder): bool
    {
        if ($user->isAdminOf($folder->category->company)) {
            return true;
        }

        // The root of a personal folder is part of the company's structure: the worker
        // works inside it, but does not rename or delete the folder itself.
        if ($folder->is_personal_of_user_id !== null) {
            return false;
        }

        return EffectiveAccess::ownsPersonalBranch($user, $folder)
            || EffectiveAccess::forFolder($user, $folder)?->canWrite() === true;
    }
}
