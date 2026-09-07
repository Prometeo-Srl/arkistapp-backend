<?php

namespace Tests\Feature;

use App\Enums\CompanySizeBand;
use App\Enums\MembershipStatus;
use App\Enums\WorkspaceKind;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\MembershipRole;
use App\Models\OrgRole;
use App\Models\OrgRolePermission;
use App\Models\User;
use Database\Seeders\OrgRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrgChartTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OrgRoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->company = Company::factory()->create([
            'kind' => WorkspaceKind::Business,
            'owner_user_id' => $this->admin->getKey(),
        ]);
        CompanyMembership::create([
            'company_id' => $this->company->getKey(),
            'user_id' => $this->admin->getKey(),
            'status' => MembershipStatus::Active,
            'is_admin' => true,
        ]);

        $this->actingAs($this->admin);
    }

    private function orgRole(string $code): OrgRole
    {
        return OrgRole::where('code', $code)->sole();
    }

    private function putRole(string $code, array $entries)
    {
        return $this->putJson(
            "/api/companies/{$this->company->getKey()}/org-chart/{$code}",
            ['entries' => $entries],
        );
    }

    private function postChart(array $payload)
    {
        return $this->postJson(
            "/api/companies/{$this->company->getKey()}/org-chart",
            $payload,
        );
    }

    public function test_conferma_e_concludi_saves_the_band_and_every_role_at_once(): void
    {
        $response = $this->postChart([
            'size_band' => 'piccola',
            'roles' => [
                ['code' => 'rspp', 'entries' => [['is_me' => true]]],
                [
                    'code' => 'rls',
                    'entries' => [
                        ['name' => 'Rep', 'email' => 'rep@example.com', 'is_territorial' => true],
                    ],
                ],
                ['code' => 'preposto', 'entries' => []],
            ],
        ]);

        $response->assertOk()->assertJsonPath('size_band', 'piccola');
        $this->assertSame(CompanySizeBand::Piccola, $this->company->fresh()->size_band);

        $this->assertTrue(
            $this->admin->activeOrgRoleIds($this->company->getKey())
                ->contains($this->orgRole('rspp')->getKey()),
        );

        $rls = MembershipRole::where('org_role_id', $this->orgRole('rls')->getKey())->sole();
        $this->assertTrue($rls->is_territorial);
    }

    /**
     * The reason the whole chart is submitted at once: a half-filled form must
     * not leave accounts behind for people who were never confirmed.
     */
    public function test_nothing_is_written_when_any_role_in_the_submission_is_invalid(): void
    {
        $this->postChart([
            'size_band' => 'grande',
            'roles' => [
                ['code' => 'rspp', 'entries' => [['name' => 'Fine', 'email' => 'fine@example.com']]],
                // Unique per company, so two entries is a validation failure.
                [
                    'code' => 'datore_lavoro_secondario',
                    'entries' => [
                        ['name' => 'One', 'email' => 'one@example.com'],
                        ['name' => 'Two', 'email' => 'two@example.com'],
                    ],
                ],
            ],
        ])->assertUnprocessable();

        $this->assertSame(1, User::count(), 'Only the admin may exist.');
        $this->assertNull($this->company->fresh()->size_band);
        $this->assertSame(0, MembershipRole::count());
    }

    public function test_it_rejects_the_same_role_submitted_twice(): void
    {
        $this->postChart([
            'roles' => [
                ['code' => 'rspp', 'entries' => [['name' => 'A', 'email' => 'a@example.com']]],
                ['code' => 'rspp', 'entries' => [['name' => 'B', 'email' => 'b@example.com']]],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('roles');

        $this->assertSame(1, User::count());
    }

    public function test_it_rejects_an_unknown_role_code(): void
    {
        $this->postChart([
            'roles' => [['code' => 'capo_supremo', 'entries' => []]],
        ])->assertUnprocessable()->assertJsonValidationErrors('roles.0.code');
    }

    public function test_skipping_step_one_leaves_the_band_unset(): void
    {
        $this->postChart(['roles' => []])->assertOk();

        $this->assertNull($this->company->fresh()->size_band);
    }

    public function test_it_rosters_a_person_without_giving_them_any_access_yet(): void
    {
        $this->putRole('rspp', [
            ['name' => 'Marta Rossellini', 'email' => 'martarossellini@gmail.com'],
        ])->assertOk();

        $user = User::where('email', 'martarossellini@gmail.com')->sole();
        $this->assertSame('Marta Rossellini', $user->name);
        $this->assertTrue($user->must_change_password);

        $membership = $this->company->memberships()->where('user_id', $user->getKey())->sole();
        $this->assertSame(MembershipStatus::Invited, $membership->status);
        $this->assertFalse($membership->is_admin);

        // An `invited` membership must grant nothing: this is what stops an admin
        // from typing a stranger's email and gaining rights over their account.
        $this->assertFalse($user->isMemberOf($this->company));
        $this->assertCount(0, $user->activeOrgRoleIds($this->company->getKey()));
    }

    public function test_a_rostered_account_cannot_be_signed_into(): void
    {
        $this->putRole('rspp', [['name' => 'Marta', 'email' => 'marta@example.com']])->assertOk();

        $user = User::where('email', 'marta@example.com')->sole();
        foreach (['', 'password', 'marta@example.com'] as $guess) {
            $this->assertFalse(Hash::check($guess, $user->password));
        }
    }

    public function test_sono_io_appoints_the_signed_in_admin_instead_of_a_new_account(): void
    {
        $this->putRole('rspp', [['is_me' => true]])->assertOk();

        $this->assertSame(1, User::count(), 'No extra account may be created for "sono io".');
        $this->assertTrue(
            $this->admin->activeOrgRoleIds($this->company->getKey())
                ->contains($this->orgRole('rspp')->getKey()),
        );
    }

    public function test_one_person_can_hold_two_roles_without_being_duplicated(): void
    {
        $this->putRole('rspp', [['name' => 'Lorenzo', 'email' => 'lorenzo@example.com']])->assertOk();
        $this->putRole('dirigente', [['name' => 'Lorenzo', 'email' => 'lorenzo@example.com']])->assertOk();

        $user = User::where('email', 'lorenzo@example.com')->sole();
        $this->assertSame(1, $this->company->memberships()->where('user_id', $user->getKey())->count());

        $membership = $this->company->memberships()->where('user_id', $user->getKey())->sole();
        $this->assertCount(2, $membership->membershipRoles);
    }

    public function test_the_same_email_is_matched_case_insensitively(): void
    {
        $this->putRole('rspp', [['name' => 'Ada', 'email' => 'Ada@Example.com']])->assertOk();
        $this->putRole('dirigente', [['name' => 'Ada', 'email' => 'ada@example.com']])->assertOk();

        $this->assertSame(2, User::count(), 'The admin plus Ada, counted once.');
    }

    public function test_dropping_someone_from_the_list_revokes_the_appointment(): void
    {
        $this->putRole('rspp', [
            ['name' => 'Lorenzo', 'email' => 'lorenzo@example.com'],
            ['name' => 'Marta', 'email' => 'marta@example.com'],
        ])->assertOk();

        $response = $this->putRole('rspp', [['name' => 'Marta', 'email' => 'marta@example.com']]);

        $response->assertOk();
        $entries = collect($response->json('roles'))->firstWhere('code', 'rspp')['entries'];
        $this->assertSame(['marta@example.com'], array_column($entries, 'email'));

        $lorenzo = User::where('email', 'lorenzo@example.com')->sole();
        $this->assertCount(0, $lorenzo->activeOrgRoleIds($this->company->getKey()));
    }

    public function test_relisting_a_revoked_person_reuses_the_same_appointment_row(): void
    {
        $entry = [['name' => 'Marta', 'email' => 'marta@example.com']];

        $this->putRole('rspp', $entry)->assertOk();
        $this->putRole('rspp', [])->assertOk();
        $this->putRole('rspp', $entry)->assertOk();

        // (membership, role) is unique, so re-appointing must clear revoked_at
        // rather than try to insert a second row.
        $this->assertSame(1, MembershipRole::where('org_role_id', $this->orgRole('rspp')->getKey())->count());
        $this->assertNull(MembershipRole::where('org_role_id', $this->orgRole('rspp')->getKey())->sole()->revoked_at);
    }

    public function test_it_stores_the_rls_territoriale_flag(): void
    {
        $this->putRole('rls', [
            ['name' => 'Union Rep', 'email' => 'rep@example.com', 'is_territorial' => true],
            ['name' => 'Internal Rep', 'email' => 'internal@example.com'],
        ])->assertOk();

        $entries = collect($this->getJson("/api/companies/{$this->company->getKey()}/org-chart")->json('roles'))
            ->firstWhere('code', 'rls')['entries'];

        $this->assertSame(
            ['rep@example.com' => true, 'internal@example.com' => false],
            array_combine(array_column($entries, 'email'), array_column($entries, 'is_territorial')),
        );
    }

    public function test_a_role_marked_unique_per_company_rejects_a_second_person(): void
    {
        $this->assertTrue($this->orgRole('datore_lavoro_secondario')->is_unique_per_company);

        $this->putRole('datore_lavoro_secondario', [
            ['name' => 'One', 'email' => 'one@example.com'],
            ['name' => 'Two', 'email' => 'two@example.com'],
        ])->assertUnprocessable()->assertJsonValidationErrors('entries');
    }

    /** The prototype gives RSPP "+ aggiungi" and shows two of them. */
    public function test_rspp_accepts_more_than_one_person(): void
    {
        $this->assertFalse($this->orgRole('rspp')->is_unique_per_company);

        $this->putRole('rspp', [
            ['name' => 'Lorenzo Lombardi', 'email' => 'lorenzo@example.com'],
            ['name' => 'Marta Rossellini', 'email' => 'marta@example.com'],
        ])->assertOk();

        $this->assertSame(2, MembershipRole::where('org_role_id', $this->orgRole('rspp')->getKey())->count());
    }

    public function test_it_rejects_the_same_email_twice_in_one_role(): void
    {
        $this->putRole('rspp', [
            ['name' => 'A', 'email' => 'same@example.com'],
            ['name' => 'B', 'email' => 'same@example.com'],
        ])->assertUnprocessable()->assertJsonValidationErrors('entries');
    }

    public function test_it_rejects_more_than_one_sono_io(): void
    {
        $this->putRole('rspp', [['is_me' => true], ['is_me' => true]])
            ->assertUnprocessable()->assertJsonValidationErrors('entries');
    }

    public function test_an_entry_needs_an_email_unless_it_is_me(): void
    {
        $this->putRole('rspp', [['name' => 'Nameless']])
            ->assertUnprocessable()->assertJsonValidationErrors('entries.0.email');
    }

    public function test_it_leaves_an_existing_account_name_and_access_alone(): void
    {
        $existing = User::factory()->create(['name' => 'Their Own Name']);
        $activeMembership = CompanyMembership::create([
            'company_id' => $this->company->getKey(),
            'user_id' => $existing->getKey(),
            'status' => MembershipStatus::Active,
            'is_admin' => true,
        ]);

        $this->putRole('rspp', [['name' => 'Typed Over', 'email' => $existing->email]])->assertOk();

        $this->assertSame('Their Own Name', $existing->fresh()->name);
        $this->assertSame(MembershipStatus::Active, $activeMembership->fresh()->status);
        $this->assertTrue($activeMembership->fresh()->is_admin, 'An active admin must not be demoted.');
    }

    public function test_step_one_saves_the_size_band(): void
    {
        $this->patchJson("/api/companies/{$this->company->getKey()}", ['size_band' => 'piccola'])
            ->assertOk();

        $this->assertSame(CompanySizeBand::Piccola, $this->company->fresh()->size_band);
        $this->assertSame(
            'piccola',
            $this->getJson("/api/companies/{$this->company->getKey()}/org-chart")->json('size_band'),
        );
    }

    public function test_it_rejects_a_size_band_outside_the_four_offered(): void
    {
        $this->patchJson("/api/companies/{$this->company->getKey()}", ['size_band' => 'enormous'])
            ->assertUnprocessable()->assertJsonValidationErrors('size_band');
    }

    public function test_the_role_list_is_returned_in_prototype_order(): void
    {
        $codes = array_column(
            $this->getJson("/api/companies/{$this->company->getKey()}/org-chart")->json('roles'),
            'code',
        );

        $this->assertSame([
            'datore_lavoro', 'datore_lavoro_secondario', 'rspp', 'aspp',
            'medico_competente', 'rls', 'dirigente', 'preposto', 'lavoratore',
        ], $codes);
    }

    public function test_a_non_admin_member_may_read_but_not_write_the_org_chart(): void
    {
        $worker = User::factory()->create();
        CompanyMembership::create([
            'company_id' => $this->company->getKey(),
            'user_id' => $worker->getKey(),
            'status' => MembershipStatus::Active,
            'is_admin' => false,
        ]);

        $this->actingAs($worker);

        $this->getJson("/api/companies/{$this->company->getKey()}/org-chart")->assertOk();
        $this->putRole('rspp', [['name' => 'X', 'email' => 'x@example.com']])->assertForbidden();
        $this->postChart(['roles' => []])->assertForbidden();
    }

    public function test_an_outsider_cannot_touch_the_org_chart(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson("/api/companies/{$this->company->getKey()}/org-chart")->assertForbidden();
        $this->putRole('rspp', [['name' => 'X', 'email' => 'x@example.com']])->assertForbidden();
        $this->postChart(['roles' => []])->assertForbidden();
    }

    public function test_it_does_not_revoke_appointments_belonging_to_another_company(): void
    {
        $otherAdmin = User::factory()->create();
        $other = Company::factory()->create([
            'kind' => WorkspaceKind::Business,
            'owner_user_id' => $otherAdmin->getKey(),
        ]);
        $otherMembership = CompanyMembership::create([
            'company_id' => $other->getKey(),
            'user_id' => $otherAdmin->getKey(),
            'status' => MembershipStatus::Active,
            'is_admin' => true,
        ]);
        $otherMembership->orgRoles()->attach($this->orgRole('rspp')->getKey(), [
            'appointed_at' => now()->toDateString(),
        ]);

        // Emptying this company's RSPP list must not reach into the other tenant.
        $this->putRole('rspp', [])->assertOk();

        $this->assertCount(1, $otherAdmin->activeOrgRoleIds($other->getKey()));
    }

    private function permissions()
    {
        return $this->getJson("/api/companies/{$this->company->getKey()}/org-role-permissions");
    }

    private function putPermissions(string $code, array $payload)
    {
        return $this->putJson(
            "/api/companies/{$this->company->getKey()}/org-role-permissions/{$code}",
            $payload,
        );
    }

    /** 086 lists every grantable role, with both switches on until touched. */
    public function test_gestisci_autorizzazioni_defaults_to_granted_for_every_role_but_the_employer(): void
    {
        $roles = $this->permissions()->assertOk()->json('roles');

        $this->assertSame([
            'datore_lavoro_secondario', 'rspp', 'aspp', 'medico_competente',
            'rls', 'dirigente', 'preposto', 'lavoratore',
        ], array_column($roles, 'code'));

        foreach ($roles as $role) {
            $this->assertTrue($role['can_view_org_chart']);
            $this->assertTrue($role['can_view_incidents']);
        }
    }

    public function test_a_switch_persists_and_leaves_the_other_roles_alone(): void
    {
        $roles = $this->putPermissions('aspp', [
            'can_view_org_chart' => false,
            'can_view_incidents' => true,
        ])->assertOk()->json('roles');

        $aspp = collect($roles)->firstWhere('code', 'aspp');
        $this->assertFalse($aspp['can_view_org_chart']);
        $this->assertTrue($aspp['can_view_incidents']);
        $this->assertTrue(collect($roles)->firstWhere('code', 'rspp')['can_view_org_chart']);

        // A second write updates the one row instead of inserting a rival.
        $this->putPermissions('aspp', [
            'can_view_org_chart' => true,
            'can_view_incidents' => false,
        ])->assertOk();

        $this->assertSame(1, OrgRolePermission::where('company_id', $this->company->getKey())->count());
        $aspp = collect($this->permissions()->json('roles'))->firstWhere('code', 'aspp');
        $this->assertTrue($aspp['can_view_org_chart']);
        $this->assertFalse($aspp['can_view_incidents']);
    }

    public function test_only_an_admin_may_change_the_authorizations(): void
    {
        $worker = User::factory()->create();
        CompanyMembership::create([
            'company_id' => $this->company->getKey(),
            'user_id' => $worker->getKey(),
            'status' => MembershipStatus::Active,
            'is_admin' => false,
        ]);

        $this->actingAs($worker);
        $this->permissions()->assertOk();
        $this->putPermissions('aspp', ['can_view_org_chart' => false, 'can_view_incidents' => false])
            ->assertForbidden();

        $this->actingAs(User::factory()->create());
        $this->permissions()->assertForbidden();
        $this->putPermissions('aspp', ['can_view_org_chart' => false, 'can_view_incidents' => false])
            ->assertForbidden();
    }

    /** A non-admin member holding the given appointments, or none at all. */
    private function member(array $roleCodes = []): User
    {
        $user = User::factory()->create();
        $membership = CompanyMembership::create([
            'company_id' => $this->company->getKey(),
            'user_id' => $user->getKey(),
            'status' => MembershipStatus::Active,
            'is_admin' => false,
        ]);

        foreach ($roleCodes as $code) {
            $membership->orgRoles()->attach($this->orgRole($code)->getKey(), [
                'appointed_at' => now()->toDateString(),
            ]);
        }

        return $user;
    }

    private function orgChart()
    {
        return $this->getJson("/api/companies/{$this->company->getKey()}/org-chart");
    }

    private function incidents()
    {
        return $this->getJson("/api/companies/{$this->company->getKey()}/incidents");
    }

    public function test_revoking_can_view_org_chart_closes_the_whole_organigramma_for_that_role(): void
    {
        $this->putPermissions('aspp', ['can_view_org_chart' => false, 'can_view_incidents' => true])
            ->assertOk();

        $this->actingAs($this->member(['aspp']));
        $this->orgChart()->assertForbidden();
        $this->getJson("/api/companies/{$this->company->getKey()}/members")->assertForbidden();
        $this->permissions()->assertForbidden();
        // The other half of 086 is untouched.
        $this->incidents()->assertOk();

        // A role that still grants it keeps the section open.
        $this->actingAs($this->member(['rspp']));
        $this->orgChart()->assertOk();
    }

    public function test_a_second_appointment_that_still_grants_keeps_the_section_open(): void
    {
        $this->putPermissions('aspp', ['can_view_org_chart' => false, 'can_view_incidents' => false])
            ->assertOk();

        $this->actingAs($this->member(['aspp', 'rspp']));
        $this->orgChart()->assertOk();
        $this->incidents()->assertOk();
    }

    /** A member with no appointment is read as `lavoratore`, which is the switch 086 offers. */
    public function test_revoking_the_lavoratore_switch_closes_the_sections_for_a_plain_member(): void
    {
        $this->putPermissions('lavoratore', ['can_view_org_chart' => false, 'can_view_incidents' => false])
            ->assertOk();

        $this->actingAs($this->member());
        $this->orgChart()->assertForbidden();
        $this->incidents()->assertForbidden();
        // Nothing to file into a list they may not read.
        $this->postJson("/api/companies/{$this->company->getKey()}/incidents", [
            'kind' => 'near_miss',
            'description' => 'x',
        ])->assertForbidden();
    }

    public function test_the_employer_keeps_seeing_everything_after_revoking_every_role(): void
    {
        $codes = ['datore_lavoro_secondario', 'rspp', 'aspp', 'medico_competente',
            'rls', 'dirigente', 'preposto', 'lavoratore'];

        foreach ($codes as $code) {
            $this->putPermissions($code, ['can_view_org_chart' => false, 'can_view_incidents' => false])
                ->assertOk();
        }

        $this->orgChart()->assertOk();
        $this->incidents()->assertOk();
        $this->permissions()->assertOk();
    }

    /** A counter is still a number about a section the caller may not open. */
    public function test_the_home_summary_leaves_out_the_counters_of_a_closed_section(): void
    {
        $this->putPermissions('lavoratore', ['can_view_org_chart' => false, 'can_view_incidents' => true])
            ->assertOk();

        $this->actingAs($this->member());

        $summary = $this->getJson("/api/companies/{$this->company->getKey()}/summary")
            ->assertOk()->json('data');

        $this->assertArrayNotHasKey('org_chart_members', $summary);
        $this->assertArrayHasKey('serious_injuries', $summary);
        $this->assertArrayHasKey('reports', $summary);
        $this->assertArrayHasKey('open_activities', $summary);
    }

    /** What the app hides its tabs on. */
    public function test_the_workspace_list_carries_the_callers_effective_permissions(): void
    {
        $this->putPermissions('aspp', ['can_view_org_chart' => false, 'can_view_incidents' => true])
            ->assertOk();

        $this->actingAs($this->member(['aspp']));

        $this->getJson('/api/companies')->assertOk()->assertJsonPath(
            'data.0.membership.permissions',
            ['can_view_org_chart' => false, 'can_view_incidents' => true],
        );
    }
}
