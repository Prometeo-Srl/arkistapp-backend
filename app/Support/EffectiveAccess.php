<?php

namespace App\Support;

use App\Enums\AccessPermission;
use App\Models\AccessGrant;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves what a user may actually do with a file or folder.
 *
 * Grants cascade downwards, as the hierarchy in the prototypes implies: a grant on a
 * category covers its folders and their files, and a grant on a folder covers everything
 * nested under it. When several grants apply, the strongest one wins.
 */
final class EffectiveAccess
{
    public static function forFile(User $user, File $file): ?AccessPermission
    {
        $folder = $file->folder;

        return self::strongest(
            $user,
            $folder,
            [['file', $file->getKey()]]
        );
    }

    public static function forFolder(User $user, Folder $folder): ?AccessPermission
    {
        return self::strongest($user, $folder, []);
    }

    /**
     * True when the folder sits inside the user's own personal branch. Sub-folders
     * created under a personal folder do not repeat is_personal_of_user_id, so
     * ownership has to be read from the ancestors.
     */
    public static function ownsPersonalBranch(User $user, Folder $folder): bool
    {
        foreach (self::branch($folder) as $ancestor) {
            if ($ancestor->is_personal_of_user_id !== null) {
                return $ancestor->is_personal_of_user_id === $user->getKey();
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{0: string, 1: int}>  $extraTargets
     */
    private static function strongest(User $user, Folder $folder, array $extraTargets): ?AccessPermission
    {
        $targets = $extraTargets;

        foreach (self::branch($folder) as $ancestor) {
            $targets[] = ['folder', $ancestor->getKey()];
        }

        $targets[] = ['category', $folder->category_id];

        return AccessGrant::query()
            ->active()
            ->forUser($user, $folder->category->company_id)
            ->where(function (Builder $query) use ($targets) {
                foreach ($targets as [$type, $id]) {
                    $query->orWhere(fn (Builder $inner) => $inner
                        ->where('grantable_type', $type)
                        ->where('grantable_id', $id));
                }
            })
            ->get()
            ->pluck('permission')
            ->sortByDesc(fn (AccessPermission $permission) => $permission->rank())
            ->first();
    }

    /**
     * The folder and its ancestors, closest first. Nesting is shallow in practice
     * (category → folder → sub-folder), so walking it one query at a time is cheap.
     *
     * @return array<int, Folder>
     */
    private static function branch(Folder $folder): array
    {
        $branch = [];
        $current = $folder;

        while ($current !== null) {
            $branch[] = $current;
            $current = $current->parent_folder_id ? $current->parent : null;
        }

        return $branch;
    }
}
