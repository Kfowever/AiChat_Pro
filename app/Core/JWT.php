<?php

namespace App\Core;

class JWT
{
    public static function generate(array $payload, ?string $secret = null): string
    {
        $secret = $secret ?? self::getSecret();
        $header = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));

        $payload['iat'] = $payload['iat'] ?? time();
        $payload['exp'] = $payload['exp'] ?? (time() + 86400 * 7);
        $body = self::base64UrlEncode(json_encode($payload));

        $signature = self::base64UrlEncode(hash_hmac('sha256', "{$header}.{$body}", $secret, true));

        return "{$header}.{$body}.{$signature}";
    }

    public static function verify(string $token, ?string $secret = null): ?array
    {
        $secret = $secret ?? self::getSecret();
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        $header = $parts[0];
        $body = $parts[1];
        $signature = $parts[2];

        $expectedSignature = self::base64UrlEncode(hash_hmac('sha256', "{$header}.{$body}", $secret, true));

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($body), true);

        if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    public static function extractToken(): ?string
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private static function getSecret(): string
    {
        $secret = getenv('JWT_SECRET');
        if ($secret) {
            return $secret;
        }

        $config = Config::getInstance();
        $secret = $config->get('app.jwt_secret');
        if ($secret && $secret !== 'aichat_pro_default_secret_change_me') {
            return $secret;
        }

        $envPath = dirname(__DIR__, 2) . '/.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), 'JWT_SECRET=') === 0) {
                    $val = trim(substr($line, strlen('JWT_SECRET=')));
                    if ($val && $val !== 'change_this_to_a_random_secret_string') {
                        return $val;
                    }
                }
            }
        }

        throw new \RuntimeException('JWT secret not configured. Please set JWT_SECRET in .env file.');
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
