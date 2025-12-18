<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckinToken
{
    /**
     * Allow access when either:
     * - session has checkin_auth
     * - request includes X-CHECKIN-TOKEN header matching CHECKIN_TOKEN
     * - request includes checkin_token query param matching CHECKIN_TOKEN
     * - Authorization: Bearer <token> matches CHECKIN_TOKEN
     */
    public function handle(Request $request, Closure $next)
    {
        // Allow existing session-based auth
        if ($request->session()->get('checkin_auth', false)) {
            return $next($request);
        }

        $expected = env('CHECKIN_TOKEN', env('CHECKIN_PASSWORD', null));
        if (empty($expected)) {
            return response()->json(['error' => 'checkin token not configured'], 503);
        }

        $header = $request->header('X-CHECKIN-TOKEN');
        $query = $request->query('checkin_token');
        $bearer = $request->bearerToken();

        // Use hash_equals to avoid timing attacks when comparing secrets
        if ($header && hash_equals((string)$expected, (string)$header)) {
            return $next($request);
        }

        if ($query && hash_equals((string)$expected, (string)$query)) {
            return $next($request);
        }

        if ($bearer && hash_equals((string)$expected, (string)$bearer)) {
            return $next($request);
        }

        return response()->json(['error' => 'unauthorized'], 401);
    }
}
