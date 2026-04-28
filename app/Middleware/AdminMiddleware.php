<?php

namespace App\Middleware;

use App\Core\JWT;
use App\Core\Response;
use App\Models\AdminUser;

class AdminMiddleware
{
    private static $currentAdmin = null;

    public function handle(): bool
    {
        $token = JWT::extractToken();

        if (!$token) {
            Response::unauthorized('Admin authentication required');
            return false;
        }

        $payload = JWT::verify($token);

        if (!$payload) {
            Response::unauthorized('Invalid or expired token');
            return false;
        }

        if (!isset($payload['sub']) || !isset($payload['type']) || $payload['type'] !== 'admin') {
            Response::forbidden('Admin access required');
            return false;
        }

        $adminModel = new AdminUser();
        $admin = $adminModel->findById($payload['sub']);

        if (!$admin) {
            Response::unauthorized('Admin not found');
            return false;
        }

        self::$currentAdmin = $admin;
        return true;
    }

    public static function admin(): ?array
    {
        return self::$currentAdmin;
    }
}
