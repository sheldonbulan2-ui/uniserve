<?php
declare(strict_types=1);

/**
 * Pure PHP JWT (JSON Web Token) Implementation using HMAC-SHA256
 * RFC 7519 compliant, zero external dependencies.
 */
class JwtHandler {
    private static function getSecretKey(): string {
        $secret = getenv('JWT_SECRET') ?: getenv('APP_KEY') ?: 'uniserve_enterprise_jwt_secret_key_2026_super_secure';
        return $secret;
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Generate a signed JWT Access Token
     */
    public static function encode(array $payload, int $ttlSeconds = 900): string {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];

        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $ttlSeconds;

        $encodedHeader = self::base64UrlEncode((string)json_encode($header));
        $encodedPayload = self::base64UrlEncode((string)json_encode($payload));

        $signature = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", self::getSecretKey(), true);
        $encodedSignature = self::base64UrlEncode($signature);

        return "{$encodedHeader}.{$encodedPayload}.{$encodedSignature}";
    }

    /**
     * Decode and verify a JWT Access Token
     * Returns the payload array if valid, null if invalid or expired.
     */
    public static function decode(string $jwt): ?array {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = json_decode(self::base64UrlDecode($encodedHeader), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256') {
            return null;
        }

        $expectedSignature = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", self::getSecretKey(), true);
        $providedSignature = self::base64UrlDecode($encodedSignature);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($encodedPayload), true);
        if (!is_array($payload)) {
            return null;
        }

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Generate an opaque 64-character hex Refresh Token
     */
    public static function generateRefreshToken(): string {
        return bin2hex(random_bytes(32));
    }

    /**
     * SHA-256 hash for storing refresh tokens securely in database
     */
    public static function hashRefreshToken(string $token): string {
        return hash('sha256', $token);
    }
}
