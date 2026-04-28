<?php

namespace App\Models;

use App\Core\Database;

class Chat
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        return $this->db->queryOne("SELECT * FROM chats WHERE id = ?", [$id]);
    }

    public function findByUser(int $userId, int $limit = 50): array
    {
        return $this->db->query(
            "SELECT * FROM chats WHERE user_id = ? ORDER BY updated_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }

    public function create(int $userId, string $model = 'gpt-3.5-turbo', string $title = '新对话'): int
    {
        return $this->db->insert('chats', [
            'user_id' => $userId,
            'title' => $title,
            'model' => $model,
        ]);
    }

    public function update(int $id, array $data): int
    {
        $allowed = ['title', 'model', 'last_message'];
        $updateData = array_intersect_key($data, array_flip($allowed));
        if (empty($updateData)) {
            return 0;
        }
        return $this->db->update('chats', $updateData, 'id = ?', [$id]);
    }

    public function delete(int $id, int $userId): int
    {
        return $this->db->delete('chats', 'id = ? AND user_id = ?', [$id, $userId]);
    }

    public function countByUser(int $userId): int
    {
        return (int) $this->db->queryOne("SELECT COUNT(*) as count FROM chats WHERE user_id = ?", [$userId])['count'];
    }

    public function countThisMonth(int $userId): int
    {
        return (int) $this->db->queryOne(
            "SELECT COUNT(*) as count FROM chats WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$userId]
        )['count'];
    }

    public function totalCount(): int
    {
        return (int) $this->db->queryOne("SELECT COUNT(*) as count FROM chats")['count'];
    }

    public function getWithMessages(int $chatId, int $userId): ?array
    {
        $chat = $this->db->queryOne("SELECT * FROM chats WHERE id = ? AND user_id = ?", [$chatId, $userId]);
        if (!$chat) {
            return null;
        }
        $messages = $this->db->query(
            "SELECT id, role, content, reasoning_content, parent_user_message_id, variant_no, tokens, model, created_at FROM messages WHERE chat_id = ? ORDER BY id ASC",
            [$chatId]
        );
        $chat['messages'] = $messages;
        return $chat;
    }
}
