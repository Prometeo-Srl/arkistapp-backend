<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * The six folders prototype 258 shows behind a worker's tile, for every member
 * of every workspace, plus one readable PDF in the first of them so 260 and
 * "scarica" have something.
 *
 * Demo data, not part of DatabaseSeeder. Run it on purpose:
 *   sail artisan db:seed --class=PersonalFoldersSeeder
 *
 * ponytail: this stands in for a product gap — nothing creates a worker's
 * personal branch when they are appointed. That belongs in the membership
 * write path, not in a seeder.
 */
class PersonalFoldersSeeder extends Seeder
{
    private const NAMES = [
        'curriculum vitae', 'contratti', 'buste paga',
        'attestati di formazione', 'cartella clinica', 'documenti vari',
    ];

    public function run(?Company $only = null): void
    {
        $companies = $only ? collect([$only]) : Company::all();

        foreach ($companies as $company) {
            $author = $company->owner_user_id;

            $category = Category::firstOrCreate(
                ['company_id' => $company->getKey(), 'name' => 'documenti personali'],
                ['icon' => 'folder', 'position' => 0, 'created_by_id' => $author],
            );

            $members = $company->memberships()->with('user')->get();

            foreach ($members as $membership) {
                if ($membership->user) {
                    $this->personalFolders($category, $membership->user, $author);
                }
            }

            $this->command?->info(
                "{$company->name}: ".($members->count() * count(self::NAMES)).' folders'
            );
        }
    }

    private function personalFolders(Category $category, User $user, ?int $author): void
    {
        foreach (self::NAMES as $position => $name) {
            $folder = Folder::firstOrCreate(
                [
                    'category_id' => $category->getKey(),
                    'name' => $name,
                    'is_personal_of_user_id' => $user->getKey(),
                ],
                ['position' => $position, 'created_by_id' => $author],
            );

            if ($position === 0 && $folder->files()->doesntExist()) {
                $this->demoPdf($folder, $user, $author);
            }
        }
    }

    private function demoPdf(Folder $folder, User $owner, ?int $author): void
    {
        // A minimal but valid one-page PDF, so GET /files/{file}/download returns
        // something a viewer can actually open.
        $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\n"
            ."trailer<</Root 1 0 R>>\n%%EOF\n";

        $path = "documents/{$folder->category->company_id}/demo-cv-{$owner->getKey()}.pdf";
        Storage::put($path, $pdf);

        $file = $folder->files()->create([
            'name' => 'Curriculum_vitae.pdf',
            'media_kind' => 'document',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($pdf),
            'owner_user_id' => $owner->getKey(),
            'uploaded_by_id' => $author,
        ]);

        $version = $file->versions()->create([
            'version_no' => 1,
            'storage_path' => $path,
            'size_bytes' => strlen($pdf),
            'uploaded_by_id' => $author,
        ]);

        $file->update(['current_version_id' => $version->getKey()]);
    }
}
