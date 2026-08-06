<?php

use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

// Stripe calls this one, so it is unauthenticated by necessity: the signature
// header is what authenticates it (see StripeWebhookController).
Route::post('/stripe/webhook', StripeWebhookController::class);
