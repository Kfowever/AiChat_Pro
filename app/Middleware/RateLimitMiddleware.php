<?php

namespace App\Middleware;

use App\Core\Config;
use App\Core\RateLimiter;
use App\Core\Response;

class RateLimitMiddleware
{
    public function handle(): bool
    {
        $profile = $this->profileForRequest();
        $config = Config::getInstance()->get('app.rate_limit.' . $profile, ['max' => 60, 'window' => 60]);
        $max = (int)($config['max'] ?? 60);
        $window = (int)($config['window'] ?? 60);

        $result = (new RateLimiter())->hit($this->key($profile), $max, $window);
        header('X-RateLimit-Limit: ' . $max);
        header('X-RateLimit-Remaining: ' . (int)$result['remaining']);

        if (!$result['allowed']) {
            header('Retry-After: ' . (int)$result['retry_after']);
            Response::error('Too many requests. Please try again later.', 429, [
                'retry_after' => (int)$result['retry_after'],
            ]);
            return false;
        }

        return true;
    }

    private function profileForRequest(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        if (strpos($uri, '/api/admin/login') === 0) {
            return 'admin';
        }
        if (strpos($uri, '/api/auth/') === 0) {
            return 'auth';
        }
        if (strpos($uri, '/api/upload') === 0 || strpos($uri, '/api/user/avatar') === 0) {
            return 'upload';
        }
        if (strpos($uri, '/api/chats') === 0) {
            return 'chat';
        }

        return 'api';
    }

    private function key(string $profile): string
    {
        $user = AuthMiddleware::user();
        $admin = AdminMiddleware::admin();
        $subject = 'ip:' . $this->clientIp();

        if ($user && isset($user['id'])) {
            $subject = 'user:' . $user['id'];
        } elseif ($admin && isset($admin['id'])) {
            $subject = 'admin:' . $admin['id'];
        }

        return $profile . '|' . ($_SERVER['REQUEST_METHOD'] ?? 'GET') . '|' . $subject;
    }

    private function clientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
