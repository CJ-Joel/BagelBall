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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cookie;

// Session test - try manipulating response before sending
Route::get('/debug/session', function () {
    session()->put('visit_count', (session()->get('visit_count', 0) + 1));
    session()->save();
    
    $sessionId = session()->getId();
    $visitCount = session()->get('visit_count');
    
    $text = "Session ID: $sessionId\nVisit Count: $visitCount\n";
    $response = response($text, 200, ['Content-Type' => 'text/plain']);
    
    // Add a macro test
    $response->header('X-Custom-Header', 'test-value');
    
    return $response;
})->middleware('web');
