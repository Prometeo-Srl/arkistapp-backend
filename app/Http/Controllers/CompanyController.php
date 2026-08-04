<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Enums\WorkspaceKind;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * Workspaces of the current user: their personal archive plus the active
     * business companies they follow ("Cambio profilo").
     */
    public function index(Request $request)
    {
        return CompanyResource::collection(
            $request->user()->activeCompanies()
                ->with(['brandingSetting', 'entitlingSubscription.plan'])
                ->get()
        );
    }

    /**
     * The personal workspace of an unassociated worker, created on demand.
     *
     * It used to be created as a side effect of GET /companies, which made a read
     * request write and handed a personal workspace to every employee who only ever
     * belonged to a company. Idempotent: repeated calls return the same workspace.
     */
    public function personal(Request $request)
    {
        $user = $request->user();

        abort_if($user->isOperator(), 422, 'A super admin holds no personal workspace.');

        // Query the relation rather than the property: a loaded relation can be stale,
        // and the status code has to reflect the database, not a cached value.
        $existed = $user->personalWorkspace()->exists();
        $workspace = Company::personalFor($user);

        return (new CompanyResource($workspace->load(['brandingSetting', 'entitlingSubscription.plan'])))
            ->response()
            ->setStatusCode($existed ? 200 : 201);
    }

    public function store(StoreCompanyRequest $request)
    {
        $user = $request->user();

        $company = Company::create([
            ...$request->validated(),
            'kind' => WorkspaceKind::Business,
            'owner_user_id' => $user->isOperator() ? null : $user->getKey(),
            'created_by_operator_id' => $user->isOperator() ? $user->getKey() : null,
        ]);

        if (! $user->isOperator()) {
            $company->memberships()->create([
                'user_id' => $user->getKey(),
                'status' => MembershipStatus::Active,
                'is_admin' => true,
            ]);
        }

        return (new CompanyResource($company->load(['brandingSetting', 'entitlingSubscription.plan'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Company $company)
    {
        $this->authorize('view', $company);

        return new CompanyResource($company->load(['brandingSetting', 'entitlingSubscription.plan']));
    }

    public function update(UpdateCompanyRequest $request, Company $company)
    {
        $this->authorize('update', $company);

        $company->update($request->validated());

        return new CompanyResource($company->fresh());
    }
}
