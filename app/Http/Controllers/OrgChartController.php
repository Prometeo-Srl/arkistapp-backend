<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Enums\UserType;
use App\Http\Requests\UpdateOrgChartRoleRequest;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\MembershipRole;
use App\Models\OrgRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Imposta Organigramma": the company admin lists the people holding each
 * D.Lgs 81/08 role. They are rostered, not admitted — every membership created
 * here stays `invited`, which grants no access until it is accepted, so typing a
 * stranger's email cannot hand your company any rights over their account.
 */
class OrgChartController extends Controller
{
    /** Feeds step 2, whose rows are a role label and the number of people in it. */
    public function show(Request $request, Company $company)
    {
        $this->authorize('viewMembers', $company);

        $roles = OrgRole::query()->orderBy('position')->get();
        $appointments = $this->activeAppointments($company);

        return response()->json([
            'size_band' => $company->size_band,
            'roles' => $roles->map(fn (OrgRole $role) => [
                'id' => $role->id,
                'code' => $role->code,
                'label' => $role->label,
                'is_unique_per_company' => $role->is_unique_per_company,
                'min_required' => $role->min_required,
                'entries' => $appointments
                    ->get($role->id, collect())
                    ->map(fn (MembershipRole $appointment) => [
                        'membership_id' => $appointment->company_membership_id,
                        'name' => $appointment->membership->user->name,
                        'email' => $appointment->membership->user->email,
                        'is_me' => $appointment->membership->user_id === $request->user()->getKey(),
                        'is_territorial' => $appointment->is_territorial,
                        'status' => $appointment->membership->status,
                    ])
                    ->values(),
            ]),
        ]);
    }

    /**
     * One role's entries, saved by "salva e continua". The submitted list replaces
     * what was there: anyone dropped from it has the appointment revoked.
     */
    public function update(UpdateOrgChartRoleRequest $request, Company $company, OrgRole $orgRole)
    {
        $this->authorize('manageMembers', $company);

        $entries = $request->validated()['entries'];

        DB::transaction(function () use ($request, $company, $orgRole, $entries) {
            $keptMembershipIds = [];

            foreach ($entries as $entry) {
                $membership = ($entry['is_me'] ?? false)
                    ? $this->ownMembership($company, $request->user())
                    : $this->rosterMembership($company, $entry, $request->user());

                MembershipRole::updateOrCreate(
                    [
                        'company_membership_id' => $membership->getKey(),
                        'org_role_id' => $orgRole->getKey(),
                    ],
                    [
                        'is_territorial' => (bool) ($entry['is_territorial'] ?? false),
                        'appointed_at' => now()->toDateString(),
                        // Re-appointing someone previously removed clears the revocation
                        // rather than inserting a second row: the pair is unique.
                        'revoked_at' => null,
                    ],
                );

                $keptMembershipIds[] = $membership->getKey();
            }

            MembershipRole::query()
                ->where('org_role_id', $orgRole->getKey())
                ->whereNull('revoked_at')
                ->whereNotIn('company_membership_id', $keptMembershipIds)
                ->whereIn(
                    'company_membership_id',
                    $company->memberships()->select('id'),
                )
                ->update(['revoked_at' => now()->toDateString()]);
        });

        return $this->show($request, $company->refresh());
    }

    /** The signed-in admin's own membership, for the "sono io" checkbox. */
    private function ownMembership(Company $company, User $user): CompanyMembership
    {
        return $company->memberships()->where('user_id', $user->getKey())->sole();
    }

    /**
     * The person behind one typed row. Their account is reused when the email is
     * already known, so appointing the same person to a second role does not
     * duplicate them, and their existing name is left alone.
     */
    private function rosterMembership(Company $company, array $entry, User $actor): CompanyMembership
    {
        $email = strtolower(trim($entry['email']));

        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first()
            ?? User::create([
                'name' => $entry['name'] ?? null,
                'email' => $email,
                // Nobody may sign in as an account they did not create: this is
                // deliberately unguessable and never shown, so reaching the account
                // means going through a password reset.
                'password' => Str::random(40),
                'type' => UserType::CompanyUser,
                'must_change_password' => true,
            ]);

        $membership = $company->memberships()->firstOrCreate(
            ['user_id' => $user->getKey()],
            [
                'status' => MembershipStatus::Invited,
                'is_admin' => false,
                'invited_by_id' => $actor->getKey(),
            ],
        );

        // Re-listing somebody who had been archived puts them back on the roster,
        // while an already active member keeps their access untouched.
        if ($membership->status === MembershipStatus::Archived) {
            $membership->update(['status' => MembershipStatus::Invited]);
        }

        return $membership;
    }

    /**
     * Active appointments in this company, keyed by role id.
     *
     * @return Collection<int, Collection<int, MembershipRole>>
     */
    private function activeAppointments(Company $company): Collection
    {
        return MembershipRole::query()
            ->whereNull('revoked_at')
            ->whereIn('company_membership_id', $company->memberships()->select('id'))
            ->with('membership.user')
            ->get()
            ->groupBy('org_role_id');
    }
}
