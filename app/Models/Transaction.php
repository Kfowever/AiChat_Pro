<?php

namespace App\Models;

use App\Core\Database;

class Transaction
{
    private $db;
    private array $columns = [];
    private static bool $schemaChecked = false;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->columns = $this->loadColumns();
        $this->ensureSchema();
        $this->columns = $this->loadColumns();
    }

    private function loadColumns(): array
    {
        try {
            $rows = $this->db->query("SHOW COLUMNS FROM transactions");
            $columns = [];
            foreach ($rows as $row) {
                $field = $row['Field'] ?? null;
                if ($field) {
                    $columns[$field] = true;
                }
            }
            return $columns;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function hasColumn(string $name): bool
    {
        return isset($this->columns[$name]);
    }

    private function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        try {
            $amount = $this->db->queryOne("SHOW COLUMNS FROM transactions WHERE Field = 'amount'");
            if (strtolower((string)($amount['Type'] ?? '')) !== 'decimal(14,6)') {
                $this->db->execute("ALTER TABLE transactions MODIFY COLUMN `amount` DECIMAL(14,6) NOT NULL COMMENT 'amount USD'");
            }
        } catch (\Throwable $e) {
        }

        $columnDefs = [
            'model_id' => "VARCHAR(100) DEFAULT NULL COMMENT 'model used by usage transaction'",
            'input_tokens' => "INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'estimated input tokens'",
            'output_tokens' => "INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'estimated output tokens'",
            'total_tokens' => "INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'estimated total tokens'",
        ];

        foreach ($columnDefs as $name => $definition) {
            if ($this->hasColumn($name)) {
                continue;
            }
            try {
                $this->db->execute("ALTER TABLE transactions ADD COLUMN `{$name}` {$definition}");
            } catch (\Throwable $e) {
            }
        }

        try {
            $this->db->execute("ALTER TABLE transactions ADD INDEX idx_transactions_model (`model_id`)");
        } catch (\Throwable $e) {
        }

        try {
            $this->db->execute(
                "UPDATE transactions
                 SET model_id = LOWER(TRIM(SUBSTRING_INDEX(description, ' - ', -1)))
                 WHERE type = 'usage'
                   AND (model_id IS NULL OR model_id = '')
                   AND description LIKE '% - %'"
            );
        } catch (\Throwable $e) {
        }
    }

    private function filterByExistingColumns(array $data): array
    {
        if (empty($this->columns)) {
            return $data;
        }
        return array_intersect_key($data, $this->columns);
    }

    private function usageModelExpression(string $alias = 't'): string
    {
        return "LOWER(TRIM(COALESCE(NULLIF({$alias}.model_id, ''), CASE WHEN {$alias}.type = 'usage' AND {$alias}.description LIKE '% - %' THEN SUBSTRING_INDEX({$alias}.description, ' - ', -1) ELSE '' END)))";
    }

    public function create(array $data): int
    {
        $insertData = [
            'user_id' => $data['user_id'],
            'type' => $data['type'],
            'amount' => $data['amount'],
            'description' => $data['description'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'payment_status' => $data['payment_status'] ?? 'pending',
            'model_id' => $data['model_id'] ?? null,
            'input_tokens' => max(0, (int)($data['input_tokens'] ?? 0)),
            'output_tokens' => max(0, (int)($data['output_tokens'] ?? 0)),
            'total_tokens' => max(0, (int)($data['total_tokens'] ?? 0)),
        ];

        return $this->db->insert('transactions', $this->filterByExistingColumns($insertData));
    }

    public function findByUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        $total = $this->db->queryOne("SELECT COUNT(*) as count FROM transactions WHERE user_id = ?", [$userId])['count'];
        $offset = ($page - 1) * $perPage;
        $data = $this->db->query(
            "SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$userId, $perPage, $offset]
        );
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function listForAdmin(int $page = 1, int $perPage = 20, string $type = '', string $status = '', string $search = ''): array
    {
        $where = '1=1';
        $params = [];

        if ($type) {
            $where .= " AND t.type = ?";
            $params[] = $type;
        }
        if ($status) {
            $where .= " AND t.payment_status = ?";
            $params[] = $status;
        }
        if ($search) {
            $where .= " AND (u.username LIKE ? OR u.email LIKE ? OR t.description LIKE ? OR t.model_id LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $usageModelExpr = $this->usageModelExpression('t');
        $groupSignature = "t.user_id, t.type, COALESCE(t.payment_status, ''), {$usageModelExpr}";

        $total = (int)($this->db->queryOne(
            "SELECT COUNT(*) as count
             FROM (
                 SELECT MAX(t.id) AS latest_id
                 FROM transactions t
                 INNER JOIN users u ON t.user_id = u.id
                 WHERE {$where}
                 GROUP BY {$groupSignature}
             ) merged",
            $params
        )['count'] ?? 0);

        $offset = ($page - 1) * $perPage;
        $dataParams = $params;
        $dataParams[] = $perPage;
        $dataParams[] = $offset;

        $groupedSql = "SELECT
                MAX(t.id) AS latest_id,
                COUNT(*) AS transaction_count,
                COALESCE(SUM(t.amount), 0) AS amount,
                COALESCE(SUM(t.input_tokens), 0) AS input_tokens,
                COALESCE(SUM(t.output_tokens), 0) AS output_tokens,
                COALESCE(SUM(t.total_tokens), 0) AS total_tokens,
                {$usageModelExpr} AS grouped_model_id
             FROM transactions t
             INNER JOIN users u ON t.user_id = u.id
             WHERE {$where}
             GROUP BY {$groupSignature}";

        $data = $this->db->query(
            "SELECT
                latest.id,
                latest.user_id,
                latest.type,
                grouped.amount,
                latest.payment_method,
                latest.payment_status,
                CASE
                    WHEN grouped.transaction_count > 1 AND grouped.grouped_model_id <> '' THEN grouped.grouped_model_id
                    ELSE latest.description
                END AS description,
                latest.description AS latest_description,
                latest.created_at,
                grouped.transaction_count,
                grouped.input_tokens,
                grouped.output_tokens,
                grouped.total_tokens,
                grouped.grouped_model_id AS model_id,
                u.username,
                u.email
             FROM ({$groupedSql}) grouped
             INNER JOIN transactions latest ON latest.id = grouped.latest_id
             INNER JOIN users u ON latest.user_id = u.id
             ORDER BY latest.created_at DESC, latest.id DESC
             LIMIT ? OFFSET ?",
            $dataParams
        );

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int)ceil($total / max(1, $perPage))),
        ];
    }

    public function totalUsed(int $userId, string $period = 'month'): float
    {
        switch ($period) {
            case 'today': $dateCondition = 'DATE(created_at) = CURDATE()'; break;
            case 'week': $dateCondition = 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'; break;
            case 'month': $dateCondition = 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'; break;
            default: $dateCondition = '1=1'; break;
        }
        $result = $this->db->queryOne(
            "SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE user_id = ? AND type = 'usage' AND {$dateCondition}",
            [$userId]
        );
        return (float) $result['total'];
    }

    public function totalConsumed(): float
    {
        $result = $this->db->queryOne("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'usage'");
        return (float) $result['total'];
    }

    public function usageTrend(int $days = 7): array
    {
        $days = max(1, min(30, $days));
        $interval = $days - 1;
        return $this->db->query(
            "SELECT DATE(created_at) as day, COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'usage' AND created_at >= DATE_SUB(CURDATE(), INTERVAL {$interval} DAY) GROUP BY DATE(created_at) ORDER BY day ASC"
        );
    }

    public function updateStatus(int $id, string $status): int
    {
        return $this->db->update('transactions', ['payment_status' => $status], 'id = ?', [$id]);
    }
}
