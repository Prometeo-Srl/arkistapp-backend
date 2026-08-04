<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBrandingRequest;
use App\Http\Resources\BrandingSettingResource;
use App\Models\BrandingSetting;
use App\Models\Company;
use Illuminate\Http\Request;

class BrandingController extends Controller
{
    public function show(Request $request, Company $company)
    {
        $this->authorize('view', [BrandingSetting::class, $company]);

        $setting = $company->brandingSetting;

        return $setting
            ? new BrandingSettingResource($setting)
            : response()->json(['data' => null]);
    }

    public function update(UpdateBrandingRequest $request, Company $company)
    {
        $this->authorize('update', [BrandingSetting::class, $company]);

        $setting = $company->brandingSetting()->updateOrCreate([], $request->validated());

        return (new BrandingSettingResource($setting))->response()->setStatusCode(200);
    }
}
