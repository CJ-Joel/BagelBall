<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\AdminToken;

class AdminAuthController extends Controller
{
    public function showLogin(Request $request)
    {
        $error = $request->query('error');
        return view('admin.login', ['error' => $error]);
    }

    public function login(Request $request)
    {
        $password = $request->input('password');
        $device = $request->input('device') ?? $request->header('User-Agent');

        $envHash = env('ADMIN_PASSWORD_HASH');
        $envPlain = env('ADMIN_PASSWORD');

        $ok = false;
        if (! empty($envHash)) {
            $ok = Hash::check($password, $envHash);
        } else {
            $ok = hash_equals((string)$envPlain, (string)$password);
        }

        if (! $ok) {
            return redirect()->route('admin.login', ['error' => 'invalid']);
        }

        // generate raw token and store hash
        $raw = Str::random(64);
        $hash = Hash::make($raw);

        $token = AdminToken::create([
            'token_hash' => $hash,
            'device' => substr($device, 0, 191),
            'expires_at' => now()->addDays(30),
        ]);

        // redirect to admin with token in querystring
        return redirect()->route('admin.tickets-sold-by-day', ['token' => $raw]);
    }
}
