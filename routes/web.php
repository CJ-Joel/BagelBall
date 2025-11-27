
<?php

use Illuminate\Support\Facades\Route;

use Inertia\Inertia;

// Eventbrite test/debug routes (require web middleware for session/cookies)
Route::get('/eventbrite/test-log', [\App\Http\Controllers\EventbriteWebhookController::class, 'testLog'])->name('eventbrite.test.log');
Route::get('/eventbrite/webhook-log', [\App\Http\Controllers\EventbriteWebhookController::class, 'showLog'])->name('eventbrite.webhook.log');
Route::get('/eventbrite/test-webhook', [\App\Http\Controllers\EventbriteWebhookController::class, 'sendTestWebhook'])->name('eventbrite.test.webhook');
Route::get('/eventbrite/order-lookup', [\App\Http\Controllers\EventbriteWebhookController::class, 'orderLookup'])->name('eventbrite.order.lookup');
Route::get('/eventbrite/sync', [\App\Http\Controllers\EventbriteWebhookController::class, 'syncOrders'])->name('eventbrite.sync');

Route::get('/', function () {
    return view('welcome');
})->name('home');

// PreGame public routes
Route::get('/pregames', [\App\Http\Controllers\PreGameController::class, 'index'])->name('pregames.index');
Route::get('/pregames/{pregame}', [\App\Http\Controllers\PreGameController::class, 'show'])->name('pregames.show');
Route::get('/pregames/{pregame}/signup', [\App\Http\Controllers\RegistrationController::class, 'create'])->name('pregames.signup');
Route::post('/pregames/{pregame}/signup', [\App\Http\Controllers\RegistrationController::class, 'store'])->name('pregames.signup.submit');
Route::post('/pregames/validate-order', [\App\Http\Controllers\RegistrationController::class, 'validateOrderId'])->name('pregames.validate.order');

Route::get('/checkout/success', [\App\Http\Controllers\CheckoutController::class, 'success'])->name('checkout.success');

// Debug route to test session
Route::get('/test-session-debug', function() {
    session()->put('test_key', 'test_value');
    session()->save();
    
    return response()->json([
        'session_id' => session()->getId(),
        'session_driver' => config('session.driver'),
        'session_saved' => session()->has('test_key'),
        'cookie_name' => config('session.cookie'),
        'cookie_domain' => config('session.domain'),
        'cookie_path' => config('session.path'),
        'cookie_secure' => config('session.secure'),
        'cookie_same_site' => config('session.same_site'),
    ]);
});
