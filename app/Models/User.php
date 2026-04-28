<?php

namespace App\Models;

use App\Core\Database;

class User
{
    private $db;
    private static bool $precisionChecked = false;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureAccountingPrecision();
    }

    private function ensureAccountingPrecision(): void
    {
        if (self::$precisionChecked) {
            return;
        }
        self::$precisionChecked = true;

        try {
            $columns = $this->db->query("SHOW COLUMNS FROM users WHERE Field IN ('quota_balance', 'total_used')");
            $types = [];
            foreach ($columns as $column) {
                $types[$column['Field']] = strtolower((string)($column['Type'] ?? ''));
            }
            if (($types['quota_balance'] ?? '') !== 'decimal(14,6)') {
                $this->db->execute("ALTER TABLE users MODIFY COLUMN `quota_balance` DECIMAL(14,6) DEFAULT 0.000000 COMMENT 'remaining quota USD'");
            }
            if (($types['total_used'] ?? '') !== 'decimal(14,6)') {
                $this->db->execute("ALTER TABLE users MODIFY COLUMN `total_used` DECIMAL(14,6) DEFAULT 0.000000 COMMENT 'total used quota USD'");
            }
        } catch (\Throwable $e) {
        }
    }

    public function findById(int $id): ?array
    {
        return $this->db->queryOne("SELECT * FROM users WHERE id = ?", [$id]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db->queryOne("SELECT * FROM users WHERE email = ?", [$email]);
    }

    public function findByUsername(string $username): ?array
    {
        return $this->db->queryOne("SELECT * FROM users WHERE username = ?", [$username]);
    }

    public function create(array $data): int
    {
        return $this->db->insert('users', [
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            'plan_id' => $data['plan_id'] ?? 1,
            'quota_balance' => $data['quota_balance'] ?? 1.000000,
        ]);
    }

    public function update(int $id, array $data): int
    {
        $allowed = ['username', 'email', 'avatar', 'plan_id', 'quota_balance', 'total_used', 'status', 'auto_renew'];
        $updateData = array_intersect_key($data, array_flip($allowed));
        if (empty($updateData)) {
            return 0;
        }
        return $this->db->update('users', $updateData, 'id = ?', [$id]);
    }

    public function updatePassword(int $id, string $newPassword): int
    {
        return $this->db->update('users', ['password_hash' => password_hash($newPassword, PASSWORD_BCRYPT)], 'id = ?', [$id]);
    }

    public function updateAvatar(int $id, string $avatarPath): int
    {
        return $this->db->update('users', ['avatar' => $avatarPath], 'id = ?', [$id]);
    }

    public function updateQuota(int $id, float $balance, float $used): int
    {
        return $this->db->update('users', [
            'quota_balance' => $balance,
            'total_used' => $used
        ], 'id = ?', [$id]);
    }

    public function deductQuota(int $id, float $amount): bool
    {
        $sql = "UPDATE users SET quota_balance = quota_balance - ?, total_used = total_used + ? WHERE id = ? AND quota_balance >= ?";
        return $this->db->execute($sql, [$amount, $amount, $id, $amount]) > 0;
    }

    public function addQuota(int $id, float $amount): int
    {
        $sql = "UPDATE users SET quota_balance = quota_balance + ? WHERE id = ?";
        return $this->db->execute($sql, [$amount, $id]);
    }

    public function delete(int $id): int
    {
        return $this->db->delete('users', 'id = ?', [$id]);
    }

    public function list(int $page = 1, int $perPage = 20, string $search = '', string $status = ''): array
    {
        $where = '1=1';
        $params = [];

        if ($search) {
            $where .= " AND (users.username LIKE ? OR users.email LIKE ?";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            if (ctype_digit($search)) {
                $where .= " OR users.id = ?";
                $params[] = (int)$search;
            }
            $where .= ")";
        }

        if ($status) {
            $where .= " AND users.status = ?";
            $params[] = $status;
        }

        $total = $this->db->queryOne("SELECT COUNT(*) as count FROM users WHERE {$where}", $params)['count'];
        $offset = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;

        $users = $this->db->query(
            "SELECT users.id, users.username, users.email, users.avatar, users.plan_id, plans.display_name AS plan_display_name, users.quota_balance, users.total_used, users.status, users.auto_renew, users.created_at, users.updated_at FROM users LEFT JOIN plans ON users.plan_id = plans.id WHERE {$where} ORDER BY users.created_at DESC LIMIT ? OFFSET ?",
            $params
        );

        return [
            'data' => $users,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    public function count(): int
    {
        return (int) $this->db->queryOne("SELECT COUNT(*) as count FROM users")['count'];
    }

    public function countActive(string $period = 'today'): int
    {
        switch ($period) {
            case 'today': $dateCondition = 'DATE(updated_at) = CURDATE()'; break;
            case 'week': $dateCondition = 'updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'; break;
            case 'month': $dateCondition = 'updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'; break;
            default: $dateCondition = '1=1'; break;
        }
        return (int) $this->db->queryOne("SELECT COUNT(*) as count FROM users WHERE {$dateCondition}")['count'];
    }

    public function verifyPassword(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);
        if ($user && password_verify($password, $user['password_hash'])) {
            return $user;
        }
        return null;
    }
}
