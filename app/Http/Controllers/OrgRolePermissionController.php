<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrgRolePermissionRequest;
use App\Models\Company;
use App\Models\OrgRole;
use App\Models\OrgRolePermission;
use Illuminate\Http\Request;

/**
 * "Gestisci autorizzazioni" (prototype 086): one section per D.Lgs 81/08 role,
 * two switches each. CompanyPolicy::viewMembers and IncidentReportPolicy::viewAny
 * read them back through User::orgPermissionsIn().
 */
class OrgRolePermissionController extends Controller
{
    public function index(Request $request, Company $company)
    {
        $this->authorize('viewMembers', $company);

        return response()->json(['roles' => $this->rolesPayload($company)]);
    }

    public function update(UpdateOrgRolePermissionRequest $request, Company $company, OrgRole $orgRole)
    {
        $this->authorize('manageMembers', $company);

        OrgRolePermission::updateOrCreate(
            [
                'company_id' => $company->getKey(),
                'org_role_id' => $orgRole->getKey(),
            ],
            $request->validated(),
        );

        return $this->index($request, $company);
    }

    /**
     * Every role the employer may grant to, in seeder order. `datore_lavoro` is
     * left out: it is the registrant doing the granting, and it always sees
     * everything.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rolesPayload(Company $company): array
    {
        $permissions = OrgRolePermission::query()
            ->where('company_id', $company->getKey())
            ->get()
            ->keyBy('org_role_id');

        return OrgRole::query()
            ->where('code', '!=', 'datore_lavoro')
            ->orderBy('position')
            ->get()
            ->map(fn (OrgRole $role) => [
                'id' => $role->id,
                'code' => $role->code,
                'label' => $role->label,
                // No row means "never touched", which is granted: same default as
                // the column.
                'can_view_org_chart' => $permissions->get($role->id)?->can_view_org_chart ?? true,
                'can_view_incidents' => $permissions->get($role->id)?->can_view_incidents ?? true,
            ])
            ->values()
            ->all();
    }
}
