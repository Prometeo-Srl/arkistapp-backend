<?php

namespace App\Policies;

use App\Models\AccessGrant;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Support\EffectiveAccess;

class FilePolicy
{
    /** The super admin operates across every tenant. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isOperator() ? true : null;
    }

    /** Uploading: company admin anywhere, the owner of their own personal branch, or an editor. */
    public function create(User $user, Folder $folder): bool
    {
        if ($user->isAdminOf($folder->category->company)) {
            return true;
        }

        return EffectiveAccess::ownsPersonalBranch($user, $folder)
            || EffectiveAccess::forFolder($user, $folder)?->canWrite() === true;
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

    /**
     * Metadata, including the document type that drives the expiry date. A custodian
     * manages expiry without owning the file, which is the whole point of that level.
     */
    public function update(User $user, File $file): bool
    {
        return $this->writeAllowed($user, $file, fn ($permission) => $permission->canManageExpiry());
    }

    /** Replacing the blob with a new version is expiry management, not destruction. */
    public function replaceVersion(User $user, File $file): bool
    {
        return $this->update($user, $file);
    }

    /** Destroying content is the editor's privilege alone. */
    public function delete(User $user, File $file): bool
    {
        return $this->writeAllowed($user, $file, fn ($permission) => $permission->canWrite());
    }

    private function writeAllowed(User $user, File $file, callable $allows): bool
    {
        $folder = $file->folder;

        if ($user->isAdminOf($folder->category->company)) {
            return true;
        }

        // The worker owns what lives in their personal folder: confirmed with the client,
        // the worker prototype's upload screens win over the read-only specification.
        if (EffectiveAccess::ownsPersonalBranch($user, $folder)) {
            return true;
        }

        $permission = EffectiveAccess::forFile($user, $file);

        return $permission !== null && $allows($permission);
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
