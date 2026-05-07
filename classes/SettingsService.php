<?php

declare(strict_types=1);

namespace GemData\Classes;

class SettingsService
{
    private ?array $cache = null;

    public function __construct(private Database $db)
    {
    }

    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $rows = $this->db->query('SELECT setting_key, setting_value FROM system_settings');
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $this->cache = $settings;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $settings = $this->all();
        return array_key_exists($key, $settings) ? (string) $settings[$key] : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        return filter_var($this->get($key, $default ? '1' : '0'), FILTER_VALIDATE_BOOLEAN);
    }

    public function set(string $key, string $value, string $group = 'general', ?int $adminId = null): void
    {
        $existing = $this->db->first('SELECT id FROM system_settings WHERE setting_key = :setting_key LIMIT 1', ['setting_key' => $key]);
        if ($existing) {
            $this->db->execute(
                'UPDATE system_settings SET setting_value = :setting_value, setting_group = :setting_group, updated_by_admin_id = :admin_id WHERE id = :id',
                [
                    'setting_value' => $value,
                    'setting_group' => $group,
                    'admin_id' => $adminId,
                    'id' => $existing['id'],
                ]
            );
        } else {
            $this->db->execute(
                'INSERT INTO system_settings (setting_key, setting_value, setting_group, updated_by_admin_id)
                 VALUES (:setting_key, :setting_value, :setting_group, :admin_id)',
                [
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'setting_group' => $group,
                    'admin_id' => $adminId,
                ]
            );
        }

        $this->cache = null;
    }

    public function grouped(): array
    {
        $rows = $this->db->query('SELECT setting_key, setting_value, setting_group FROM system_settings ORDER BY setting_group, setting_key');
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['setting_group']][$row['setting_key']] = $row['setting_value'];
        }

        return $groups;
    }
}
