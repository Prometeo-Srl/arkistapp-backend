<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrgRoleResource;
use App\Http\Resources\PlanResource;
use App\Models\OrgRole;
use App\Models\Plan;

/** Read-only reference data shared by the onboarding and subscription flows. */
class CatalogController extends Controller
{
    public function plans()
    {
        return PlanResource::collection(
            Plan::query()->where('is_active', true)->orderBy('price_cents')->get()
        );
    }

    public function orgRoles()
    {
        return OrgRoleResource::collection(
            OrgRole::query()->orderBy('position')->get()
        );
    }
}
