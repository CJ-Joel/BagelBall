<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use App\Models\User;

class JwtService
{
    /**
     * Create a JWT token for a user
     */
    public static function createToken(User $user): string
    {
        $header = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        
        $now = now();
        $payload = [
            'sub' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'iat' => $now->timestamp,
            'exp' => $now->addHours(24)->timestamp, // 24 hour expiry
        ];
        
        $payload_encoded = self::base64UrlEncode(json_encode($payload));
        
        $signature = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payload_encoded", config('app.key'), true)
        );
        
        return "$header.$payload_encoded.$signature";
    }

    /**
     * Verify and decode a JWT token
     */
    public static function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        // Verify signature
        $expected_signature = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", config('app.key'), true)
        );

        if ($signature !== $expected_signature) {
            return null;
        }

        // Decode payload
        $decoded = json_decode(self::base64UrlDecode($payload), true);

        if (!$decoded) {
            return null;
        }

        // Check expiration
        if ($decoded['exp'] < now()->timestamp) {
            return null;
        }

        return $decoded;
    }

    /**
     * Base64 URL encode
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
