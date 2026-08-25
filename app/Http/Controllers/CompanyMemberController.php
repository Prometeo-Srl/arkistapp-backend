<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Resources\MembershipResource;
use App\Models\Company;
use App\Models\CompanyMembership;
use Illuminate\Http\Request;

class CompanyMemberController extends Controller
{
    public function index(Request $request, Company $company)
    {
        $this->authorize('viewMembers', $company);

        return MembershipResource::collection(
            $company->memberships()
                // Guests let in by a share are not on the org chart: with no appointment
                // to their name the directory would render them as "lavoratore".
                ->onOrgChart()
                ->with(['user', 'orgRoles'])
                ->latest()
                ->get()
        );
    }

    /** Direct attachment of an existing account; email-first onboarding goes through invitations. */
    public function store(StoreMemberRequest $request, Company $company)
    {
        $this->authorize('manageMembers', $company);

        $membership = $company->memberships()->create([
            'user_id' => $request->validated('user_id'),
            'status' => MembershipStatus::Active,
            'is_admin' => $request->boolean('is_admin'),
            'department' => $request->validated('department'),
            'employee_code' => $request->validated('employee_code'),
            'hired_at' => $request->validated('hired_at'),
        ]);

        $this->syncOrgRoles($membership, $request->validated('org_role_ids'));

        return (new MembershipResource($membership->load(['user', 'orgRoles'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateMemberRequest $request, Company $company, CompanyMembership $membership)
    {
        $this->authorize('manageMembers', $company);

        $data = $request->safe()->except(['org_role_ids']);
        if ($request->has('is_admin')) {
            $data['is_admin'] = $request->boolean('is_admin');
        }
        $membership->update($data);

        $this->syncOrgRoles($membership, $request->validated('org_role_ids'));

        return new MembershipResource($membership->load(['user', 'orgRoles']));
    }

    /** Removal is an archive: the record stays as history and the seat is freed. */
    public function destroy(Request $request, Company $company, CompanyMembership $membership)
    {
        $this->authorize('manageMembers', $company);

        $membership->update(['status' => MembershipStatus::Archived]);

        return response()->noContent();
    }

    /**
     * @param  array<int, int>|null  $orgRoleIds
     */
    private function syncOrgRoles(CompanyMembership $membership, ?array $orgRoleIds): void
    {
        if ($orgRoleIds === null) {
            return;
        }

        $membership->orgRoles()->sync($orgRoleIds, ['appointed_at' => now()->toDateString()]);
    }
}
