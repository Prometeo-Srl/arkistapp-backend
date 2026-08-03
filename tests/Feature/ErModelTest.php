<?php

namespace Tests\Feature;

use App\Enums\AccessPermission;
use App\Enums\GranteeType;
use App\Enums\IncidentKind;
use App\Enums\SeverityBucket;
use App\Enums\UserType;
use App\Models\AccessGrant;
use App\Models\Acknowledgement;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\DocumentType;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Folder;
use App\Models\IncidentReport;
use App\Models\OrgRole;
use App\Models\User;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\OrgRoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([OrgRoleSeeder::class, DocumentTypeSeeder::class]);
    }

    public function test_it_walks_the_document_graph_and_derives_expiry(): void
    {
        $operator = User::factory()->create(['type' => UserType::PrometeoOperator]);
        $worker = User::factory()->create();

        $company = Company::create([
            'name' => 'Acme Srl',
            'created_by_operator_id' => $operator->id,
        ]);

        $membership = CompanyMembership::create([
            'company_id' => $company->id,
            'user_id' => $worker->id,
        ]);
        $preposto = OrgRole::where('code', 'preposto')->firstOrFail();
        $membership->orgRoles()->attach($preposto, ['appointed_at' => '2026-01-01']);

        $category = Category::create(['company_id' => $company->id, 'name' => 'Formazione']);
        $folder = Folder::create([
            'category_id' => $category->id,
            'name' => $worker->name,
            'is_personal_of_user_id' => $worker->id,
        ]);

        // Dynamic expiry: fire safety = 60 months from the document date.
        $antincendio = DocumentType::where('code', 'attestato_antincendio')->firstOrFail();
        $file = File::create([
            'folder_id' => $folder->id,
            'document_type_id' => $antincendio->id,
            'name' => 'attestato.pdf',
            'issued_at' => '2026-03-10',
            'requires_acknowledgement' => true,
        ]);

        $this->assertSame('2031-03-10', $file->expires_at->toDateString());

        $version = FileVersion::create([
            'file_id' => $file->id,
            'version_no' => 1,
            'storage_path' => 'files/attestato-v1.pdf',
        ]);
        $file->update(['current_version_id' => $version->id]);

        Acknowledgement::create([
            'file_id' => $file->id,
            'file_version_id' => $version->id,
            'user_id' => $worker->id,
            'confirmed_at' => now(),
        ]);

        // The same user can confirm a given version only once.
        $this->expectException(QueryException::class);
        Acknowledgement::create([
            'file_id' => $file->id,
            'file_version_id' => $version->id,
            'user_id' => $worker->id,
        ]);
    }

    public function test_access_grants_reach_the_user_through_org_roles(): void
    {
        $worker = User::factory()->create();
        $company = Company::create(['name' => 'Acme Srl']);
        $membership = CompanyMembership::create([
            'company_id' => $company->id,
            'user_id' => $worker->id,
        ]);
        $preposto = OrgRole::where('code', 'preposto')->firstOrFail();
        $membership->orgRoles()->attach($preposto);

        $category = Category::create(['company_id' => $company->id, 'name' => 'DVR']);
        $category->accessGrants()->create([
            'grantee_type' => GranteeType::OrgRole,
            'grantee_id' => $preposto->id,
            'permission' => AccessPermission::Custodian,
        ]);

        // Grant towards a different role: must not show up.
        $category->accessGrants()->create([
            'grantee_type' => GranteeType::OrgRole,
            'grantee_id' => OrgRole::where('code', 'rls')->firstOrFail()->id,
            'permission' => AccessPermission::Viewer,
        ]);

        $grants = AccessGrant::query()->active()->forUser($worker, $company->id)->get();

        $this->assertCount(1, $grants);
        $this->assertSame(AccessPermission::Custodian, $grants->first()->permission);
        $this->assertTrue($grants->first()->permission->canManageExpiry());
        $this->assertFalse($grants->first()->permission->canWrite());
        $this->assertTrue($grants->first()->grantable->is($category));
    }

    public function test_anonymous_report_drops_the_reporter_and_buckets_severity(): void
    {
        $worker = User::factory()->create();
        $company = Company::create(['name' => 'Acme Srl']);

        $nearMiss = IncidentReport::create([
            'company_id' => $company->id,
            'kind' => IncidentKind::NearMiss,
            'is_anonymous' => true,
            'reported_by_id' => $worker->id,
            'description' => 'Scala non assicurata',
        ]);

        $this->assertNull($nearMiss->fresh()->reported_by_id);
        $this->assertNull($nearMiss->severity_bucket);

        $injury = IncidentReport::create([
            'company_id' => $company->id,
            'kind' => IncidentKind::Injury,
            'reported_by_id' => $worker->id,
            'absence_days' => 45,
        ]);

        $this->assertSame(SeverityBucket::Over40Days, $injury->severity_bucket);
        $this->assertSame($worker->id, $injury->reported_by_id);
    }
}
