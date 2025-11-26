Route::get('/eventbrite/webhook-log', [\App\Http\Controllers\EventbriteWebhookController::class, 'showLog'])->name('eventbrite.webhook.log');
<?php
// Eventbrite webhook endpoint
Route::post('/webhooks/eventbrite', [\App\Http\Controllers\EventbriteWebhookController::class, 'handle'])->name('webhooks.eventbrite');


use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// PreGame public routes
Route::get('/pregames', [\App\Http\Controllers\PreGameController::class, 'index'])->name('pregames.index');
Route::get('/pregames/{pregame}', [\App\Http\Controllers\PreGameController::class, 'show'])->name('pregames.show');
Route::get('/pregames/{pregame}/signup', [\App\Http\Controllers\RegistrationController::class, 'create'])->name('pregames.signup');
Route::post('/pregames/{pregame}/signup', [\App\Http\Controllers\RegistrationController::class, 'store'])->name('pregames.signup.submit');
Route::get('/checkout/success', [\App\Http\Controllers\CheckoutController::class, 'success'])->name('checkout.success');
Route::post('/webhooks/stripe', [\App\Http\Controllers\StripeWebhookController::class, 'handle'])->name('webhooks.stripe');
