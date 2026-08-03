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
        $user = $request->user();

        if (! $user->isOperator()) {
            // Operators hold no membership; workers get their personal workspace lazily.
            Company::personalFor($user);
        }

        return CompanyResource::collection(
            $user->activeCompanies()
                ->with(['brandingSetting', 'entitlingSubscription.plan'])
                ->get()
        );
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
