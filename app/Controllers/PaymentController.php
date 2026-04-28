<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\PaymentService;

class PaymentController
{
    private PaymentService $paymentService;

    public function __construct()
    {
        $this->paymentService = new PaymentService();
    }

    public function create(Request $request): void
    {
        $user = \App\Middleware\AuthMiddleware::user();
        $planId = (int) $request->input('plan_id');
        $paymentMethod = $request->input('payment_method', 'alipay');

        if (!in_array($paymentMethod, ['alipay', 'wechat'])) {
            Response::error('Unsupported payment method');
            return;
        }

        $result = $this->paymentService->createPayment($user['id'], $planId, $paymentMethod);
        Response::json($result, $result['success'] ? 200 : 400);
    }

    public function callback(Request $request): void
    {
        $paymentMethod = $request->param('method', 'alipay');
        $data = $request->body();

        $result = $this->paymentService->handleCallback($paymentMethod, $data);

        if ($result['success']) {
            echo 'success';
        } else {
            echo 'fail';
        }
        exit;
    }
}
