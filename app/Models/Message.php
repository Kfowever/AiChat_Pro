<?php

namespace App\Models;

use App\Core\Database;

class Message
{
    private Database $db;
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
            $rows = $this->db->query("SHOW COLUMNS FROM messages");
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
        $columnDefs = [
            'reasoning_content' => "LONGTEXT DEFAULT NULL COMMENT 'assistant reasoning content'",
            'parent_user_message_id' => "BIGINT UNSIGNED DEFAULT NULL COMMENT 'assistant reply target user message id'",
            'variant_no' => "INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'assistant variant sequence per user message'",
        ];

        foreach ($columnDefs as $name => $definition) {
            if ($this->hasColumn($name)) {
                continue;
            }
            try {
                $this->db->execute("ALTER TABLE messages ADD COLUMN `{$name}` {$definition}");
            } catch (\Throwable $e) {
            }
        }

        try {
            $this->db->execute("ALTER TABLE messages ADD INDEX idx_messages_chat_parent (chat_id, parent_user_message_id)");
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

    public function findById(int $id): ?array
    {
        return $this->db->queryOne("SELECT * FROM messages WHERE id = ?", [$id]);
    }

    public function findByChat(int $chatId): array
    {
        return $this->db->query(
            "SELECT id, role, content, reasoning_content, parent_user_message_id, variant_no, tokens, model, created_at FROM messages WHERE chat_id = ? ORDER BY id ASC",
            [$chatId]
        );
    }

    public function findInChat(int $chatId, int $messageId): ?array
    {
        return $this->db->queryOne(
            "SELECT id, chat_id, role, content, reasoning_content, parent_user_message_id, variant_no, tokens, model, created_at FROM messages WHERE chat_id = ? AND id = ?",
            [$chatId, $messageId]
        );
    }

    public function create(int $chatId, string $role, string $content, int $tokens = 0, ?string $model = null, array $meta = []): int
    {
        $insertData = [
            'chat_id' => $chatId,
            'role' => $role,
            'content' => $content,
            'tokens' => $tokens,
            'model' => $model,
        ];

        if (array_key_exists('reasoning_content', $meta)) {
            $insertData['reasoning_content'] = $meta['reasoning_content'];
        }
        if (array_key_exists('parent_user_message_id', $meta)) {
            $insertData['parent_user_message_id'] = $meta['parent_user_message_id'] ? (int)$meta['parent_user_message_id'] : null;
        }
        if (array_key_exists('variant_no', $meta)) {
            $insertData['variant_no'] = max(1, (int)$meta['variant_no']);
        }

        $insertData = $this->filterByExistingColumns($insertData);
        return $this->db->insert('messages', $insertData);
    }

    public function getRecentByChat(int $chatId, int $limit = 50): array
    {
        return $this->db->query(
            "SELECT role, content FROM messages WHERE chat_id = ? ORDER BY id DESC LIMIT ?",
            [$chatId, $limit]
        );
    }

    public function getPromptMessages(int $chatId, int $limit = 50, ?int $targetUserMessageId = null): array
    {
        $rows = $this->db->query(
            "SELECT id, role, content, parent_user_message_id, variant_no FROM messages WHERE chat_id = ? AND role IN ('user', 'assistant') ORDER BY id ASC",
            [$chatId]
        );

        $userOrder = [];
        $userRows = [];
        $assistantCandidates = [];
        $lastUserId = null;

        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            $role = (string)($row['role'] ?? '');

            if ($role === 'user') {
                if ($targetUserMessageId !== null && $id > $targetUserMessageId) {
                    break;
                }
                $lastUserId = $id;
                $userOrder[] = $id;
                $userRows[$id] = $row;
                continue;
            }

            if ($role !== 'assistant') {
                continue;
            }

            $parentId = (int)($row['parent_user_message_id'] ?? 0);
            if ($parentId <= 0) {
                $parentId = $lastUserId ?: 0;
            }
            if ($parentId <= 0) {
                continue;
            }
            if ($targetUserMessageId !== null && $parentId > $targetUserMessageId) {
                continue;
            }

            $variantNo = (int)($row['variant_no'] ?? 1);
            if (!isset($assistantCandidates[$parentId])) {
                $assistantCandidates[$parentId] = $row;
                $assistantCandidates[$parentId]['variant_no'] = $variantNo;
                continue;
            }

            $best = $assistantCandidates[$parentId];
            $bestVariant = (int)($best['variant_no'] ?? 1);
            $bestId = (int)($best['id'] ?? 0);
            if ($variantNo > $bestVariant || ($variantNo === $bestVariant && $id > $bestId)) {
                $assistantCandidates[$parentId] = $row;
                $assistantCandidates[$parentId]['variant_no'] = $variantNo;
            }
        }

        $messages = [];
        foreach ($userOrder as $userId) {
            $user = $userRows[$userId] ?? null;
            if (!$user) {
                continue;
            }
            $messages[] = ['role' => 'user', 'content' => (string)$user['content']];

            if ($targetUserMessageId !== null && $userId === $targetUserMessageId) {
                continue;
            }

            if (isset($assistantCandidates[$userId])) {
                $messages[] = ['role' => 'assistant', 'content' => (string)$assistantCandidates[$userId]['content']];
            }
        }

        if ($limit > 0 && count($messages) > $limit) {
            $messages = array_slice($messages, -$limit);
        }

        return $messages;
    }

    public function findLastUserMessage(int $chatId): ?array
    {
        return $this->db->queryOne(
            "SELECT id, role, content, created_at FROM messages WHERE chat_id = ? AND role = 'user' ORDER BY id DESC LIMIT 1",
            [$chatId]
        );
    }

    public function getNextAssistantVariantNo(int $chatId, int $parentUserMessageId): int
    {
        if (!$this->hasColumn('parent_user_message_id') || !$this->hasColumn('variant_no')) {
            return 1;
        }
        $row = $this->db->queryOne(
            "SELECT COALESCE(MAX(variant_no), 0) as max_variant FROM messages WHERE chat_id = ? AND role = 'assistant' AND parent_user_message_id = ?",
            [$chatId, $parentUserMessageId]
        );
        return max(1, (int)($row['max_variant'] ?? 0) + 1);
    }

    public function deleteAfterId(int $chatId, int $messageId): int
    {
        return $this->db->execute(
            "DELETE FROM messages WHERE chat_id = ? AND id > ?",
            [$chatId, $messageId]
        );
    }

    public function deleteFromId(int $chatId, int $messageId): int
    {
        return $this->db->execute(
            "DELETE FROM messages WHERE chat_id = ? AND id >= ?",
            [$chatId, $messageId]
        );
    }

    public function countModelUsageToday(int $userId, string $model): int
    {
        return (int) $this->db->queryOne(
            "SELECT COUNT(*) as count FROM messages m INNER JOIN chats c ON m.chat_id = c.id WHERE c.user_id = ? AND m.role = 'assistant' AND m.model = ? AND DATE(m.created_at) = CURDATE()",
            [$userId, $model]
        )['count'];
    }

    public function countByUser(int $userId): int
    {
        return (int) $this->db->queryOne(
            "SELECT COUNT(*) as count FROM messages m INNER JOIN chats c ON m.chat_id = c.id WHERE c.user_id = ?",
            [$userId]
        )['count'];
    }

    public function countThisMonth(int $userId): int
    {
        return (int) $this->db->queryOne(
            "SELECT COUNT(*) as count FROM messages m INNER JOIN chats c ON m.chat_id = c.id WHERE c.user_id = ? AND m.role = 'user' AND m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$userId]
        )['count'];
    }
}
