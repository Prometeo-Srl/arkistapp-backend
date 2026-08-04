<?php

use App\Http\Controllers\PrometeoContactController;
use App\Http\Controllers\SupportThreadController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/companies/{company}/support-threads', [SupportThreadController::class, 'index']);
    Route::post('/companies/{company}/support-threads', [SupportThreadController::class, 'store']);

    Route::get('/threads/{thread}', [SupportThreadController::class, 'show']);
    Route::post('/threads/{thread}/messages', [SupportThreadController::class, 'storeMessage']);
    Route::post('/threads/{thread}/read', [SupportThreadController::class, 'markRead']);
    Route::patch('/threads/{thread}', [SupportThreadController::class, 'close']);

    Route::get('/prometeo-contacts', [PrometeoContactController::class, 'index']);

    // Operator-only queue: open threads across every company.
    Route::get('/operator/support-threads', [SupportThreadController::class, 'operatorIndex']);
});
