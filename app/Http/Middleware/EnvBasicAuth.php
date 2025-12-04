<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class EnvBasicAuth
{
    /**
     * Handle an incoming request using simple env-based HTTP Basic auth.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->getUser();
        $pass = $request->getPassword();

        $envUser = env('ADMIN_USERNAME');
        $envPass = env('ADMIN_PASSWORD');
        $envPassHash = env('ADMIN_PASSWORD_HASH');

        // Log attempt (do NOT log the raw password)
        Log::debug('EnvBasicAuth attempt', [
            'user_provided' => $user ?? null,
            'password_provided' => (bool) $pass,
            'env_user_exists' => ! empty($envUser),
            'using_hash' => ! empty($envPassHash),
        ]);

        // Basic checks
        if (! $user || ! $pass || ! hash_equals((string)$envUser, (string)$user)) {
            return response('Unauthorized', 401, ['WWW-Authenticate' => 'Basic realm="Admin Area"']);
        }

        // If an ADMIN_PASSWORD_HASH is set, use Hash::check(), otherwise compare plain text
        $passwordOk = false;
        if (! empty($envPassHash)) {
            $passwordOk = Hash::check($pass, $envPassHash);
        } else {
            $passwordOk = hash_equals((string)$envPass, (string)$pass);
        }

        if (! $passwordOk) {
            return response('Unauthorized', 401, ['WWW-Authenticate' => 'Basic realm="Admin Area"']);
        }

        return $next($request);
    }
}
