<?php

use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gives every personal branch a root folder of its own.
 *
 * The app used to browse a worker's branch as a synthetic level — "the folders
 * where is_personal_of_user_id = X" — which is not a row, so a document had
 * nothing to be filed into there. The six seeded folders move one level down,
 * under a folder named after the worker, and that level becomes an ordinary
 * folder: files and sub-folders both live in it.
 *
 * Nothing in authorization changes: EffectiveAccess::ownsPersonalBranch() already
 * walks ancestors, and visibleFolderIds() already cascades to descendants.
 */
return new class extends Migration
{
    public function up(): void
    {
        $branches = Folder::query()
            ->whereNotNull('is_personal_of_user_id')
            ->whereNull('parent_folder_id')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Folder $folder) => $folder->category_id.':'.$folder->is_personal_of_user_id);

        foreach ($branches as $folders) {
            /** @var Collection<int, Folder> $folders */
            $first = $folders->first();
            $userId = $first->is_personal_of_user_id;

            // Already migrated: a branch whose only root is the root itself.
            if ($folders->count() === 1 && $first->children()->exists()) {
                continue;
            }

            DB::transaction(function () use ($folders, $first, $userId) {
                $root = Folder::create([
                    'category_id' => $first->category_id,
                    'parent_folder_id' => null,
                    'name' => self::branchName($userId),
                    'position' => 0,
                    'is_personal_of_user_id' => $userId,
                    'created_by_id' => $first->created_by_id,
                ]);

                Folder::query()
                    ->whereIn('id', $folders->pluck('id'))
                    ->update(['parent_folder_id' => $root->getKey()]);
            });
        }
    }

    /** The worker's own name, which is what the branch is called everywhere else. */
    private static function branchName(?int $userId): string
    {
        $user = $userId ? User::query()->find($userId) : null;

        return trim(($user?->name ?? '').' '.($user?->surname ?? '')) ?: 'documenti personali';
    }

    public function down(): void
    {
        $roots = Folder::query()
            ->whereNotNull('is_personal_of_user_id')
            ->whereNull('parent_folder_id')
            ->get();

        foreach ($roots as $root) {
            DB::transaction(function () use ($root) {
                $children = DB::table('folders')
                    ->where('parent_folder_id', $root->getKey())
                    ->whereNull('deleted_at')
                    ->get(['id', 'name']);

                // The root only ever held folders; anything filed straight into it
                // has no home once it is gone, so it moves into "documenti vari".
                $fallback = $children->firstWhere('name', 'documenti vari')
                    ?? $children->first();

                if ($fallback) {
                    DB::table('files')
                        ->where('folder_id', $root->getKey())
                        ->update(['folder_id' => $fallback->id]);
                }

                DB::table('folders')
                    ->where('parent_folder_id', $root->getKey())
                    ->update(['parent_folder_id' => null]);

                // Deleted through the query builder on purpose: the model cascades
                // to `$folder->children`, and that relation — loaded or reloaded —
                // would take the branch down with the root it no longer owns.
                DB::table('access_grants')
                    ->where('grantable_type', 'folder')
                    ->where('grantable_id', $root->getKey())
                    ->delete();

                DB::table('folders')->where('id', $root->getKey())->delete();
            });
        }
    }
};
