<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::patch('/me', [AuthController::class, 'update']);
        // "modifica email" (080): two steps, because the new address is only
        // written once a code mailed to it comes back. Throttled — the first
        // sends mail to an address the caller types, the second redeems a
        // six-digit secret.
        Route::post('/me/email', [AuthController::class, 'requestEmailChange'])
            ->middleware('throttle:email-change');
        Route::post('/me/email/verify', [AuthController::class, 'verifyEmailChange'])
            ->middleware('throttle:email-change');
        // Profile picture (078): multipart in, the bytes back out inline. POST
        // rather than PATCH because PHP only parses a multipart body on POST.
        Route::post('/me/avatar', [AuthController::class, 'storeAvatar']);
        Route::get('/me/avatar', [AuthController::class, 'avatar']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::delete('/me', [AuthController::class, 'destroy']);
    });
});
