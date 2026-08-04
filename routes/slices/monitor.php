<?php

use App\Http\Controllers\MonitorController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Monitora attività board.
    Route::get('/companies/{company}/activities', [MonitorController::class, 'index']);
    Route::patch('/activities/{activity}', [MonitorController::class, 'update']);

    // Audit trail.
    Route::get('/companies/{company}/audit-log', [MonitorController::class, 'auditLog']);

    // Billing.
    Route::get('/subscriptions/{subscription}/payments', [MonitorController::class, 'payments']);
});
