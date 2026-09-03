<?php

namespace Tests\Feature;

use App\Enums\AccessPermission;
use App\Enums\AssignmentStatus;
use App\Enums\GranteeType;
use App\Enums\IncidentKind;
use App\Models\AccessGrant;
use App\Models\Acknowledgement;
use App\Models\Category;
use App\Models\Checklist;
use App\Models\ChecklistAssignment;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Folder;
use App\Models\IncidentReport;
use App\Models\User;
use Database\Seeders\OrgRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "Monitora attività" (prototypes 059/063) and the four counters of 078.
 *
 * The load-bearing claim of the whole feature is the denominator: a person who has
 * not confirmed yet must still have a row, or the ring has nothing to divide by and
 * "in attesa di completamento" lists nobody.
 */
class MonitorBoardApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Company $company;

    private Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([OrgRoleSeeder::class]);

        $this->admin = User::factory()->create();
        $this->company = Company::factory()->create(['owner_user_id' => $this->admin->id]);
        CompanyMembership::factory()->admin()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
        ]);

        $category = Category::factory()->create(['company_id' => $this->company->id]);
        $this->folder = Folder::factory()->create(['category_id' => $category->id]);
    }

    private function member(bool $guest = false): User
    {
        $user = User::factory()->create();
        CompanyMembership::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'is_guest' => $guest,
        ]);

        return $user;
    }

    /** A document with a version, so it can carry a duty at all. */
    private function file(array $attributes = []): File
    {
        $file = File::factory()->create([
            'folder_id' => $this->folder->id,
            ...$attributes,
        ]);

        $version = FileVersion::factory()->create(['file_id' => $file->id]);
        $file->update(['current_version_id' => $version->id]);

        return $file->fresh();
    }

    private function shareWith(User $user, string $type, int $id): AccessGrant
    {
        return AccessGrant::factory()->create([
            'grantable_type' => $type,
            'grantable_id' => $id,
            'grantee_type' => GranteeType::User,
            'grantee_id' => $user->id,
            'permission' => AccessPermission::Viewer,
            'granted_by_id' => $this->admin->id,
        ]);
    }

    public function test_setting_the_duty_materializes_a_row_for_everyone_it_was_shared_with(): void
    {
        $reader = $this->member();
        $stranger = $this->member();

        $this->shareWith($reader, 'folder', $this->folder->id);

        $file = $this->file(['requires_acknowledgement' => true]);

        $rows = Acknowledgement::where('file_version_id', $file->current_version_id)->get();

        $this->assertSame([$reader->id], $rows->pluck('user_id')->all());
        $this->assertNotNull($rows->first()->required_at);
        $this->assertNull($rows->first()->confirmed_at);
        // Nobody shared with, nobody on the roster — and the admin who set the duty
        // is not serving it either.
        $this->assertFalse($rows->pluck('user_id')->contains($stranger->id));
        $this->assertFalse($rows->pluck('user_id')->contains($this->admin->id));
    }

    public function test_a_guest_let_in_by_a_share_is_not_on_the_roster(): void
    {
        $guest = $this->member(guest: true);
        $this->shareWith($guest, 'folder', $this->folder->id);

        $file = $this->file(['requires_acknowledgement' => true]);

        $this->assertSame(0, Acknowledgement::where('file_version_id', $file->current_version_id)->count());
    }

    public function test_revoking_the_share_drops_the_pending_duty_but_keeps_the_receipt(): void
    {
        $confirmed = $this->member();
        $pending = $this->member();
        $grantConfirmed = $this->shareWith($confirmed, 'folder', $this->folder->id);
        $grantPending = $this->shareWith($pending, 'folder', $this->folder->id);

        $file = $this->file(['requires_acknowledgement' => true]);

        Acknowledgement::where('file_version_id', $file->current_version_id)
            ->where('user_id', $confirmed->id)
            ->update(['confirmed_at' => now()]);

        $grantConfirmed->delete();
        $grantPending->delete();

        $rows = Acknowledgement::where('file_version_id', $file->current_version_id)->get();

        $this->assertSame([$confirmed->id], $rows->pluck('user_id')->all());
    }

    public function test_dropping_the_duty_clears_the_pending_roster(): void
    {
        $reader = $this->member();
        $this->shareWith($reader, 'folder', $this->folder->id);

        $file = $this->file(['requires_acknowledgement' => true]);
        $this->assertSame(1, Acknowledgement::where('file_version_id', $file->current_version_id)->count());

        $file->update(['requires_acknowledgement' => false]);

        $this->assertSame(0, Acknowledgement::where('file_version_id', $file->current_version_id)->count());
    }

    public function test_a_new_version_asks_for_a_fresh_confirmation(): void
    {
        $reader = $this->member();
        $this->shareWith($reader, 'folder', $this->folder->id);

        $file = $this->file(['requires_acknowledgement' => true]);
        $firstVersion = $file->current_version_id;

        Acknowledgement::where('file_version_id', $firstVersion)->update(['confirmed_at' => now()]);

        $second = FileVersion::factory()->create(['file_id' => $file->id, 'version_no' => 2]);
        $file->update(['current_version_id' => $second->id]);

        $fresh = Acknowledgement::where('file_version_id', $second->id)->first();

        $this->assertNotNull($fresh);
        $this->assertNull($fresh->confirmed_at);
    }

    public function test_board_derives_the_four_tipologie_and_their_progress(): void
    {
        $a = $this->member();
        $b = $this->member();
        $this->shareWith($a, 'folder', $this->folder->id);
        $this->shareWith($b, 'folder', $this->folder->id);

        $read = $this->file(['name' => 'contratto.pdf', 'requires_acknowledgement' => true]);
        $this->file(['name' => 'regolamento.pdf', 'requires_signature' => true]);
        $this->file(['name' => 'informativa.pdf', 'requires_acknowledgement' => true, 'requires_signature' => true]);
        // No duty at all: not on the board.
        $this->file(['name' => 'organigramma.pdf']);

        Acknowledgement::where('file_version_id', $read->current_version_id)
            ->where('user_id', $a->id)
            ->update(['confirmed_at' => now()]);

        $checklist = Checklist::factory()->create([
            'company_id' => $this->company->id,
            'title' => 'controllo operativo',
            'published_at' => now(),
        ]);
        ChecklistAssignment::factory()->create([
            'checklist_id' => $checklist->id,
            'assignee_type' => GranteeType::User,
            'assignee_id' => $a->id,
            'status' => AssignmentStatus::Completed,
        ]);
        ChecklistAssignment::factory()->create([
            'checklist_id' => $checklist->id,
            'assignee_type' => GranteeType::User,
            'assignee_id' => $b->id,
            'status' => AssignmentStatus::Pending,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/companies/{$this->company->id}/monitor")->assertOk();

        $byKind = collect($response->json('meta.by_kind'))->keyBy('kind');

        $this->assertCount(4, $byKind, 'the carousel always shows all four tipologie');
        $this->assertSame(['completed' => 1, 'total' => 2], [
            'completed' => $byKind['read_document']['completed'],
            'total' => $byKind['read_document']['total'],
        ]);
        $this->assertSame(1, $byKind['read_and_sign']['subjects']);
        $this->assertSame(1, $byKind['fill_checklist']['completed']);
        $this->assertSame(2, $byKind['fill_checklist']['total']);

        // Three documents with a duty, plus the checklist. The one with no duty is out.
        $this->assertCount(4, $response->json('data'));
        $this->assertSame(4, $response->json('meta.counts.open'));
        $this->assertSame(0, $response->json('meta.counts.done'));
    }

    public function test_board_filters_by_tipologia_and_search_without_moving_the_tab_counts(): void
    {
        $reader = $this->member();
        $this->shareWith($reader, 'folder', $this->folder->id);

        $this->file(['name' => 'contratto sicurezza.pdf', 'requires_acknowledgement' => true]);
        $this->file(['name' => 'regolamento interno.pdf', 'requires_signature' => true]);

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/companies/{$this->company->id}/monitor?kind[]=sign")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kind', 'sign')
            // The tabs count the whole board, not the filtered slice.
            ->assertJsonPath('meta.counts.open', 2);

        $this->getJson("/api/companies/{$this->company->id}/monitor?q=SICUREZZA")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'contratto sicurezza.pdf');
    }

    public function test_a_document_completed_by_everybody_moves_to_completate(): void
    {
        $reader = $this->member();
        $this->shareWith($reader, 'folder', $this->folder->id);

        $file = $this->file(['requires_acknowledgement' => true]);
        Acknowledgement::where('file_version_id', $file->current_version_id)->update(['confirmed_at' => now()]);

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/companies/{$this->company->id}/monitor?status=done")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'done')
            ->assertJsonPath('meta.counts.done', 1);
    }

    public function test_subject_splits_the_roster_the_two_ways_063_lists_it(): void
    {
        $done = $this->member();
        $waiting = $this->member();
        $this->shareWith($done, 'folder', $this->folder->id);
        $this->shareWith($waiting, 'folder', $this->folder->id);

        $file = $this->file(['requires_acknowledgement' => true]);
        Acknowledgement::where('file_version_id', $file->current_version_id)
            ->where('user_id', $done->id)
            ->update(['confirmed_at' => now()]);

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/companies/{$this->company->id}/monitor/file/{$file->id}")
            ->assertOk()
            ->assertJsonPath('data.kind', 'read_document')
            ->assertJsonPath('data.label', 'presa visione')
            ->assertJsonCount(1, 'data.completed')
            ->assertJsonCount(1, 'data.pending')
            ->assertJsonPath('data.completed.0.user_id', $done->id)
            ->assertJsonPath('data.pending.0.user_id', $waiting->id)
            // Somebody with no appointment is a plain lavoratore.
            ->assertJsonPath('data.pending.0.role_label', 'lavoratore');
    }

    public function test_subject_404s_for_a_document_with_no_duty_on_it(): void
    {
        $file = $this->file();

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/companies/{$this->company->id}/monitor/file/{$file->id}")->assertNotFound();
    }

    public function test_summary_counts_the_four_home_tiles(): void
    {
        $reader = $this->member();
        $this->shareWith($reader, 'folder', $this->folder->id);
        $this->file(['requires_acknowledgement' => true]);

        IncidentReport::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'kind' => IncidentKind::Injury,
            'absence_days' => 60,
        ]);
        IncidentReport::factory()->create([
            'company_id' => $this->company->id,
            'kind' => IncidentKind::NearMiss,
            'absence_days' => null,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/companies/{$this->company->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.open_activities', 1)
            // The admin and the one worker; a guest would not be on the chart.
            ->assertJsonPath('data.org_chart_members', 2)
            ->assertJsonPath('data.serious_injuries', 2)
            ->assertJsonPath('data.reports', 3);
    }

    public function test_an_outsider_cannot_read_the_board(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/companies/{$this->company->id}/monitor")->assertForbidden();
        $this->getJson("/api/companies/{$this->company->id}/summary")->assertForbidden();
    }
}
