<?php

namespace App\Policies;

use App\Models\AccessGrant;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;

class FilePolicy
{
    /** The super admin operates across every tenant. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isOperator() ? true : null;
    }

    /** Uploading: company admin anywhere, or the owner of their own personal folder. */
    public function create(User $user, Folder $folder): bool
    {
        $company = $folder->category->company;

        if ($user->isAdminOf($company)) {
            return true;
        }

        return $folder->is_personal_of_user_id !== null
            && $folder->is_personal_of_user_id === $user->getKey();
    }

    public function view(User $user, File $file): bool
    {
        $folder = $file->folder;

        return $user->isMemberOf($folder->category->company) && $this->folderVisibleTo($user, $folder);
    }

    /** Members always download; non-members need a direct, still-valid access grant. */
    public function download(User $user, File $file): bool
    {
        if ($this->view($user, $file)) {
            return true;
        }

        return AccessGrant::query()
            ->active()
            ->forUser($user, $file->folder->category->company_id)
            ->where('grantable_type', $file->getMorphClass())
            ->where('grantable_id', $file->getKey())
            ->exists();
    }

    public function acknowledge(User $user, File $file): bool
    {
        return $this->download($user, $file);
    }

    public function update(User $user, File $file): bool
    {
        return $user->isAdminOf($file->folder->category->company);
    }

    public function delete(User $user, File $file): bool
    {
        return $this->update($user, $file);
    }

    private function folderVisibleTo(User $user, Folder $folder): bool
    {
        if ($folder->is_personal_of_user_id === null) {
            return true;
        }

        return $folder->is_personal_of_user_id === $user->getKey()
            || $user->isAdminOf($folder->category->company);
    }
}
