<?php

namespace App\Models;

use App\Core\Database;

class UploadedFile
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        return $this->db->queryOne("SELECT * FROM uploaded_files WHERE id = ?", [$id]);
    }

    public function create(array $data): int
    {
        return $this->db->insert('uploaded_files', [
            'user_id' => $data['user_id'],
            'chat_id' => $data['chat_id'] ?? null,
            'filename' => $data['filename'],
            'filepath' => $data['filepath'],
            'filetype' => $data['filetype'],
            'filesize' => $data['filesize'],
        ]);
    }

    public function findByUser(int $userId, int $limit = 50): array
    {
        return $this->db->query(
            "SELECT * FROM uploaded_files WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }

    public function delete(int $id, int $userId): int
    {
        $file = $this->db->queryOne("SELECT * FROM uploaded_files WHERE id = ? AND user_id = ?", [$id, $userId]);
        if ($file) {
            $path = dirname(__DIR__, 2) . '/' . ltrim($file['filepath'], '/');
            if (is_file($path)) {
                unlink($path);
            }
        }
        return $this->db->delete('uploaded_files', 'id = ? AND user_id = ?', [$id, $userId]);
    }
}
