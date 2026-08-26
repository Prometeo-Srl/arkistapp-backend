<?php

namespace App\Support;

use App\Enums\AccessPermission;
use App\Enums\FileVisibility;
use App\Models\AccessGrant;
use App\Models\Company;
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
    /**
     * The document's own visibility (prototype 204) decides whether the folder around
     * it still counts: "esteso" inherits the branch, "personalizzato" honours only the
     * grants written on the file, "privato" honours none at all. The owner and the
     * company admin are not resolved here — they never go through a grant.
     */
    public static function forFile(User $user, File $file): ?AccessPermission
    {
        $folder = $file->folder;
        $onTheFile = [['file', $file->getKey()]];

        return match ($file->visibility) {
            FileVisibility::Private => null,
            FileVisibility::Custom => self::pick($user, $folder->category->company_id, $onTheFile),
            FileVisibility::Inherited => self::strongest($user, $folder, $onTheFile),
        };
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
     * The company folders a non-admin may see: their own personal branch, plus whatever
     * a grant on a category/folder reaches, plus everything nested under those. A folder
     * nobody shared with them does not exist for them — the default archive belongs to
     * the datore di lavoro, not to every employee.
     *
     * ponytail: resolved in PHP over the company's folder rows instead of a recursive
     * CTE. Trees run a few hundred rows; move it into SQL if this listing ever shows up
     * in the timings.
     *
     * @return array<int, int>
     */
    public static function visibleFolderIds(User $user, Company $company): array
    {
        $folders = Folder::query()
            ->whereRelation('category', 'company_id', $company->getKey())
            ->get(['id', 'parent_folder_id', 'category_id', 'is_personal_of_user_id']);

        $grants = AccessGrant::query()->active()->forUser($user, $company->getKey())->get();
        $categoryIds = $grants->where('grantable_type', 'category')->pluck('grantable_id')->all();
        $folderIds = $grants->where('grantable_type', 'folder')->pluck('grantable_id')->all();

        $visible = $folders
            ->filter(fn (Folder $folder) => $folder->is_personal_of_user_id === $user->getKey()
                || in_array($folder->category_id, $categoryIds)
                || in_array($folder->getKey(), $folderIds))
            ->pluck('id')
            ->all();

        // Grants cascade downwards: pull in descendants until nothing new appears.
        do {
            $before = count($visible);

            foreach ($folders as $folder) {
                if ($folder->parent_folder_id
                    && in_array($folder->parent_folder_id, $visible)
                    && ! in_array($folder->getKey(), $visible)) {
                    $visible[] = $folder->getKey();
                }
            }
        } while (count($visible) > $before);

        return $visible;
    }

    /**
     * Files shared one by one, whose folder itself stays invisible.
     *
     * @return array<int, int>
     */
    public static function grantedFileIds(User $user, Company $company): array
    {
        return AccessGrant::query()
            ->active()
            ->forUser($user, $company->getKey())
            ->where('grantable_type', 'file')
            ->pluck('grantable_id')
            ->all();
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

        return self::pick($user, $folder->category->company_id, $targets);
    }

    /**
     * The strongest active grant this user holds over any of [$targets].
     *
     * @param  array<int, array{0: string, 1: int}>  $targets
     */
    private static function pick(User $user, int $companyId, array $targets): ?AccessPermission
    {
        return AccessGrant::query()
            ->active()
            ->forUser($user, $companyId)
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
