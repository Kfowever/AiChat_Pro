<?php

namespace App\Models;

use App\Core\Database;

class Subscription
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        return $this->db->queryOne("SELECT * FROM subscriptions WHERE id = ?", [$id]);
    }

    public function findActiveByUser(int $userId): ?array
    {
        return $this->db->queryOne(
            "SELECT s.*, p.name as plan_name, p.display_name as plan_display_name, p.price, p.quota_amount, p.quota_period FROM subscriptions s INNER JOIN plans p ON s.plan_id = p.id WHERE s.user_id = ? AND s.status = 'active' ORDER BY s.created_at DESC LIMIT 1",
            [$userId]
        );
    }

    public function create(int $userId, int $planId, bool $autoRenew = false, ?string $expiresAt = null): int
    {
        return $this->db->insert('subscriptions', [
            'user_id' => $userId,
            'plan_id' => $planId,
            'status' => 'active',
            'auto_renew' => $autoRenew ? 1 : 0,
            'expires_at' => $expiresAt,
        ]);
    }

    public function cancel(int $id, int $userId): int
    {
        return $this->db->update('subscriptions', ['status' => 'cancelled', 'auto_renew' => 0], 'id = ? AND user_id = ?', [$id, $userId]);
    }

    public function expire(int $id): int
    {
        return $this->db->update('subscriptions', ['status' => 'expired'], 'id = ?', [$id]);
    }

    public function findByUser(int $userId): array
    {
        return $this->db->query(
            "SELECT s.*, p.name as plan_name, p.display_name, p.price FROM subscriptions s INNER JOIN plans p ON s.plan_id = p.id WHERE s.user_id = ? ORDER BY s.created_at DESC",
            [$userId]
        );
    }
}
