<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

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

// Public auth routes (no JWT required)
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

// Search registrants (for check-in page)
Route::get('/search-registrants', function (\Illuminate\Http\Request $request) {
    $query = $request->query('q', '');
    if (strlen($query) < 2) {
        return response()->json(['results' => []]);
    }
    
    $results = \App\Models\EventbriteTicket::query()
        ->where('first_name', 'like', '%' . $query . '%')
        ->orWhere('last_name', 'like', '%' . $query . '%')
        ->orderBy('first_name', 'asc')
        ->limit(10)
        ->get(['first_name', 'last_name', 'email', 'barcode_id', 'redeemed_at', 'order_date'])
        ->toArray();
    
    return response()->json(['results' => $results]);
});

// Protected routes (JWT required)
Route::middleware('jwt')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});
