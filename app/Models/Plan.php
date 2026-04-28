<?php

namespace App\Models;

use App\Core\Database;

class Plan
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAll(): array
    {
        return $this->db->query("SELECT * FROM plans WHERE status = 'active' ORDER BY sort_order ASC");
    }

    public function findAllForAdmin(): array
    {
        return $this->db->query("SELECT * FROM plans ORDER BY sort_order ASC, id ASC");
    }

    public function findById(int $id): ?array
    {
        return $this->db->queryOne("SELECT * FROM plans WHERE id = ?", [$id]);
    }

    public function create(array $data): int
    {
        return $this->db->insert('plans', [
            'name' => $data['name'],
            'display_name' => $data['display_name'],
            'price' => $data['price'],
            'quota_amount' => $data['quota_amount'],
            'quota_period' => $data['quota_period'] ?? 'monthly',
            'features' => isset($data['features']) ? json_encode($data['features']) : null,
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => $data['status'] ?? 'active',
        ]);
    }

    public function update(int $id, array $data): int
    {
        $allowed = ['name', 'display_name', 'price', 'quota_amount', 'quota_period', 'features', 'sort_order', 'status'];
        $updateData = array_intersect_key($data, array_flip($allowed));
        if (isset($updateData['features']) && is_array($updateData['features'])) {
            $updateData['features'] = json_encode($updateData['features']);
        }
        if (empty($updateData)) {
            return 0;
        }
        return $this->db->update('plans', $updateData, 'id = ?', [$id]);
    }

    public function countSubscribers(int $planId): int
    {
        return (int) $this->db->queryOne(
            "SELECT COUNT(*) as count FROM users WHERE plan_id = ?",
            [$planId]
        )['count'];
    }

    public function delete(int $id): int
    {
        return $this->db->delete('plans', 'id = ?', [$id]);
    }

    public function subscriberStats(): array
    {
        return $this->db->query(
            "SELECT p.id, p.name, p.display_name, COUNT(u.id) as subscriber_count FROM plans p LEFT JOIN users u ON p.id = u.plan_id GROUP BY p.id ORDER BY p.sort_order"
        );
    }
}
