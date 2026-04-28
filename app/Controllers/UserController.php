<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Middleware\AuthMiddleware;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Subscription;
use App\Models\Plan;
use App\Services\FileService;
use App\Services\QuotaService;

class UserController
{
    private User $userModel;
    private FileService $fileService;
    private QuotaService $quotaService;

    public function __construct()
    {
        $this->userModel = new User();
        $this->fileService = new FileService();
        $this->quotaService = new QuotaService();
    }

    public function profile(Request $request): void
    {
        $user = AuthMiddleware::user();
        unset($user['password_hash']);
        $plan = (new Plan())->findById($user['plan_id']);
        $user['plan'] = $plan;
        Response::success($user);
    }

    public function updateProfile(Request $request): void
    {
        $user = AuthMiddleware::user();
        $data = $request->body();

        $validator = Validator::make($data, [
            'username' => 'alpha_num|min:3|max:20',
            'email' => 'email',
        ]);

        if (!$validator->validate()) {
            Response::error($validator->firstError());
            return;
        }

        $updateData = [];
        if (isset($data['username']) && $data['username'] !== $user['username']) {
            $existing = $this->userModel->findByUsername($data['username']);
            if ($existing) {
                Response::error('Username already taken');
                return;
            }
            $updateData['username'] = $data['username'];
        }
        if (isset($data['email']) && $data['email'] !== $user['email']) {
            $existing = $this->userModel->findByEmail($data['email']);
            if ($existing) {
                Response::error('Email already registered');
                return;
            }
            $updateData['email'] = $data['email'];
        }

        if (!empty($updateData)) {
            $this->userModel->update($user['id'], $updateData);
        }

        Response::success(null, 'Profile updated');
    }

    public function uploadAvatar(Request $request): void
    {
        $user = AuthMiddleware::user();
        $file = $request->file('avatar');

        if (!$file) {
            Response::error('No avatar file uploaded');
            return;
        }

        $result = $this->fileService->uploadAvatar($file, $user['id']);
        Response::json($result, $result['success'] ? 200 : 400);
    }

    public function updatePassword(Request $request): void
    {
        $user = AuthMiddleware::user();
        $data = $request->body();

        $validator = Validator::make($data, [
            'current_password' => 'required',
            'new_password' => 'required|min:8',
        ]);

        if (!$validator->validate()) {
            Response::error($validator->firstError());
            return;
        }

        $fullUser = $this->userModel->findById($user['id']);
        if (!password_verify($data['current_password'], $fullUser['password_hash'])) {
            Response::error('Current password is incorrect');
            return;
        }

        $this->userModel->updatePassword($user['id'], $data['new_password']);
        Response::success(null, 'Password updated');
    }

    public function usage(Request $request): void
    {
        $user = AuthMiddleware::user();
        $stats = $this->quotaService->getUsageStats($user['id']);
        Response::success($stats);
    }

    public function transactions(Request $request): void
    {
        $user = AuthMiddleware::user();
        $page = (int) $request->query('page', 1);
        $transactionModel = new Transaction();
        $result = $transactionModel->findByUser($user['id'], $page);
        Response::success($result);
    }

    public function plans(Request $request): void
    {
        $plans = (new Plan())->findAll();
        Response::success($plans);
    }

    public function subscribe(Request $request): void
    {
        $user = AuthMiddleware::user();
        $planId = (int) $request->input('plan_id');
        $autoRenew = (bool) $request->input('auto_renew', false);

        $plan = (new Plan())->findById($planId);
        if (!$plan) {
            Response::error('Plan not found');
            return;
        }

        if ((float)$plan['price'] > 0) {
            Response::error('Paid plans must be activated through the payment flow.', 402);
            return;
        }

        if ((int)$user['plan_id'] === $planId) {
            Response::success(null, 'Already on this plan');
            return;
        }

        $subscriptionModel = new Subscription();
        $currentSub = $subscriptionModel->findActiveByUser($user['id']);
        if ($currentSub) {
            $subscriptionModel->cancel($currentSub['id'], $user['id']);
        }

        $expiresAt = $plan['quota_period'] === 'weekly'
            ? date('Y-m-d H:i:s', strtotime('+1 week'))
            : date('Y-m-d H:i:s', strtotime('+1 month'));

        $subscriptionModel->create($user['id'], $planId, $autoRenew, $expiresAt);
        $this->userModel->update($user['id'], ['plan_id' => $planId]);

        Response::success(null, 'Subscription activated');
    }

    public function cancelSubscription(Request $request): void
    {
        $user = AuthMiddleware::user();
        $subId = (int) $request->param('id');

        $subscriptionModel = new Subscription();
        $result = $subscriptionModel->cancel($subId, $user['id']);

        if ($result > 0) {
            Response::success(null, 'Subscription cancelled');
        } else {
            Response::error('Failed to cancel subscription');
        }
    }
}
