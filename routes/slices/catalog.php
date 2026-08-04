<?php

use App\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;

// Read-only reference data shared by onboarding and subscription flows.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/plans', [CatalogController::class, 'plans']);
    Route::get('/org-roles', [CatalogController::class, 'orgRoles']);
});
