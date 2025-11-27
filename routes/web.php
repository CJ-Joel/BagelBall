
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
    
    // Get current visit count BEFORE incrementing
    $currentVisitCount = session()->get('visit_count', 0);
    $newVisitCount = $currentVisitCount + 1;
    
    // Write new value
    session()->put('visit_count', $newVisitCount);
    session()->put('timestamp', now()->toDateTimeString());
    session()->put('_test_marker', 'marker_' . time());
    
    \Log::info('SESSION WRITE - Before save', [
        'session_id' => $sessionId,
        'current_visit_count_before_put' => $currentVisitCount,
        'new_visit_count_after_put' => session()->get('visit_count'),
    ]);
    
    session()->save();
    
    \Log::info('SESSION WRITE - After save', [
        'session_id' => $sessionId,
        'visit_count_in_memory' => session()->get('visit_count'),
    ]);
    
    // Check database immediately after save
    $dbSession = DB::table('sessions')->where('id', $sessionId)->first();
    
    $dbData = null;
    if ($dbSession) {
        \Log::info('DB SESSION RAW', [
            'id' => $dbSession->id,
            'user_id' => $dbSession->user_id ?? null,
            'payload_length' => strlen($dbSession->payload),
            'payload_is_null' => $dbSession->payload === null,
            'payload_is_empty_string' => $dbSession->payload === '',
            'payload_first_50_chars' => substr($dbSession->payload, 0, 50),
            'last_activity' => $dbSession->last_activity,
        ]);
        
        try {
            $decoded = base64_decode($dbSession->payload);
            $dbData = unserialize($decoded);
        } catch (\Exception $e) {
            \Log::error('Failed to decode session payload', ['error' => $e->getMessage()]);
        }
    }
    
    // Now read it back in a fresh way
    $readBackVisitCount = session()->get('visit_count');
    
    \Log::info('SESSION READ - After refresh', [
        'visit_count_read_back' => $readBackVisitCount,
    ]);
    
    return response()->json([
        'status' => $readBackVisitCount > 1 ? 'WORKING ✅' : 'NOT_PERSISTING ❌',
        'visit_count' => $readBackVisitCount,
        'session_id' => $sessionId,
        'write_info' => [
            'visit_count_before' => $currentVisitCount,
            'visit_count_written' => $newVisitCount,
            'visit_count_read_back' => $readBackVisitCount,
        ],
        'db_check' => [
            'session_exists' => $dbSession ? 'YES' : 'NO',
            'payload_is_empty' => !$dbSession || !$dbSession->payload ? 'YES' : 'NO',
            'payload_length' => $dbSession ? strlen($dbSession->payload) : 0,
            'payload_preview' => $dbSession ? substr($dbSession->payload, 0, 50) : 'N/A',
            'unserialized_keys' => $dbData ? array_keys($dbData) : [],
            'visit_count_in_db' => $dbData && isset($dbData['visit_count']) ? $dbData['visit_count'] : 'NOT_FOUND',
        ],
        'config' => [
            'session_driver' => config('session.driver'),
            'session_lifetime' => config('session.lifetime'),
            'cookie_name' => config('session.cookie'),
            'session_encrypt' => config('session.encrypt'),
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
