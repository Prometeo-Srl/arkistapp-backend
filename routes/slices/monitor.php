<?php

use App\Http\Controllers\MonitorController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // "Monitora attività" (059/063) and the home counters (078), derived from the
    // duties on files and checklist assignments.
    Route::get('/companies/{company}/monitor', [MonitorController::class, 'board']);
    Route::get('/companies/{company}/monitor/{subjectType}/{subjectId}', [MonitorController::class, 'subject'])
        ->whereIn('subjectType', ['file', 'checklist'])
        ->whereNumber('subjectId');
    Route::get('/companies/{company}/summary', [MonitorController::class, 'summary']);

    // The `activities` rows behind a materialized board. Nothing writes them yet.
    Route::get('/companies/{company}/activities', [MonitorController::class, 'index']);
    Route::patch('/activities/{activity}', [MonitorController::class, 'update']);

    // Audit trail.
    Route::get('/companies/{company}/audit-log', [MonitorController::class, 'auditLog']);

    // Billing.
    Route::get('/subscriptions/{subscription}/payments', [MonitorController::class, 'payments']);
});
