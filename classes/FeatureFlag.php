<?php

declare(strict_types=1);

namespace GemData\Classes;

/**
 * FeatureFlag
 *
 * Reads feature toggles from system_settings table.
 * Cached in memory for the duration of the request.
 * Admin can toggle features without code changes via settings UI.
 *
 * Supported flags (stored in system_settings.setting_key):
 *   reseller_enabled        — enable/disable reseller tier
 *   commission_enabled      — enable/disable commission crediting
 *   api_enabled             — enable/disable API key access
 *   withdrawal_enabled      — enable/disable withdrawal requests
 *   referral_enabled        — enable/disable referral system
 *   auto_retry_enabled      — enable/disable auto transaction retry
 *   maintenance_mode        — put platform in maintenance mode
 */
class FeatureFlag
{
    /** @var array<string, bool> */
    private array $cache = [];

    public function __construct(private Database $db)
    {
    }

    /**
     * Check if a feature flag is enabled.
     * Returns false if flag does not exist in system_settings.
     */
    public function enabled(string $key): bool
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $row = $this->db->first(
            'SELECT setting_value FROM system_settings WHERE setting_key = :key LIMIT 1',
            ['key' => $key]
        );

        $value = $row ? (string) $row['setting_value'] : '0';
        $result = in_array($value, ['1', 'true', 'yes', 'on'], true);

        $this->cache[$key] = $result;
        return $result;
    }

    /**
     * Require a feature to be enabled; throws if disabled.
     */
    public function require(string $key, string $message = ''): void
    {
        if (!$this->enabled($key)) {
            throw new \RuntimeException(
                $message ?: "Feature '{$key}' is currently disabled."
            );
        }
    }

    /**
     * Flush the in-memory cache (useful in long-running scripts).
     */
    public function flush(): void
    {
        $this->cache = [];
    }

    /**
     * Get all flags as key => bool map.
     */
    public function all(): array
    {
        $rows = $this->db->safeQuery(
            "SELECT setting_key, setting_value FROM system_settings WHERE setting_group IN ('features','general','automation','referrals')"
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['setting_key']] = in_array((string)$row['setting_value'], ['1','true','yes','on'], true);
        }
        return $result;
    }
}
