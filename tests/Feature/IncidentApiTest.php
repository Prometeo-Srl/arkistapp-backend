<?php

namespace Tests\Feature;

use App\Enums\IncidentKind;
use App\Enums\IncidentStatus;
use App\Enums\MediaKind;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\IncidentAttachment;
use App\Models\IncidentReport;
use App\Models\OrgRole;
use App\Models\User;
use Database\Seeders\OrgRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IncidentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OrgRoleSeeder::class);
    }

    private function actingAsUser(?User $user = null): User
    {
        return tap($user ?? User::factory()->create(), fn (User $u) => Sanctum::actingAs($u));
    }

    /**
     * The admin also holds the datore di lavoro appointment: filing an injury is
     * the employer's, not the workspace admin's, and the two are the same person
     * in every company that has not delegated the role.
     *
     * @return array{0: Company, 1: User, 2: User}
     */
    private function companyWithAdminAndWorker(): array
    {
        $admin = $this->actingAsUser();
        $company = Company::create(['name' => 'Acme Srl', 'owner_user_id' => $admin->id]);
        $membership = CompanyMembership::factory()->for($company)->for($admin)->admin()->create();
        $membership->orgRoles()->attach(OrgRole::where('code', 'datore_lavoro')->firstOrFail());
        $worker = $this->actingAsUser();
        CompanyMembership::factory()->for($company)->for($worker)->create();

        return [$company, $admin, $worker];
    }

    public function test_employer_reports_an_injury_and_severity_bucket_is_derived(): void
    {
        [$company, $employer, $worker] = $this->companyWithAdminAndWorker();

        Sanctum::actingAs($employer);
        $this->postJson("/api/companies/{$company->id}/incidents", [
            'kind' => IncidentKind::Injury->value,
            'occurred_at' => now()->subDay()->toDateString(),
            'description' => 'Caduta dalla scala in magazzino',
            'absence_days' => 45,
        ])->assertCreated()->assertJsonPath('data.severity_bucket', 'over_40_days');

        Sanctum::actingAs($worker);
        $this->postJson("/api/companies/{$company->id}/incidents", [
            'kind' => IncidentKind::NearMiss->value,
            'description' => 'Sfiorato da un carrello elevatore',
            'absence_days' => 5,
        ])->assertCreated()->assertJsonPath('data.severity_bucket', 'under_40_days');

        $this->assertDatabaseHas('incident_reports', [
            'company_id' => $company->id,
            'reported_by_id' => $employer->id,
            'severity_bucket' => 'over_40_days',
        ]);
    }

    public function test_only_an_employer_may_file_an_injury(): void
    {
        [$company, , $worker] = $this->companyWithAdminAndWorker();

        Sanctum::actingAs($worker);
        $this->postJson("/api/companies/{$company->id}/incidents", [
            'kind' => IncidentKind::Injury->value,
            'description' => 'Caduta in magazzino',
        ])->assertForbidden();

        $this->assertDatabaseCount('incident_reports', 0);
    }

    public function test_a_worker_neither_lists_nor_reads_the_company_injuries(): void
    {
        [$company, $employer] = $this->companyWithAdminAndWorker();
        $injury = IncidentReport::factory()->for($company)->create([
            'kind' => IncidentKind::Injury,
            'reported_by_id' => $employer->id,
        ]);
        $nearMiss = IncidentReport::factory()->for($company)->create(['kind' => IncidentKind::NearMiss]);

        $reader = $this->actingAsUser();
        CompanyMembership::factory()->for($company)->for($reader)->create();

        $this->getJson("/api/companies/{$company->id}/incidents")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $nearMiss->id);
        $this->getJson("/api/incidents/{$injury->id}")->assertForbidden();

        // The RSPP is one of the three appointments the injury list is written for.
        $membership = CompanyMembership::where('user_id', $reader->id)->firstOrFail();
        $membership->orgRoles()->attach(OrgRole::where('code', 'rspp')->firstOrFail());

        $this->getJson("/api/companies/{$company->id}/incidents")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/incidents/{$injury->id}")->assertOk();
    }

    public function test_anonymous_report_strips_the_reporter(): void
    {
        [$company, , $worker] = $this->companyWithAdminAndWorker();

        Sanctum::actingAs($worker);
        $response = $this->postJson("/api/companies/{$company->id}/incidents", [
            'kind' => IncidentKind::NearMiss->value,
            'description' => 'Segnalazione anonima',
            'is_anonymous' => true,
        ])->assertCreated()
            ->assertJsonPath('data.is_anonymous', true)
            ->assertJsonMissingPath('data.reported_by');

        $this->assertDatabaseHas('incident_reports', [
            'id' => $response->json('data.id'),
            'reported_by_id' => null,
        ]);
    }

    public function test_reporter_edits_own_draft_but_other_members_cannot(): void
    {
        [$company, , $worker] = $this->companyWithAdminAndWorker();
        $incident = IncidentReport::factory()->for($company)->create(['reported_by_id' => $worker->id]);

        $other = $this->actingAsUser();
        CompanyMembership::factory()->for($company)->for($other)->create();
        $this->patchJson("/api/incidents/{$incident->id}", ['description' => 'Intrusione'])
            ->assertForbidden();

        Sanctum::actingAs($worker);
        $this->patchJson("/api/incidents/{$incident->id}", [
            'description' => 'Dettagli aggiornati',
            'location' => 'Zona carico',
        ])->assertOk()->assertJsonPath('data.description', 'Dettagli aggiornati');

        $this->assertSame('Zona carico', $incident->fresh()->location);
    }

    public function test_admin_reviews_and_closes_an_incident(): void
    {
        [$company, $admin, $worker] = $this->companyWithAdminAndWorker();
        $incident = IncidentReport::factory()->for($company)->create([
            'reported_by_id' => $worker->id,
            'absence_days' => 10,
        ]);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/incidents/{$incident->id}", [
            'status' => IncidentStatus::Closed->value,
            'causes' => 'Pavimento bagnato',
            'actions_taken' => 'Segnaletica installata',
            'inail_ref' => 'INAIL-2026-0001',
        ])->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.inail_ref', 'INAIL-2026-0001');

        $this->assertDatabaseHas('incident_reports', [
            'id' => $incident->id,
            'status' => IncidentStatus::Closed->value,
            'reviewed_by_id' => $admin->id,
        ]);
        $this->assertNotNull($incident->fresh()->closed_at);
    }

    public function test_member_uploads_an_attachment(): void
    {
        Storage::fake();
        [$company, , $worker] = $this->companyWithAdminAndWorker();
        $incident = IncidentReport::factory()->for($company)->create([
            'kind' => IncidentKind::NearMiss,
            'reported_by_id' => $worker->id,
        ]);

        Sanctum::actingAs($worker);
        $response = $this->postJson("/api/incidents/{$incident->id}/attachments", [
            'file' => UploadedFile::fake()->create('foto.jpg', 200),
            'caption' => 'Foto del danno',
        ])->assertCreated()->assertJsonPath('data.caption', 'Foto del danno');

        $this->assertDatabaseHas('incident_attachments', [
            'incident_report_id' => $incident->id,
            'media_kind' => MediaKind::Image->value,
            'caption' => 'Foto del danno',
        ]);

        $attachment = IncidentAttachment::findOrFail($response->json('data.id'));
        Storage::assertExists($attachment->storage_path);
    }

    public function test_attachment_rejects_a_type_outside_the_incident_allowlist(): void
    {
        Storage::fake();
        [$company, , $worker] = $this->companyWithAdminAndWorker();
        $incident = IncidentReport::factory()->for($company)->create([
            'kind' => IncidentKind::NearMiss,
            'reported_by_id' => $worker->id,
        ]);

        Sanctum::actingAs($worker);
        // Referti, verbali e documenti only: the archive's wider allowlist (audio,
        // video, spreadsheets) does not apply to an incident report.
        $this->postJson("/api/incidents/{$incident->id}/attachments", [
            'file' => UploadedFile::fake()->create('archivio.zip', 10),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_non_member_cannot_access_incidents(): void
    {
        [$company, , $worker] = $this->companyWithAdminAndWorker();
        $incident = IncidentReport::factory()->for($company)->create(['reported_by_id' => $worker->id]);

        $this->actingAsUser();

        $this->getJson("/api/companies/{$company->id}/incidents")->assertForbidden();
        $this->getJson("/api/incidents/{$incident->id}")->assertForbidden();
        $this->deleteJson("/api/incidents/{$incident->id}")->assertForbidden();
    }
}
