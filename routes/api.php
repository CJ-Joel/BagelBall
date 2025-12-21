<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\DB;

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
            $q->where('eventbrite_tickets.first_name', 'like', "%{$t}%")
              ->orWhere('eventbrite_tickets.last_name', 'like', "%{$t}%");
        });
    } else {
        // Require that each term appears in either first_name or last_name.
        // This lets searches like "John Smith" or "Smith John" match correctly,
        // and also handles middle names or multi-word last names.
        foreach ($terms as $t) {
            $qb->where(function ($q) use ($t) {
                $q->where('eventbrite_tickets.first_name', 'like', "%{$t}%")
                  ->orWhere('eventbrite_tickets.last_name', 'like', "%{$t}%");
            });
        }
    }

    // Collapse duplicates in SQL by computing a dedupe key and grouping by it.
    // We prefer rows that have an `eventbrite_ticket_id`, then `barcode_id`,
    // then fallback to email or name. Use ANY_VALUE() to pick representative
    // values for non-grouped columns and keep the subquery for `pregame_name`.
    $dedupeExpr = "(
        CASE
            WHEN eventbrite_tickets.eventbrite_ticket_id IS NOT NULL AND eventbrite_tickets.eventbrite_ticket_id != '' THEN CONCAT('ticket:', eventbrite_tickets.eventbrite_ticket_id)
            WHEN eventbrite_tickets.barcode_id IS NOT NULL AND eventbrite_tickets.barcode_id != '' THEN CONCAT('barcode:', eventbrite_tickets.barcode_id)
            WHEN eventbrite_tickets.email IS NOT NULL AND eventbrite_tickets.email != '' THEN CONCAT('email:', LOWER(eventbrite_tickets.email))
            ELSE CONCAT('name:', LOWER(CONCAT(eventbrite_tickets.first_name, '|', eventbrite_tickets.last_name)))
        END
    )";

        // Use a derived-table approach (inner aggregated subquery) and compute
        // the dedupe key in the outer query. This mirrors the corrected SQL
        // provided and avoids ONLY_FULL_GROUP_BY issues while keeping a single
        // efficient database round-trip.

        // Build WHERE clause with bindings for the search terms.
        $whereParts = [];
        $bindings = [];
        foreach ($terms as $t) {
                $whereParts[] = "(et.first_name LIKE ? OR et.last_name LIKE ?)";
                $bindings[] = "%{$t}%";
                $bindings[] = "%{$t}%";
        }
        $whereSql = implode(' AND ', $whereParts);
        if ($whereSql === '') {
                $whereSql = '1';
        }

        $sql = "SELECT
    t.*,
    CASE
        WHEN t.eventbrite_ticket_id IS NOT NULL AND t.eventbrite_ticket_id <> ''
            THEN CONCAT('ticket:', t.eventbrite_ticket_id)
        WHEN t.barcode_id IS NOT NULL AND t.barcode_id <> ''
            THEN CONCAT('barcode:', t.barcode_id)
        WHEN t.email IS NOT NULL AND t.email <> ''
            THEN CONCAT('email:', LOWER(t.email))
        ELSE CONCAT('name:', LOWER(CONCAT(t.first_name, '|', t.last_name)))
    END AS dedupe_key
FROM (
    SELECT
        MIN(et.first_name) AS first_name,
        MIN(et.last_name)  AS last_name,
        MIN(et.email)      AS email,
        MIN(et.barcode_id) AS barcode_id,
        MAX(et.eventbrite_ticket_id) AS eventbrite_ticket_id,
        MIN(et.redeemed_at) AS redeemed_at,
        MIN(et.order_date)  AS order_date,
        MIN(p.name)         AS pregame_name
    FROM eventbrite_tickets et
    LEFT JOIN registrations r
        ON r.pregame_id = et.pregame_id
     AND (
                r.eventbrite_ticket_id = et.id
                OR (r.eventbrite_order_id IS NOT NULL AND r.eventbrite_order_id = et.eventbrite_order_id)
             )
     AND r.payment_status = 'paid'
    LEFT JOIN pregames p
        ON p.id = r.pregame_id
    WHERE {$whereSql}
    GROUP BY
        COALESCE(NULLIF(et.eventbrite_ticket_id, ''),
                         NULLIF(et.barcode_id, ''),
                         NULLIF(LOWER(et.email), ''),
                         LOWER(CONCAT(et.first_name,'|',et.last_name)))
) t
ORDER BY t.first_name,t.last_name ASC
LIMIT 10";

        $rows = DB::select($sql, $bindings);

    return response()->json(['results' => $rows]);
})->middleware('throttle:60,1');

// Protected routes (JWT required)
Route::middleware('jwt')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});
