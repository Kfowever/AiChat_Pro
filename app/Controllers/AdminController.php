<?php

namespace App\Controllers;

use App\Core\JWT;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Middleware\AdminMiddleware;
use App\Models\User;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Plan;
use App\Models\ModelConfig;
use App\Models\Transaction;
use App\Models\SiteSetting;
use App\Models\AdminUser;
use App\Models\PlanModel;
use App\Services\QuotaService;

class AdminController
{
    public function login(Request $request): void
    {
        $data = $request->body();
        $validator = Validator::make($data, [
            'username' => 'required',
            'password' => 'required',
        ]);

        if (!$validator->validate()) {
            Response::error($validator->firstError());
            return;
        }

        $adminModel = new AdminUser();
        $admin = $adminModel->verifyPassword($data['username'], $data['password']);

        if (!$admin) {
            Response::error('用户名或密码错误', 401);
            return;
        }

        $token = JWT::generate([
            'sub' => $admin['id'],
            'type' => 'admin',
            'username' => $admin['username'],
        ]);

        unset($admin['password_hash']);
        Response::success(['token' => $token, 'admin' => $admin], '登录成功');
    }

    public function dashboard(Request $request): void
    {
        $userModel = new User();
        $chatModel = new Chat();
        $transactionModel = new Transaction();
        $modelModel = new ModelConfig();
        $planModel = new Plan();

        $data = [
            'total_users' => $userModel->count(),
            'active_today' => $userModel->countActive('today'),
            'active_week' => $userModel->countActive('week'),
            'active_month' => $userModel->countActive('month'),
            'total_chats' => $chatModel->totalCount(),
            'total_consumed' => $transactionModel->totalConsumed(),
            'recent_users' => $userModel->list(1, 10)['data'],
            'model_usage' => $modelModel->usageStats(),
            'plan_stats' => $planModel->subscriberStats(),
            'usage_trend' => $transactionModel->usageTrend(7),
        ];

        Response::success($data);
    }

    public function listUsers(Request $request): void
    {
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 20);
        $search = $request->query('search', '');
        $status = $request->query('status', '');
        $perPage = max(10, min(100, $perPage));

        $userModel = new User();
        $result = $userModel->list($page, $perPage, $search, $status);

        foreach ($result['data'] as &$user) {
            unset($user['password_hash']);
        }

        Response::success($result);
    }

    public function getUser(Request $request): void
    {
        $id = (int) $request->param('id');
        $userModel = new User();
        $user = $userModel->findById($id);

        if (!$user) {
            Response::notFound('User not found');
            return;
        }

        $chatModel = new Chat();
        $messageModel = new Message();
        $transactionModel = new Transaction();
        $planModel = new Plan();
        $user['plan'] = $planModel->findById((int)$user['plan_id']);
        $user['stats'] = [
            'chat_count' => $chatModel->countByUser($id),
            'message_count' => $messageModel->countByUser($id),
            'monthly_usage' => $transactionModel->totalUsed($id, 'month'),
        ];

        unset($user['password_hash']);
        Response::success($user);
    }

    public function updateUser(Request $request): void
    {
        $id = (int) $request->param('id');
        $data = $request->body();
        $userModel = new User();
        $user = $userModel->findById($id);

        if (!$user) {
            Response::notFound('User not found');
            return;
        }

        $update = [];
        if (array_key_exists('username', $data)) {
            $username = trim((string)$data['username']);
            if (mb_strlen($username) < 2 || mb_strlen($username) > 50) {
                Response::error('用户名长度需为 2-50 个字符');
                return;
            }
            $existing = $userModel->findByUsername($username);
            if ($existing && (int)$existing['id'] !== $id) {
                Response::error('用户名已存在');
                return;
            }
            $update['username'] = $username;
        }

        if (array_key_exists('email', $data)) {
            $email = trim((string)$data['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::error('邮箱格式不正确');
                return;
            }
            $existing = $userModel->findByEmail($email);
            if ($existing && (int)$existing['id'] !== $id) {
                Response::error('邮箱已存在');
                return;
            }
            $update['email'] = $email;
        }

        if (array_key_exists('plan_id', $data)) {
            $planId = (int)$data['plan_id'];
            if (!(new Plan())->findById($planId)) {
                Response::error('Plan not found');
                return;
            }
            $update['plan_id'] = $planId;
        }

        if (array_key_exists('status', $data)) {
            $status = (string)$data['status'];
            if (!in_array($status, ['active', 'banned'], true)) {
                Response::error('Invalid status');
                return;
            }
            $update['status'] = $status;
        }

        foreach (['quota_balance', 'total_used'] as $field) {
            if (array_key_exists($field, $data)) {
                if (!is_numeric($data[$field]) || (float)$data[$field] < 0) {
                    Response::error($field . ' must be a non-negative number');
                    return;
                }
                $update[$field] = round((float)$data[$field], 6);
            }
        }

        if (array_key_exists('auto_renew', $data)) {
            $update['auto_renew'] = !empty($data['auto_renew']) ? 1 : 0;
        }

        if (empty($update)) {
            Response::success(null, '没有需要更新的字段');
            return;
        }

        $userModel->update($id, $update);
        Response::success(null, '用户资料已更新');
    }

    public function resetUserPassword(Request $request): void
    {
        $id = (int) $request->param('id');
        $password = (string) $request->input('password', '');

        if (strlen($password) < 8) {
            Response::error('密码至少8位');
            return;
        }

        $userModel = new User();
        if (!$userModel->findById($id)) {
            Response::notFound('User not found');
            return;
        }

        $userModel->updatePassword($id, $password);
        Response::success(null, '用户密码已重置');
    }

    public function updateQuota(Request $request): void
    {
        $id = (int) $request->param('id');
        $action = $request->input('action');
        $amount = (float) $request->input('amount');

        if (!in_array($action, ['add', 'deduct'])) {
            Response::error('Action must be add or deduct');
            return;
        }

        if ($amount <= 0) {
            Response::error('Amount must be positive');
            return;
        }

        $userModel = new User();
        $user = $userModel->findById($id);
        if (!$user) {
            Response::notFound('User not found');
            return;
        }

        $quotaService = new QuotaService();
        if ($action === 'add') {
            $quotaService->addQuota($id, $amount, '管理员手动调整(增加)');
        } else {
            if (!$userModel->deductQuota($id, $amount)) {
                Response::error('Insufficient quota to deduct');
                return;
            }
            (new Transaction())->create([
                'user_id' => $id,
                'type' => 'usage',
                'amount' => $amount,
                'description' => '管理员手动调整(扣减)',
                'payment_method' => 'manual',
                'payment_status' => 'completed',
            ]);
        }

        Response::success(null, '额度已更新');
    }

    public function updateUserPlan(Request $request): void
    {
        $id = (int) $request->param('id');
        $planId = (int) $request->input('plan_id');

        $userModel = new User();
        $planModel = new Plan();

        $user = $userModel->findById($id);
        if (!$user) {
            Response::notFound('User not found');
            return;
        }

        $plan = $planModel->findById($planId);
        if (!$plan) {
            Response::error('Plan not found');
            return;
        }

        $userModel->update($id, ['plan_id' => $planId]);
        Response::success(null, '用户套餐已更新');
    }

    public function updateUserStatus(Request $request): void
    {
        $id = (int) $request->param('id');
        $status = $request->input('status');

        if (!in_array($status, ['active', 'banned'])) {
            Response::error('Invalid status');
            return;
        }

        $userModel = new User();
        $user = $userModel->findById($id);
        if (!$user) {
            Response::notFound('User not found');
            return;
        }

        $userModel->update($id, ['status' => $status]);
        Response::success(null, '用户状态已更新');
    }

    public function deleteUser(Request $request): void
    {
        $id = (int) $request->param('id');
        $userModel = new User();
        $user = $userModel->findById($id);
        if (!$user) {
            Response::notFound('User not found');
            return;
        }

        $userModel->delete($id);
        Response::success(null, '用户已删除');
    }

    public function listModels(Request $request): void
    {
        $modelModel = new ModelConfig();
        Response::success($modelModel->findAllForAdmin());
    }

    public function createModel(Request $request): void
    {
        $data = $request->body();
        $validator = Validator::make($data, [
            'display_name' => 'required',
            'model_id' => 'required',
        ]);

        if (!$validator->validate()) {
            Response::error($validator->firstError());
            return;
        }

        $modelModel = new ModelConfig();
        $id = $modelModel->create($this->sanitizeModelPayload($data, true));
        Response::success(['id' => $id], '模型已创建', 201);
    }

    public function updateModel(Request $request): void
    {
        $id = (int) $request->param('id');
        $data = $request->body();
        $modelModel = new ModelConfig();
        if (!$modelModel->findById($id)) {
            Response::notFound('Model not found');
            return;
        }
        $modelModel->update($id, $this->sanitizeModelPayload($data, false));
        Response::success(null, '模型已更新');
    }

    public function deleteModel(Request $request): void
    {
        $id = (int) $request->param('id');
        $modelModel = new ModelConfig();
        if (!$modelModel->findById($id)) {
            Response::notFound('Model not found');
            return;
        }
        $modelModel->delete($id);
        Response::success(null, '模型已删除');
    }

    private function sanitizeModelPayload(array $data, bool $creating): array
    {
        $payload = $data;

        foreach (['name', 'display_name', 'provider', 'model_id', 'api_base_url', 'api_key', 'system_prompt', 'description', 'status'] as $field) {
            if (array_key_exists($field, $payload) && $payload[$field] !== null) {
                $payload[$field] = trim((string)$payload[$field]);
            }
        }

        if (isset($payload['status']) && !in_array($payload['status'], ['active', 'inactive'], true)) {
            $payload['status'] = 'inactive';
        }

        if (isset($payload['provider'])) {
            $payload['provider'] = strtolower($payload['provider']);
        }

        if (isset($payload['model_id'])) {
            $payload['model_id'] = str_replace('_', '-', $payload['model_id']);
        }

        if (($payload['provider'] ?? '') === 'deepseek' && isset($payload['model_id'])) {
            $payload['model_id'] = strtolower($payload['model_id']);
        }

        foreach (['max_context_tokens', 'max_output_tokens', 'daily_limit', 'sort_order'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = max(0, (int)$payload[$field]);
            }
        }

        foreach (['pricing_input', 'pricing_output', 'default_temperature'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = max(0, (float)$payload[$field]);
            }
        }

        if (array_key_exists('capabilities', $payload)) {
            if (is_string($payload['capabilities'])) {
                $decoded = json_decode($payload['capabilities'], true);
                if (is_array($decoded)) {
                    $payload['capabilities'] = $decoded;
                } else {
                    $payload['capabilities'] = array_values(array_filter(array_map('trim', explode(',', $payload['capabilities']))));
                }
            }
            if (is_array($payload['capabilities'])) {
                $payload['capabilities'] = array_values(array_unique(array_filter(array_map(static function ($value) {
                    return trim((string)$value);
                }, $payload['capabilities']))));
            }
        }

        if (isset($payload['default_temperature'])) {
            $payload['default_temperature'] = min(2, (float)$payload['default_temperature']);
        }

        if ($creating && empty($payload['name']) && !empty($payload['model_id'])) {
            $payload['name'] = $payload['model_id'];
        }

        return $payload;
    }

    public function getSettings(Request $request): void
    {
        $settingModel = new SiteSetting();
        Response::success($settingModel->getAll());
    }

    public function updateSettings(Request $request): void
    {
        $data = $request->body();
        $settingModel = new SiteSetting();
        $settingModel->setMany($data);
        Response::success(null, '设置已保存');
    }

    public function listPlans(Request $request): void
    {
        $planModel = new Plan();
        $plans = $planModel->findAllForAdmin();
        foreach ($plans as &$plan) {
            $plan['subscriber_count'] = $planModel->countSubscribers($plan['id']);
        }
        Response::success($plans);
    }

    public function createPlan(Request $request): void
    {
        $data = $request->body();
        $validator = Validator::make($data, [
            'name' => 'required',
            'display_name' => 'required',
        ]);

        if (!$validator->validate()) {
            Response::error($validator->firstError());
            return;
        }

        $planModel = new Plan();
        $id = $planModel->create($data);
        Response::success(['id' => $id], '套餐已创建', 201);
    }

    public function updatePlan(Request $request): void
    {
        $id = (int) $request->param('id');
        $data = $request->body();
        $planModel = new Plan();
        $planModel->update($id, $data);
        Response::success(null, '套餐已更新');
    }

    public function deletePlan(Request $request): void
    {
        $id = (int) $request->param('id');
        $planModel = new Plan();
        $planModel->delete($id);
        Response::success(null, '套餐已删除');
    }

    public function getPlanModels(Request $request): void
    {
        $planId = (int) $request->param('id');
        $planModel = new PlanModel();
        Response::success($planModel->getModelIdsByPlan($planId));
    }

    public function updatePlanModels(Request $request): void
    {
        $planId = (int) $request->param('id');
        $modelIds = $request->input('model_ids', []);
        if (!is_array($modelIds)) {
            $modelIds = [];
        }
        $planModel = new PlanModel();
        $planModel->setModelsForPlan($planId, $modelIds);
        Response::success(null, '套餐模型关联已更新');
    }

    public function listTransactions(Request $request): void
    {
        $page = (int) $request->query('page', 1);
        $type = $request->query('type', '');
        $status = $request->query('status', '');
        $search = $request->query('search', '');

        $transactionModel = new Transaction();
        $result = $transactionModel->listForAdmin($page, 20, $type, $status, $search);
        Response::success($result);
    }

    public function getSystemInfo(Request $request): void
    {
        $info = [
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'mysql_version' => 'Unknown',
            'disk_free' => '',
            'disk_total' => '',
            'extensions' => [],
        ];

        try {
            $db = \App\Core\Database::getInstance();
            $pdo = $db->getPdo();
            if ($pdo) {
                $r = $db->queryOne("SELECT VERSION() as v");
                $info['mysql_version'] = $r ? $r['v'] : 'Unknown';
            }
        } catch (\Throwable $e) {}

        $diskFree = @disk_free_space('.');
        $diskTotal = @disk_total_space('.');
        if ($diskFree !== false && $diskTotal !== false) {
            $info['disk_free'] = round($diskFree / 1073741824, 2) . ' GB';
            $info['disk_total'] = round($diskTotal / 1073741824, 2) . ' GB';
        }

        $exts = ['pdo_mysql','json','mbstring','openssl','curl','fileinfo','gd','zip','bcmath','redis'];
        foreach ($exts as $ext) {
            $info['extensions'][$ext] = extension_loaded($ext);
        }

        Response::success($info);
    }

    public function listAdmins(Request $request): void
    {
        $adminModel = new AdminUser();
        Response::success($adminModel->findAll());
    }

    public function createAdmin(Request $request): void
    {
        $data = $request->body();
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if (strlen($username) < 3) {
            Response::error('用户名至少3个字符');
            return;
        }
        if (strlen($password) < 8) {
            Response::error('密码至少8位');
            return;
        }

        $adminModel = new AdminUser();
        $existing = $adminModel->findByUsername($username);
        if ($existing) {
            Response::error('用户名已存在');
            return;
        }

        $id = $adminModel->create($username, $password);
        Response::success(['id' => $id], '管理员已创建', 201);
    }

    public function deleteAdmin(Request $request): void
    {
        $id = (int) $request->param('id');
        $adminModel = new AdminUser();

        $currentAdmin = AdminMiddleware::admin();
        if ($currentAdmin && (int)$currentAdmin['id'] === $id) {
            Response::error('不能删除自己');
            return;
        }

        $adminModel->delete($id);
        Response::success(null, '管理员已删除');
    }
}
