
<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

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

// Check if session cookie is being sent
Route::get('/debug/cookie-check', function() {
    session()->put('test_data', 'cookie_check_test_' . time());
    session()->save();
    
    $sessionId = session()->getId();
    $cookieName = config('session.cookie');
    
    \Log::info('COOKIE CHECK', [
        'session_id' => $sessionId,
        'cookie_name' => $cookieName,
        'cookie_config' => [
            'path' => config('session.path'),
            'domain' => config('session.domain'),
            'secure' => config('session.secure'),
            'http_only' => config('session.http_only'),
            'same_site' => config('session.same_site'),
        ],
    ]);
    
    // Just return text so we don't hit Inertia middleware
    return "Session ID: " . $sessionId . "\n\nCookie Name: " . $cookieName . "\n\nCheck DevTools for the laravel-session cookie.";
})->middleware('web');

// Simple session write test
Route::get('/debug/session-write-test', function() {
    $testData = [
        'simple_string' => 'hello',
        'number' => 42,
        'array' => ['a', 'b', 'c'],
    ];
    
    // Try different ways to write
    session()->flush();
    foreach ($testData as $key => $value) {
        session()->put($key, $value);
    }
    session()->save();
    
    // Read back immediately
    $readBack = [];
    foreach ($testData as $key => $value) {
        $readBack[$key] = session()->get($key);
    }
    
    // Check database
    $sessionId = session()->getId();
    $dbSession = DB::table('sessions')->where('id', $sessionId)->first();
    
    \Log::info('SESSION WRITE TEST', [
        'session_id' => $sessionId,
        'written_data' => $testData,
        'read_back' => $readBack,
        'db_exists' => $dbSession ? 'YES' : 'NO',
        'db_payload_length' => $dbSession ? strlen($dbSession->payload) : 0,
        'db_payload_first_100_chars' => $dbSession ? substr($dbSession->payload, 0, 100) : 'N/A',
    ]);
    
    return response()->json([
        'session_id' => $sessionId,
        'written' => $testData,
        'read_back' => $readBack,
        'match' => $testData === $readBack ? 'YES ✅' : 'NO ❌',
        'db_has_data' => $dbSession && strlen($dbSession->payload) > 10 ? 'YES ✅' : 'NO ❌',
    ]);
})->middleware('web');

// Debug routes for session diagnostics
Route::get('/debug/session', function() {
    $sessionId = session()->getId();
    $visitCount = session()->get('visit_count', 0);
    $visitCount++;
    session()->put('visit_count', $visitCount);
    session()->put('timestamp', now()->toDateTimeString());
    session()->save();
    
    // Check if the session was actually written to the database
    $dbSession = DB::table('sessions')->where('id', $sessionId)->first();
    
    $payload = null;
    if ($dbSession) {
        $payload = json_decode($dbSession->payload, true);
    }
    
    \Log::info('DEBUG SESSION ENDPOINT', [
        'session_id' => $sessionId,
        'visit_count_in_memory' => $visitCount,
        'timestamp' => now()->toDateTimeString(),
        'db_session_exists' => $dbSession ? 'YES' : 'NO',
        'db_payload' => $payload ? 'HAS DATA' : 'EMPTY/NULL',
        'payload_keys' => $payload ? array_keys($payload) : [],
    ]);
    
    return response()->json([
        'status' => $visitCount > 1 ? 'WORKING ✅' : 'NOT_PERSISTING ❌',
        'visit_count' => $visitCount,
        'session_id' => $sessionId,
        'db_check' => [
            'session_exists_in_db' => $dbSession ? 'YES' : 'NO ⚠️',
            'has_data' => $payload ? 'YES' : 'NO ⚠️',
            'payload_keys' => $payload ? array_keys($payload) : [],
        ],
        'config' => [
            'session_driver' => config('session.driver'),
            'session_lifetime' => config('session.lifetime'),
            'cookie_name' => config('session.cookie'),
            'cookie_secure' => config('session.secure'),
            'session_regenerate' => config('session.regenerate'),
        ],
        'instructions' => [
            'Refresh 5+ times',
            'If visit_count increments: Sessions are working ✅',
            'If visit_count stays at 1 BUT session_exists_in_db=YES: Data not being read back',
            'If visit_count stays at 1 AND session_exists_in_db=NO: Data not being written',
        ],
    ], 200, [
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ]);
})->middleware('web');

// Test session debug - MINIMAL middleware
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
})->middleware(['StartSession']);
