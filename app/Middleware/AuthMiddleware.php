<?php

namespace App\Middleware;

use App\Core\JWT;
use App\Core\Response;
use App\Models\User;

class AuthMiddleware
{
    private static $currentUser = null;

    public function handle(): bool
    {
        $token = JWT::extractToken();

        if (!$token) {
            Response::unauthorized('Authentication required');
            return false;
        }

        $payload = JWT::verify($token);

        if (!$payload) {
            Response::unauthorized('Invalid or expired token');
            return false;
        }

        if (!isset($payload['sub']) || !isset($payload['type']) || $payload['type'] !== 'user') {
            Response::unauthorized('Invalid token type');
            return false;
        }

        $userModel = new User();
        $user = $userModel->findById($payload['sub']);

        if (!$user) {
            Response::unauthorized('User not found');
            return false;
        }

        if ($user['status'] === 'banned') {
            Response::forbidden('Account has been banned');
            return false;
        }

        self::$currentUser = $user;
        return true;
    }

    public static function user(): ?array
    {
        return self::$currentUser;
    }
}
