<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Folder;
use App\Models\User;
use App\Observers\CompanyMembershipObserver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Backfill: CompanyMembershipObserver now creates a worker's personal branch when
 * they are appointed, so this only covers memberships that predate that, and adds
 * one readable PDF in "curriculum vitae" so 260 and "scarica" have something.
 *
 * Demo data, not part of DatabaseSeeder. Run it on purpose:
 *   sail artisan db:seed --class=PersonalFoldersSeeder
 */
class PersonalFoldersSeeder extends Seeder
{
    public function run(?Company $only = null): void
    {
        $companies = $only ? collect([$only]) : Company::all();

        foreach ($companies as $company) {
            $members = $company->memberships()->with('user')->get();

            foreach ($members as $membership) {
                if (! $membership->user) {
                    continue;
                }

                $folders = CompanyMembershipObserver::seedPersonalFolders($membership);
                $cv = $folders['curriculum vitae'] ?? null;

                if ($cv && $cv->files()->doesntExist()) {
                    $this->demoPdf($cv, $membership->user, $company->owner_user_id);
                }
            }

            $this->command?->info(
                "{$company->name}: "
                .($members->count() * count(CompanyMembershipObserver::PERSONAL_FOLDERS)).' folders'
            );
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
