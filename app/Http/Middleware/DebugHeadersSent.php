<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DebugHeadersSent
{
    public function handle(Request $request, Closure $next)
    {
        \Log::info('BEFORE_NEXT - Headers sent: ' . (headers_sent() ? 'YES' : 'NO'));
        $response = $next($request);
        \Log::info('AFTER_NEXT - Headers sent: ' . (headers_sent() ? 'YES' : 'NO'));
        return $response;
    }
}
