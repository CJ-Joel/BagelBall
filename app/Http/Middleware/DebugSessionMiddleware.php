<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DebugSessionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Check if session cookie exists in response
        $cookies = $response->headers->getCookies();
        $sessionCookieFound = false;
        
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === config('session.cookie')) {
                $sessionCookieFound = true;
                \Log::info('SESSION COOKIE IN RESPONSE', [
                    'cookie_name' => $cookie->getName(),
                    'cookie_value' => $cookie->getValue(),
                    'cookie_path' => $cookie->getPath(),
                    'cookie_domain' => $cookie->getDomain(),
                    'cookie_secure' => $cookie->isSecure(),
                    'cookie_http_only' => $cookie->isHttpOnly(),
                    'cookie_same_site' => $cookie->getSameSite(),
                ]);
                break;
            }
        }
        
        if (!$sessionCookieFound) {
            \Log::warning('SESSION COOKIE NOT FOUND IN RESPONSE', [
                'session_id' => session()->getId(),
                'session_cookie_name' => config('session.cookie'),
                'total_cookies_in_response' => count($cookies),
                'cookie_names' => array_map(fn($c) => $c->getName(), $cookies),
            ]);
        }
        
        return $response;
    }
}
