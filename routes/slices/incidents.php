<?php

use App\Http\Controllers\IncidentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/companies/{company}/incidents', [IncidentController::class, 'index']);
    Route::post('/companies/{company}/incidents', [IncidentController::class, 'store']);

    Route::get('/incidents/{incident}', [IncidentController::class, 'show']);
    Route::patch('/incidents/{incident}', [IncidentController::class, 'update']);
    Route::delete('/incidents/{incident}', [IncidentController::class, 'destroy']);
    Route::post('/incidents/{incident}/attachments', [IncidentController::class, 'storeAttachment']);
});
