<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\JwtService;
use App\Models\User;

class ValidateJwtToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->getTokenFromRequest($request);

        if (!$token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = JwtService::verifyToken($token);

        if (!$payload) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        // Load user from database
        $user = User::find($payload['sub']);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 401);
        }

        // Set user on request for later use
        $request->setUserResolver(fn() => $user);
        auth()->setUser($user);

        return $next($request);
    }

    /**
     * Extract token from Authorization header
     */
    private function getTokenFromRequest(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return substr($header, 7);
    }
}
