<?php

namespace App\Services;

use App\Core\JWT;
use App\Core\Validator;
use App\Models\User;
use App\Models\Subscription;
use App\Models\SiteSetting;
use App\Models\Plan;

class AuthService
{
    private User $userModel;
    private Subscription $subscriptionModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->subscriptionModel = new Subscription();
    }

    public function register(array $data): array
    {
        $settings = new SiteSetting();
        if ($settings->get('allow_registration', '1') !== '1') {
            return ['success' => false, 'message' => 'Registration is currently disabled'];
        }

        $validator = Validator::make($data, [
            'username' => 'required|alpha_num|min:3|max:20',
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        if (!$validator->validate()) {
            return ['success' => false, 'message' => $validator->firstError()];
        }

        if ($this->userModel->findByEmail($data['email'])) {
            return ['success' => false, 'message' => 'Email already registered'];
        }

        if ($this->userModel->findByUsername($data['username'])) {
            return ['success' => false, 'message' => 'Username already taken'];
        }

        $userId = $this->userModel->create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'plan_id' => 1,
            'quota_balance' => 1.0000,
        ]);

        $this->subscriptionModel->create($userId, 1, false, date('Y-m-d H:i:s', strtotime('+1 month')));

        $token = JWT::generate([
            'sub' => $userId,
            'type' => 'user',
            'username' => $data['username'],
        ]);

        $user = $this->userModel->findById($userId);
        unset($user['password_hash']);
        $user['plan'] = (new Plan())->findById((int)$user['plan_id']);

        return [
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'token' => $token,
                'user' => $user,
            ]
        ];
    }

    public function login(array $data): array
    {
        $validator = Validator::make($data, [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!$validator->validate()) {
            return ['success' => false, 'message' => $validator->firstError()];
        }

        $user = $this->userModel->verifyPassword($data['email'], $data['password']);

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }

        if ($user['status'] === 'banned') {
            return ['success' => false, 'message' => 'Account has been banned'];
        }

        $token = JWT::generate([
            'sub' => $user['id'],
            'type' => 'user',
            'username' => $user['username'],
        ]);

        unset($user['password_hash']);
        $user['plan'] = (new Plan())->findById((int)$user['plan_id']);

        return [
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => $user,
            ]
        ];
    }

    public function me(int $userId): array
    {
        $user = $this->userModel->findById($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        unset($user['password_hash']);
        $user['plan'] = (new Plan())->findById((int)$user['plan_id']);
        return ['success' => true, 'data' => $user];
    }

    public function refreshToken(int $userId): array
    {
        $user = $this->userModel->findById($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        $token = JWT::generate([
            'sub' => $user['id'],
            'type' => 'user',
            'username' => $user['username'],
        ]);

        return ['success' => true, 'data' => ['token' => $token]];
    }
}
