<?php

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Subscription;
use App\Models\Plan;

class PaymentService
{
    private $userModel;
    private $transactionModel;
    private $subscriptionModel;
    private $planModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->transactionModel = new Transaction();
        $this->subscriptionModel = new Subscription();
        $this->planModel = new Plan();
    }

    public function createPayment(int $userId, int $planId, string $paymentMethod): array
    {
        $plan = $this->planModel->findById($planId);
        if (!$plan) {
            return ['success' => false, 'message' => 'Plan not found'];
        }

        if ($plan['price'] <= 0) {
            return ['success' => false, 'message' => 'Free plan does not require payment'];
        }

        $transactionId = $this->transactionModel->create([
            'user_id' => $userId,
            'type' => 'purchase',
            'amount' => (float) $plan['price'],
            'description' => "Subscribe to {$plan['display_name']}",
            'payment_method' => $paymentMethod,
            'payment_status' => 'pending',
        ]);

        $orderData = [
            'transaction_id' => $transactionId,
            'user_id' => $userId,
            'plan_id' => $planId,
            'amount' => (float) $plan['price'],
            'payment_method' => $paymentMethod,
        ];

        switch ($paymentMethod) {
            case 'alipay': $paymentResult = $this->createAlipayOrder($orderData); break;
            case 'wechat': $paymentResult = $this->createWechatOrder($orderData); break;
            default: $paymentResult = ['success' => false, 'message' => 'Unsupported payment method']; break;
        }

        if ($paymentResult['success']) {
            return [
                'success' => true,
                'data' => [
                    'transaction_id' => $transactionId,
                    'payment_url' => $paymentResult['data']['payment_url'] ?? null,
                    'qr_code' => $paymentResult['data']['qr_code'] ?? null,
                ]
            ];
        }

        return $paymentResult;
    }

    public function handleCallback(string $paymentMethod, array $data): array
    {
        switch ($paymentMethod) {
            case 'alipay': return $this->handleAlipayCallback($data);
            case 'wechat': return $this->handleWechatCallback($data);
            default: return ['success' => false, 'message' => 'Unsupported payment method'];
        }
    }

    public function completePayment(int $transactionId): array
    {
        $db = Database::getInstance();
        try {
            $db->beginTransaction();

            $this->transactionModel->updateStatus($transactionId, 'completed');

            $transaction = $db->queryOne("SELECT * FROM transactions WHERE id = ?", [$transactionId]);
            if (!$transaction) {
                throw new \Exception('Transaction not found');
            }

            $userId = $transaction['user_id'];
            $description = $transaction['description'];

            if (str_contains($description, 'Subscribe to')) {
                $planName = str_replace('Subscribe to ', '', $description);
                $plans = $this->planModel->findAll();
                $plan = null;
                foreach ($plans as $p) {
                    if ($p['display_name'] === $planName) {
                        $plan = $p;
                        break;
                    }
                }

                if ($plan) {
                    $this->activateSubscription($userId, $plan['id']);
                }
            }

            $db->commit();
            return ['success' => true, 'message' => 'Payment completed'];
        } catch (\Exception $e) {
            $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function activateSubscription(int $userId, int $planId): void
    {
        $plan = $this->planModel->findById($planId);
        if (!$plan) return;

        $currentSub = $this->subscriptionModel->findActiveByUser($userId);
        if ($currentSub) {
            $this->subscriptionModel->cancel($currentSub['id'], $userId);
        }

        $expiresAt = $plan['quota_period'] === 'weekly'
            ? date('Y-m-d H:i:s', strtotime('+1 week'))
            : date('Y-m-d H:i:s', strtotime('+1 month'));

        $this->subscriptionModel->create($userId, $planId, true, $expiresAt);

        $this->userModel->update($userId, [
            'plan_id' => $planId,
        ]);

        (new QuotaService())->addQuota($userId, (float) $plan['quota_amount'], "Subscribed to {$plan['display_name']}");
    }

    private function createAlipayOrder(array $orderData): array
    {
        $appId = getenv('ALIPAY_APP_ID');
        $privateKey = getenv('ALIPAY_PRIVATE_KEY');

        if (empty($appId) || empty($privateKey)) {
            return ['success' => false, 'message' => 'Alipay not configured. Please set ALIPAY_APP_ID and ALIPAY_PRIVATE_KEY in .env'];
        }

        return [
            'success' => true,
            'data' => [
                'payment_url' => '#alipay-placeholder',
                'qr_code' => '#alipay-qr-placeholder',
                'message' => 'Alipay integration placeholder - implement with actual SDK',
            ]
        ];
    }

    private function createWechatOrder(array $orderData): array
    {
        $appId = getenv('WECHAT_APP_ID');
        $mchId = getenv('WECHAT_MCH_ID');
        $apiKey = getenv('WECHAT_API_KEY');

        if (empty($appId) || empty($mchId) || empty($apiKey)) {
            return ['success' => false, 'message' => 'WeChat Pay not configured. Please set WECHAT_APP_ID, WECHAT_MCH_ID, and WECHAT_API_KEY in .env'];
        }

        return [
            'success' => true,
            'data' => [
                'payment_url' => '#wechat-placeholder',
                'qr_code' => '#wechat-qr-placeholder',
                'message' => 'WeChat Pay integration placeholder - implement with actual SDK',
            ]
        ];
    }

    private function handleAlipayCallback(array $data): array
    {
        return ['success' => true, 'message' => 'Alipay callback placeholder'];
    }

    private function handleWechatCallback(array $data): array
    {
        return ['success' => true, 'message' => 'WeChat callback placeholder'];
    }
}
