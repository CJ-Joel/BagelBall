<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhook Routes
|--------------------------------------------------------------------------
|
| These routes are for external webhooks and have minimal middleware.
| No CSRF protection, no sessions, no cookies.
|
*/

Route::post('/webhooks/eventbrite', [\App\Http\Controllers\EventbriteWebhookController::class, 'handle']);
Route::post('/webhooks/stripe', [\App\Http\Controllers\StripeWebhookController::class, 'handle']);
