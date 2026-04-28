<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\ModelConfig;

class QuotaService
{
    private User $userModel;
    private Transaction $transactionModel;
    private ModelConfig $modelModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->transactionModel = new Transaction();
        $this->modelModel = new ModelConfig();
    }

    public function calculateCost(string $modelId, int $inputTokens, int $outputTokens): float
    {
        $model = $this->modelModel->findByModelId($modelId);
        if (!$model) {
            return 0.0;
        }

        $inputCost = ($inputTokens / 1000) * (float) $model['pricing_input'];
        $outputCost = ($outputTokens / 1000) * (float) $model['pricing_output'];

        return round($inputCost + $outputCost, 6);
    }

    public function deductQuota(int $userId, float $amount, string $description = '', array $meta = []): bool
    {
        if ($amount <= 0) {
            return true;
        }

        $db = \App\Core\Database::getInstance();
        try {
            $db->beginTransaction();

            $success = $this->userModel->deductQuota($userId, $amount);

            if ($success) {
                $this->transactionModel->create([
                    'user_id' => $userId,
                    'type' => 'usage',
                    'amount' => $amount,
                    'description' => $description,
                    'payment_method' => 'system',
                    'payment_status' => 'completed',
                    'model_id' => $meta['model_id'] ?? null,
                    'input_tokens' => $meta['input_tokens'] ?? 0,
                    'output_tokens' => $meta['output_tokens'] ?? 0,
                    'total_tokens' => $meta['total_tokens'] ?? 0,
                ]);
            }

            $db->commit();
            return $success;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }

    public function addQuota(int $userId, float $amount, string $description = ''): bool
    {
        $db = \App\Core\Database::getInstance();
        try {
            $db->beginTransaction();

            $this->userModel->addQuota($userId, $amount);

            $this->transactionModel->create([
                'user_id' => $userId,
                'type' => 'quota_grant',
                'amount' => $amount,
                'description' => $description,
                'payment_method' => 'system',
                'payment_status' => 'completed',
            ]);

            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }

    public function getUsageStats(int $userId): array
    {
        $user = $this->userModel->findById($userId);
        if (!$user) {
            return [];
        }

        return [
            'quota_balance' => (float) $user['quota_balance'],
            'total_used' => (float) $user['total_used'],
            'monthly_used' => $this->transactionModel->totalUsed($userId, 'month'),
            'weekly_used' => $this->transactionModel->totalUsed($userId, 'week'),
            'today_used' => $this->transactionModel->totalUsed($userId, 'today'),
            'monthly_chats' => (new \App\Models\Chat())->countThisMonth($userId),
        ];
    }

    public function grantPeriodicQuota(): void
    {
        $users = \App\Core\Database::getInstance()->query(
            "SELECT u.id, u.plan_id, p.quota_amount, p.quota_period FROM users u INNER JOIN plans p ON u.plan_id = p.id WHERE u.status = 'active'"
        );

        foreach ($users as $user) {
            $this->addQuota($user['id'], (float) $user['quota_amount'], "Periodic quota grant ({$user['quota_period']})");
        }
    }
}
