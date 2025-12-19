
<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Landing page
Route::get('/', [\App\Http\Controllers\LandingPageController::class, 'index'])->name('landing');

// Eventbrite test/debug routes (require web middleware for session/cookies)
Route::get('/eventbrite/test-log', [\App\Http\Controllers\EventbriteWebhookController::class, 'testLog'])->name('eventbrite.test.log');
Route::get('/eventbrite/webhook-log', [\App\Http\Controllers\EventbriteWebhookController::class, 'showLog'])->name('eventbrite.webhook.log');
Route::get('/eventbrite/test-webhook', [\App\Http\Controllers\EventbriteWebhookController::class, 'sendTestWebhook'])->name('eventbrite.test.webhook');
Route::get('/eventbrite/order-lookup', [\App\Http\Controllers\EventbriteWebhookController::class, 'orderLookup'])->name('eventbrite.order.lookup');
Route::get('/eventbrite/sync', [\App\Http\Controllers\EventbriteWebhookController::class, 'syncOrders'])->name('eventbrite.sync');

// PreGame public routes
Route::get('/pregames', [\App\Http\Controllers\PreGameController::class, 'index'])->name('pregames.index');
Route::get('/pregames/{pregame}', [\App\Http\Controllers\PreGameController::class, 'show'])->name('pregames.show');
Route::get('/pregames/{pregame}/signup', [\App\Http\Controllers\RegistrationController::class, 'create'])->name('pregames.signup');
Route::post('/pregames/{pregame}/signup', [\App\Http\Controllers\RegistrationController::class, 'store'])->name('pregames.signup.submit');
Route::post('/pregames/validate-order', [\App\Http\Controllers\RegistrationController::class, 'validateOrderId'])->name('pregames.validate.order');

Route::get('/checkout/success', [\App\Http\Controllers\CheckoutController::class, 'success'])->name('checkout.success');

// Admin auth (password form + token)
Route::get('/admin/login', [\App\Http\Controllers\AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [\App\Http\Controllers\AdminAuthController::class, 'login'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])->name('admin.login.post');

// Admin page (token in querystring)
Route::get('/admin', [\App\Http\Controllers\AdminController::class, 'ticketsSoldByDay'])->name('admin.tickets-sold-by-day');

// Check-in (token or session-based middleware handles access)

Route::get('/checkin', [\App\Http\Controllers\CheckinController::class, 'index'])->middleware(\App\Http\Middleware\CheckinToken::class)->name('checkin.index');
Route::post('/checkin/scan', [\App\Http\Controllers\CheckinController::class, 'scan'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])->middleware(\App\Http\Middleware\CheckinToken::class)->name('checkin.scan');
// Reverse a checkin (unredeem a ticket)
Route::post('/checkin/reverse', [\App\Http\Controllers\CheckinController::class, 'reverse'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])->middleware(\App\Http\Middleware\CheckinToken::class)->name('checkin.reverse');
