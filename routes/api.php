<?php

use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\ChatController;
use App\Controllers\UserController;
use App\Controllers\AdminController;
use App\Controllers\PaymentController;
use App\Controllers\InstallController;
use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;
use App\Middleware\RateLimitMiddleware;

function controller(string $class, string $method): callable
{
    return function ($request) use ($class, $method) {
        $instance = new $class();
        $instance->{$method}($request);
    };
}

$rate = [RateLimitMiddleware::class];
$authRate = [AuthMiddleware::class, RateLimitMiddleware::class];
$adminRate = [AdminMiddleware::class, RateLimitMiddleware::class];

Router::post('/api/auth/register', controller(AuthController::class, 'register'), $rate);
Router::post('/api/auth/login', controller(AuthController::class, 'login'), $rate);
Router::post('/api/auth/refresh', controller(AuthController::class, 'refresh'), $authRate);
Router::get('/api/auth/me', controller(AuthController::class, 'me'), [AuthMiddleware::class]);

Router::get('/api/chats', controller(ChatController::class, 'list'), [AuthMiddleware::class]);
Router::post('/api/chats', controller(ChatController::class, 'create'), $authRate);
Router::get('/api/chats/{id}', controller(ChatController::class, 'get'), [AuthMiddleware::class]);
Router::put('/api/chats/{id}', controller(ChatController::class, 'update'), $authRate);
Router::delete('/api/chats/{id}', controller(ChatController::class, 'delete'), $authRate);
Router::get('/api/chats/{id}/export', controller(ChatController::class, 'export'), [AuthMiddleware::class]);
Router::post('/api/chats/{id}/messages', controller(ChatController::class, 'sendMessage'), $authRate);
Router::post('/api/chats/{id}/regenerate', controller(ChatController::class, 'regenerate'), $authRate);
Router::post('/api/chats/{id}/messages/{messageId}/regenerate', controller(ChatController::class, 'regenerateFromMessage'), $authRate);
Router::post('/api/chats/{id}/messages/{messageId}/rollback', controller(ChatController::class, 'rollback'), $authRate);
Router::post('/api/upload', controller(ChatController::class, 'upload'), $authRate);
Router::get('/api/models', controller(ChatController::class, 'getModels'), [AuthMiddleware::class]);

Router::get('/api/user/profile', controller(UserController::class, 'profile'), [AuthMiddleware::class]);
Router::put('/api/user/profile', controller(UserController::class, 'updateProfile'), $authRate);
Router::post('/api/user/avatar', controller(UserController::class, 'uploadAvatar'), $authRate);
Router::put('/api/user/password', controller(UserController::class, 'updatePassword'), $authRate);
Router::get('/api/user/usage', controller(UserController::class, 'usage'), [AuthMiddleware::class]);
Router::get('/api/user/transactions', controller(UserController::class, 'transactions'), [AuthMiddleware::class]);

Router::get('/api/plans', controller(UserController::class, 'plans'));
Router::post('/api/subscriptions', controller(UserController::class, 'subscribe'), $authRate);
Router::put('/api/subscriptions/{id}', controller(UserController::class, 'cancelSubscription'), $authRate);

Router::post('/api/payments/create', controller(PaymentController::class, 'create'), $authRate);
Router::post('/api/payments/callback/{method}', controller(PaymentController::class, 'callback'), $rate);

Router::post('/api/admin/login', controller(AdminController::class, 'login'), $rate);
Router::get('/api/admin/dashboard', controller(AdminController::class, 'dashboard'), [AdminMiddleware::class]);
Router::get('/api/admin/users', controller(AdminController::class, 'listUsers'), [AdminMiddleware::class]);
Router::get('/api/admin/users/{id}', controller(AdminController::class, 'getUser'), [AdminMiddleware::class]);
Router::put('/api/admin/users/{id}', controller(AdminController::class, 'updateUser'), $adminRate);
Router::put('/api/admin/users/{id}/password', controller(AdminController::class, 'resetUserPassword'), $adminRate);
Router::put('/api/admin/users/{id}/quota', controller(AdminController::class, 'updateQuota'), $adminRate);
Router::put('/api/admin/users/{id}/plan', controller(AdminController::class, 'updateUserPlan'), $adminRate);
Router::put('/api/admin/users/{id}/status', controller(AdminController::class, 'updateUserStatus'), $adminRate);
Router::delete('/api/admin/users/{id}', controller(AdminController::class, 'deleteUser'), $adminRate);
Router::get('/api/admin/models', controller(AdminController::class, 'listModels'), [AdminMiddleware::class]);
Router::post('/api/admin/models', controller(AdminController::class, 'createModel'), $adminRate);
Router::put('/api/admin/models/{id}', controller(AdminController::class, 'updateModel'), $adminRate);
Router::delete('/api/admin/models/{id}', controller(AdminController::class, 'deleteModel'), $adminRate);
Router::get('/api/admin/settings', controller(AdminController::class, 'getSettings'), [AdminMiddleware::class]);
Router::put('/api/admin/settings', controller(AdminController::class, 'updateSettings'), $adminRate);
Router::get('/api/admin/plans', controller(AdminController::class, 'listPlans'), [AdminMiddleware::class]);
Router::post('/api/admin/plans', controller(AdminController::class, 'createPlan'), $adminRate);
Router::put('/api/admin/plans/{id}', controller(AdminController::class, 'updatePlan'), $adminRate);
Router::delete('/api/admin/plans/{id}', controller(AdminController::class, 'deletePlan'), $adminRate);
Router::get('/api/admin/plans/{id}/models', controller(AdminController::class, 'getPlanModels'), [AdminMiddleware::class]);
Router::put('/api/admin/plans/{id}/models', controller(AdminController::class, 'updatePlanModels'), $adminRate);
Router::get('/api/admin/transactions', controller(AdminController::class, 'listTransactions'), [AdminMiddleware::class]);
Router::get('/api/admin/system/info', controller(AdminController::class, 'getSystemInfo'), [AdminMiddleware::class]);
Router::get('/api/admin/admins', controller(AdminController::class, 'listAdmins'), [AdminMiddleware::class]);
Router::post('/api/admin/admins', controller(AdminController::class, 'createAdmin'), $adminRate);
Router::delete('/api/admin/admins/{id}', controller(AdminController::class, 'deleteAdmin'), $adminRate);

Router::get('/api/install/check', controller(InstallController::class, 'check'));
Router::post('/api/install/test-db', controller(InstallController::class, 'testDb'), $rate);
Router::post('/api/install/execute', controller(InstallController::class, 'execute'), $rate);
