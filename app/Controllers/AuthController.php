<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function register(Request $request): void
    {
        $result = $this->authService->register($request->body());
        Response::json($result, $result['success'] ? 201 : 400);
    }

    public function login(Request $request): void
    {
        $result = $this->authService->login($request->body());
        Response::json($result, $result['success'] ? 200 : 401);
    }

    public function me(Request $request): void
    {
        $user = \App\Middleware\AuthMiddleware::user();
        $result = $this->authService->me($user['id']);
        Response::json($result);
    }

    public function refresh(Request $request): void
    {
        $user = \App\Middleware\AuthMiddleware::user();
        $result = $this->authService->refreshToken($user['id']);
        Response::json($result);
    }
}
