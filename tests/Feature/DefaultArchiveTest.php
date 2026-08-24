<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Folder;
use App\Models\User;
use App\Observers\CompanyObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_business_workspace_gets_the_default_archive(): void
    {
        $owner = User::factory()->create();

        $company = Company::create([
            'name' => 'Edil Costruzioni',
            'kind' => 'business',
            'owner_user_id' => $owner->getKey(),
        ]);

        $tree = config('document_tree');

        $this->assertSame(
            count($tree),
            $company->categories()->count(),
            'One category per section of config/document_tree.php.'
        );

        $luogo = $company->categories()->where('name', 'Luogo di lavoro')->sole();
        $this->assertEqualsCanonicalizing(
            ['Locali', 'Rischio strutturale/sismico'],
            $luogo->rootFolders()->pluck('name')->all()
        );

        // Nesting survives: Scaffalature sits under Locali, its docs under it.
        $locali = $luogo->rootFolders()->where('name', 'Locali')->sole();
        $scaffalature = $locali->children()->where('name', 'Scaffalature (aziendali)')->sole();
        $this->assertSame(5, $scaffalature->children()->count());

        // Every node of the config exists, none twice.
        $this->assertSame($this->countNodes($tree) - count($tree), Folder::query()->count());

        // Idempotent: a backfill on an already-seeded workspace changes nothing.
        CompanyObserver::seedDefaultArchive($company->fresh());
        $this->assertSame($this->countNodes($tree) - count($tree), Folder::query()->count());
        $this->assertSame(count($tree), Category::query()->count());
    }

    public function test_a_personal_workspace_gets_no_default_archive(): void
    {
        $company = Company::personalFor(User::factory()->create());

        $this->assertSame(0, $company->categories()->count());
    }

    private function countNodes(array $nodes): int
    {
        $count = 0;

        foreach ($nodes as $key => $value) {
            $count += 1 + (is_int($key) ? 0 : $this->countNodes($value));
        }

        return $count;
    }
}
