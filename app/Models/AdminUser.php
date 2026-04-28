<?php

namespace App\Models;

use App\Core\Database;

class AdminUser
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        return $this->db->queryOne("SELECT * FROM admin_users WHERE id = ?", [$id]);
    }

    public function findByUsername(string $username): ?array
    {
        return $this->db->queryOne("SELECT * FROM admin_users WHERE username = ?", [$username]);
    }

    public function findAll(): array
    {
        return $this->db->query("SELECT id, username, created_at FROM admin_users ORDER BY id ASC");
    }

    public function create(string $username, string $password): int
    {
        return $this->db->insert('admin_users', [
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        ]);
    }

    public function delete(int $id): int
    {
        return $this->db->delete('admin_users', 'id = ?', [$id]);
    }

    public function verifyPassword(string $username, string $password): ?array
    {
        $admin = $this->findByUsername($username);
        if ($admin && password_verify($password, $admin['password_hash'])) {
            return $admin;
        }
        return null;
    }
}
