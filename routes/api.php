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
    $query = trim($request->query('q', ''));
    if (strlen($query) < 2) {
        return response()->json(['results' => []]);
    }

    // Split the query into terms (supports "First Last" or multiple parts).
    $terms = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);

    $qb = \App\Models\EventbriteTicket::query();

    if (count($terms) === 1) {
        $t = $terms[0];
        $qb->where(function ($q) use ($t) {
            $q->where('first_name', 'like', "%{$t}%")
              ->orWhere('last_name', 'like', "%{$t}%");
        });
    } else {
        // Require that each term appears in either first_name or last_name.
        // This lets searches like "John Smith" or "Smith John" match correctly,
        // and also handles middle names or multi-word last names.
        foreach ($terms as $t) {
            $qb->where(function ($q) use ($t) {
                $q->where('first_name', 'like', "%{$t}%")
                  ->orWhere('last_name', 'like', "%{$t}%");
            });
        }
    }

    // Fetch more rows than we intend to return so we can collapse duplicate
    // tickets belonging to the same person (same email or same first+last).
    // Include a subquery to fetch the name of a paid pregame registration
    // associated with this ticket (if any). This avoids doing a query per row
    // in PHP and ensures the client receives the value directly.
        // Fetch a larger set of rows and collapse duplicates in PHP by preferring
        // eventbrite_ticket_id, then barcode_id, then id as the unique key.
        $rows = $qb->orderBy('first_name', 'asc')
                ->limit(200)
                ->select([
                        'id', 'first_name', 'last_name', 'email', 'barcode_id', 'eventbrite_ticket_id', 'redeemed_at', 'order_date', 'pregame_id'
                ])
                ->selectRaw("(
                        select p.name
                        from registrations r
                        join pregames p on p.id = r.pregame_id
                        where r.pregame_id = eventbrite_tickets.pregame_id
                            and (
                                r.eventbrite_ticket_id = eventbrite_tickets.id
                                or (
                                        r.eventbrite_order_id IS NOT NULL
                                        and r.eventbrite_order_id = eventbrite_tickets.eventbrite_order_id
                                )
                            )
                            and r.payment_status = 'paid'
                        limit 1
                ) as pregame_name")
                ->get()
                ->toArray();

    $seen = [];
    $results = [];
    foreach ($rows as $r) {
        // Prefer deduplication by ticket id, then barcode, then email/name fallback.
        $ticketId = trim((string)($r['eventbrite_ticket_id'] ?? ''));
        $barcodeVal = trim((string)($r['barcode_id'] ?? ''));

        if ($ticketId !== '') {
            $key = 'ticket:' . $ticketId;
        } elseif ($barcodeVal !== '') {
            $key = 'barcode:' . $barcodeVal;
        } else {
            $email = trim(strtolower($r['email'] ?? ''));
            if ($email !== '') {
                $key = 'email:' . $email;
            } else {
                $fn = trim(strtolower($r['first_name'] ?? ''));
                $ln = trim(strtolower($r['last_name'] ?? ''));
                $key = 'name:' . $fn . '|' . $ln;
            }
        }

        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        // The SQL subquery already returned `pregame_name` when a paid
        // registration exists; keep that value and don't overwrite it here.
        $results[] = $r;
        if (count($results) >= 10) break;
    }

    return response()->json(['results' => $results]);
})->middleware('throttle:60,1');

// Protected routes (JWT required)
Route::middleware('jwt')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});
