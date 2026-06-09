<?php
// ============================================================
// EGGLAND BD - Pure PHP JWT HS256 Implementation
// ============================================================

require_once __DIR__ . '/config.php';

class JWT {

    /**
     * Encode a JWT token
     */
    public static function encode(array $payload): string {
        $header = self::base64UrlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256'
        ]));

        $payload['iat'] = time();
        $payload['exp'] = time() + JWT_EXPIRY;

        $encodedPayload = self::base64UrlEncode(json_encode($payload));
        $signature = self::sign($header . '.' . $encodedPayload);

        return $header . '.' . $encodedPayload . '.' . $signature;
    }

    /**
     * Decode and verify a JWT token
     */
    public static function decode(string $token): array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Invalid token format');
        }

        [$header, $payload, $signature] = $parts;

        // Verify signature
        $expectedSig = self::sign($header . '.' . $payload);
        if (!hash_equals($expectedSig, $signature)) {
            throw new Exception('Invalid token signature');
        }

        $data = json_decode(self::base64UrlDecode($payload), true);

        if (!$data) {
            throw new Exception('Invalid token payload');
        }

        // Check expiry
        if (isset($data['exp']) && $data['exp'] < time()) {
            throw new Exception('Token expired');
        }

        return $data;
    }

    /**
     * Generate a refresh token
     */
    public static function generateRefreshToken(): string {
        return bin2hex(random_bytes(32));
    }

    private static function sign(string $data): string {
        return self::base64UrlEncode(
            hash_hmac('sha256', $data, JWT_SECRET, true)
        );
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }

    /**
     * Extract user data from Bearer token in Authorization header
     */
    public static function fromRequest(): ?array {
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        // Check Authorization header
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (empty($authHeader)) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        }

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);

        try {
            return self::decode($token);
        } catch (Exception $e) {
            return null;
        }
    }
}
