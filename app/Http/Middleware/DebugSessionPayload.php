<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DebugSessionPayload
{
    public function handle(Request $request, Closure $next): Response
    {
        // Before processing
        \Log::info('MIDDLEWARE_START', [
            'session_id' => session()->getId(),
            'session_data_count' => count(session()->all()),
        ]);

        $response = $next($request);

        // After processing - check what's in the session
        \Log::info('MIDDLEWARE_END', [
            'session_id' => session()->getId(),
            'session_data_all' => session()->all(),
            'session_data_count' => count(session()->all()),
        ]);

        return $response;
    }
}
