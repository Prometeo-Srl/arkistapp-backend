<?php

namespace App\Observers;

use App\Models\Category;
use App\Models\Company;
use App\Models\Folder;
use Illuminate\Support\Facades\DB;

class CompanyObserver
{
    /**
     * A new business workspace starts with the standard archive from
     * config/document_tree.php, so an operator never builds it by hand.
     * Personal workspaces get nothing: their tree is the worker's own folders.
     */
    public function created(Company $company): void
    {
        if ($company->isPersonal()) {
            return;
        }

        static::seedDefaultArchive($company);
    }

    /**
     * Idempotent, so it also backfills a workspace created before this existed.
     *
     * ponytail: ~350 inserts, one row at a time, inside the request. Fine at
     * signup rate; push it to a queued job if company creation ever gets hot.
     */
    public static function seedDefaultArchive(Company $company): void
    {
        $author = $company->owner_user_id ?? $company->created_by_operator_id;

        DB::transaction(function () use ($company, $author) {
            $position = 0;

            // Sections are always keyed, never a bare leaf.
            foreach (config('document_tree', []) as $name => $children) {
                $category = Category::firstOrCreate(
                    ['company_id' => $company->getKey(), 'name' => $name],
                    ['icon' => 'folder', 'position' => $position, 'created_by_id' => $author],
                );

                static::folders($category, $children, null, $author);

                $position++;
            }
        });
    }

    /** @param  array<int|string, mixed>  $nodes  string leaf, or name => children */
    private static function folders(Category $category, array $nodes, ?int $parentId, ?int $author): void
    {
        $position = 0;

        foreach ($nodes as $key => $value) {
            $name = is_int($key) ? $value : $key;
            $children = is_int($key) ? [] : $value;

            $folder = Folder::firstOrCreate(
                [
                    'category_id' => $category->getKey(),
                    'parent_folder_id' => $parentId,
                    'name' => $name,
                    'is_personal_of_user_id' => null,
                ],
                ['position' => $position, 'created_by_id' => $author],
            );

            if ($children) {
                static::folders($category, $children, $folder->getKey(), $author);
            }

            $position++;
        }
    }
}
