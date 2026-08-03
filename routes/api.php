<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyMemberController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // Workspaces ("Cambio profilo") and company profile.
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::post('/companies', [CompanyController::class, 'store']);
    Route::get('/companies/{company}', [CompanyController::class, 'show']);
    Route::patch('/companies/{company}', [CompanyController::class, 'update']);

    // Org chart. Memberships and invitations are scoped to their company by implicit binding.
    Route::get('/companies/{company}/members', [CompanyMemberController::class, 'index']);
    Route::post('/companies/{company}/members', [CompanyMemberController::class, 'store']);
    Route::patch('/companies/{company}/members/{membership}', [CompanyMemberController::class, 'update']);
    Route::delete('/companies/{company}/members/{membership}', [CompanyMemberController::class, 'destroy']);

    Route::get('/companies/{company}/invitations', [InvitationController::class, 'index']);
    Route::post('/companies/{company}/invitations', [InvitationController::class, 'store']);
    Route::delete('/companies/{company}/invitations/{invitation}', [InvitationController::class, 'destroy']);

    Route::get('/companies/{company}/subscription', [SubscriptionController::class, 'show']);
    Route::post('/companies/{company}/subscription', [SubscriptionController::class, 'store']);
    Route::delete('/companies/{company}/subscription', [SubscriptionController::class, 'destroy']);

    // Reference data.
    Route::get('/plans', [CatalogController::class, 'plans']);
    Route::get('/org-roles', [CatalogController::class, 'orgRoles']);

    // Token redemption: the invitee must be authenticated.
    Route::post('/invitations/accept', [InvitationController::class, 'accept']);
});

// Per-domain slice routes, appended here to keep the main file readable.
require __DIR__.'/slices/documents.php';
require __DIR__.'/slices/checklists.php';
require __DIR__.'/slices/incidents.php';
require __DIR__.'/slices/support.php';
require __DIR__.'/slices/monitor.php';
