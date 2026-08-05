<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyMemberController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\OrgChartController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

// Tenancy/onboarding slice: workspaces, org chart, invitations, subscriptions.
Route::middleware('auth:sanctum')->group(function () {
    // Workspaces ("Cambio profilo") and company profile.
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::post('/companies', [CompanyController::class, 'store']);
    Route::post('/companies/personal', [CompanyController::class, 'personal']);
    Route::get('/companies/{company}', [CompanyController::class, 'show']);
    Route::patch('/companies/{company}', [CompanyController::class, 'update']);

    // Org chart. scopeBindings() resolves {membership}/{invitation} through the parent
    // company, so an id belonging to another tenant is a 404 instead of a hijack.
    Route::scopeBindings()->group(function () {
        Route::get('/companies/{company}/members', [CompanyMemberController::class, 'index']);
        Route::post('/companies/{company}/members', [CompanyMemberController::class, 'store']);
        Route::patch('/companies/{company}/members/{membership}', [CompanyMemberController::class, 'update']);
        Route::delete('/companies/{company}/members/{membership}', [CompanyMemberController::class, 'destroy']);

        Route::get('/companies/{company}/invitations', [InvitationController::class, 'index']);
        Route::post('/companies/{company}/invitations', [InvitationController::class, 'store']);
        Route::delete('/companies/{company}/invitations/{invitation}', [InvitationController::class, 'destroy']);
    });

    // "Imposta Organigramma". withoutScopedBindings() because a custom key on a nested
    // parameter otherwise makes Laravel resolve {orgRole:code} through the parent:
    // org roles are global reference data, so Company has no relation to scope through.
    Route::get('/companies/{company}/org-chart', [OrgChartController::class, 'show']);
    // The whole chart, from "conferma e concludi".
    Route::post('/companies/{company}/org-chart', [OrgChartController::class, 'store']);
    // A single role, for editing the chart later.
    Route::put('/companies/{company}/org-chart/{orgRole:code}', [OrgChartController::class, 'update'])
        ->withoutScopedBindings();

    Route::get('/companies/{company}/subscription', [SubscriptionController::class, 'show']);
    Route::post('/companies/{company}/subscription', [SubscriptionController::class, 'store']);
    Route::delete('/companies/{company}/subscription', [SubscriptionController::class, 'destroy']);

    // Token redemption: the invitee must be authenticated. Throttled, because the
    // token is the only secret standing between a stranger and a company membership.
    Route::post('/invitations/accept', [InvitationController::class, 'accept'])
        ->middleware('throttle:invitations');
});
