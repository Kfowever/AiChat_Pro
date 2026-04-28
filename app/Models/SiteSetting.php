<?php

namespace App\Models;

use App\Core\Database;

class SiteSetting
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll(): array
    {
        $settings = $this->db->query("SELECT setting_key, setting_value FROM site_settings");
        $result = [];
        foreach ($settings as $row) {
            $result[$row['setting_key']] = $row['setting_value'];
        }
        return $result;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $result = $this->db->queryOne("SELECT setting_value FROM site_settings WHERE setting_key = ?", [$key]);
        return $result ? $result['setting_value'] : $default;
    }

    public function set(string $key, string $value): void
    {
        $existing = $this->db->queryOne("SELECT id FROM site_settings WHERE setting_key = ?", [$key]);
        if ($existing) {
            $this->db->update('site_settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
        } else {
            $this->db->insert('site_settings', ['setting_key' => $key, 'setting_value' => $value]);
        }
    }

    public function setMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->set($key, (string) $value);
        }
    }
}
