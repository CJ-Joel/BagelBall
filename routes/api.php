<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Eventbrite webhook endpoint (accessible at /api/webhooks/eventbrite)
Route::post('/webhooks/eventbrite', [\App\Http\Controllers\EventbriteWebhookController::class, 'handle']);
