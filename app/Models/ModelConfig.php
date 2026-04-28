<?php

namespace App\Models;

use App\Core\Database;

class ModelConfig
{
    private $db;
    private array $columns = [];

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
            $rows = $this->db->query("SHOW COLUMNS FROM model_configs");
            $cols = [];
            foreach ($rows as $row) {
                $field = $row['Field'] ?? null;
                if ($field) {
                    $cols[$field] = true;
                }
            }
            return $cols;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function ensureSchema(): void
    {
        $columnDefs = [
            'api_key' => "VARCHAR(500) DEFAULT NULL COMMENT 'API密钥'",
            'api_base_url' => "VARCHAR(500) DEFAULT NULL COMMENT 'API基础URL'",
            'max_context_tokens' => "INT UNSIGNED NOT NULL DEFAULT 4096 COMMENT '最大上下文token数'",
            'max_output_tokens' => "INT UNSIGNED NOT NULL DEFAULT 2048 COMMENT '最大输出token数'",
            'default_temperature' => "DECIMAL(3,2) NOT NULL DEFAULT 0.70 COMMENT '默认温度'",
            'system_prompt' => "TEXT COMMENT '系统提示词'",
            'description' => "TEXT COMMENT '模型描述'",
            'capabilities' => "TEXT COMMENT '能力标签JSON'",
            'daily_limit' => "INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '每日调用限制'",
        ];

        foreach ($columnDefs as $name => $definition) {
            if ($this->hasColumn($name)) {
                continue;
            }
            try {
                $this->db->execute("ALTER TABLE model_configs ADD COLUMN `{$name}` {$definition}");
            } catch (\Throwable $e) {
            }
        }
    }

    private function hasColumn(string $name): bool
    {
        return isset($this->columns[$name]);
    }

    private function normalizeRow(array $row): array
    {
        $defaults = [
            'api_key' => null,
            'api_base_url' => null,
            'max_context_tokens' => 4096,
            'max_output_tokens' => 2048,
            'default_temperature' => 0.70,
            'system_prompt' => null,
            'description' => null,
            'capabilities' => null,
            'daily_limit' => 0,
            'status' => 'active',
            'sort_order' => 0,
        ];
        return array_merge($defaults, $row);
    }

    private function filterByExistingColumns(array $data): array
    {
        if (empty($this->columns)) {
            return $data;
        }
        return array_intersect_key($data, $this->columns);
    }

    public function findAll(): array
    {
        $rows = $this->db->query("SELECT * FROM model_configs WHERE status = 'active' ORDER BY sort_order ASC");
        return array_map([$this, 'normalizeRow'], $rows);
    }

    public function findAllForAdmin(): array
    {
        $rows = $this->db->query("SELECT * FROM model_configs ORDER BY sort_order ASC");
        return array_map([$this, 'normalizeRow'], $rows);
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->queryOne("SELECT * FROM model_configs WHERE id = ?", [$id]);
        return $row ? $this->normalizeRow($row) : null;
    }

    public function findByModelId(string $modelId): ?array
    {
        $row = $this->db->queryOne("SELECT * FROM model_configs WHERE model_id = ?", [$modelId]);
        if (!$row) {
            $row = $this->db->queryOne("SELECT * FROM model_configs WHERE LOWER(model_id) = LOWER(?)", [$modelId]);
        }
        return $row ? $this->normalizeRow($row) : null;
    }

    public function create(array $data): int
    {
        $insertData = [
            'name' => $data['name'] ?? '',
            'display_name' => $data['display_name'] ?? '',
            'provider' => $data['provider'] ?? 'openai',
            'model_id' => $data['model_id'] ?? '',
            'api_key' => $data['api_key'] ?? null,
            'api_base_url' => $data['api_base_url'] ?? null,
            'pricing_input' => $data['pricing_input'] ?? 0,
            'pricing_output' => $data['pricing_output'] ?? 0,
            'max_context_tokens' => $data['max_context_tokens'] ?? 4096,
            'max_output_tokens' => $data['max_output_tokens'] ?? 2048,
            'default_temperature' => $data['default_temperature'] ?? 0.70,
            'system_prompt' => $data['system_prompt'] ?? null,
            'description' => $data['description'] ?? null,
            'capabilities' => isset($data['capabilities']) ? (is_array($data['capabilities']) ? json_encode($data['capabilities']) : $data['capabilities']) : null,
            'daily_limit' => $data['daily_limit'] ?? 0,
            'status' => $data['status'] ?? 'active',
            'sort_order' => $data['sort_order'] ?? 0,
        ];
        $insertData = $this->filterByExistingColumns($insertData);
        return $this->db->insert('model_configs', $insertData);
    }

    public function update(int $id, array $data): int
    {
        $allowed = ['name', 'display_name', 'provider', 'model_id', 'api_key', 'api_base_url', 'pricing_input', 'pricing_output', 'max_context_tokens', 'max_output_tokens', 'default_temperature', 'system_prompt', 'description', 'capabilities', 'daily_limit', 'status', 'sort_order'];
        $updateData = array_intersect_key($data, array_flip($allowed));
        $updateData = $this->filterByExistingColumns($updateData);
        if (isset($updateData['capabilities']) && is_array($updateData['capabilities'])) {
            $updateData['capabilities'] = json_encode($updateData['capabilities']);
        }
        if (empty($updateData)) {
            return 0;
        }
        return $this->db->update('model_configs', $updateData, 'id = ?', [$id]);
    }

    public function delete(int $id): int
    {
        return $this->db->delete('model_configs', 'id = ?', [$id]);
    }

    public function usageStats(): array
    {
        return $this->db->query(
            "SELECT
                mc.id,
                mc.display_name,
                mc.provider,
                GREATEST(COALESCE(msg.message_count, 0), COALESCE(tx.usage_count, 0)) AS message_count,
                GREATEST(COALESCE(msg.total_tokens, 0), COALESCE(tx.total_tokens, 0)) AS total_tokens,
                COALESCE(tx.total_cost, 0) AS total_cost
             FROM model_configs mc
             LEFT JOIN (
                SELECT
                    LOWER(TRIM(COALESCE(NULLIF(m.model, ''), c.model))) AS model_id,
                    COUNT(m.id) AS message_count,
                    COALESCE(SUM(m.tokens), 0) AS total_tokens
                FROM messages m
                INNER JOIN chats c ON m.chat_id = c.id
                WHERE m.role = 'assistant'
                GROUP BY LOWER(TRIM(COALESCE(NULLIF(m.model, ''), c.model)))
             ) msg ON LOWER(mc.model_id) = msg.model_id
             LEFT JOIN (
                SELECT
                    LOWER(TRIM(COALESCE(NULLIF(t.model_id, ''), CASE WHEN t.description LIKE '% - %' THEN SUBSTRING_INDEX(t.description, ' - ', -1) ELSE '' END))) AS model_id,
                    COUNT(t.id) AS usage_count,
                    COALESCE(SUM(t.total_tokens), 0) AS total_tokens,
                    COALESCE(SUM(t.amount), 0) AS total_cost
                FROM transactions t
                WHERE t.type = 'usage'
                GROUP BY LOWER(TRIM(COALESCE(NULLIF(t.model_id, ''), CASE WHEN t.description LIKE '% - %' THEN SUBSTRING_INDEX(t.description, ' - ', -1) ELSE '' END)))
             ) tx ON LOWER(mc.model_id) = tx.model_id
             ORDER BY message_count DESC, total_tokens DESC, mc.sort_order ASC"
        );
    }
}
