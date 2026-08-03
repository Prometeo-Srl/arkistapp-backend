<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\Folder;
use App\Models\User;

class FolderPolicy
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

    public function view(User $user, Folder $folder): bool
    {
        return $user->isMemberOf($folder->category->company) && $this->visibleTo($user, $folder);
    }

    public function update(User $user, Folder $folder): bool
    {
        return $user->isAdminOf($folder->category->company);
    }

    public function delete(User $user, Folder $folder): bool
    {
        return $this->update($user, $folder);
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
}
