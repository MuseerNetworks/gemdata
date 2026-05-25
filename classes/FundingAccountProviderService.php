<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class FundingAccountProviderService
{
    private const PROVIDERS = ['katpay', 'paystack', 'xixapay'];

    public function __construct(
        private Database $db,
        private SettingsService $settings,
        private KatPayVirtualAccountService $katPay,
        private PaystackDedicatedAccountService $paystack
    ) {
    }

    public function activeProvider(): string
    {
        $provider = strtolower(trim((string) $this->settings->get(
            'active_funding_provider',
            (string) config('payments.active_funding_provider', 'katpay')
        )));

        return in_array($provider, self::PROVIDERS, true) ? $provider : 'katpay';
    }

    public function multiProviderFunding(): bool
    {
        return $this->settings->bool(
            'multi_provider_funding',
            (bool) config('payments.multi_provider_funding', false)
        );
    }

    public function providerDisplayEnabled(string $provider): bool
    {
        $provider = strtolower(trim($provider));
        $default = match ($provider) {
            'katpay' => true,
            default => false,
        };

        return $this->settings->bool('funding_provider_' . $provider . '_user_display_enabled', $default);
    }

    public function displayAccountsForUser(int $userId): array
    {
        if ($this->multiProviderFunding()) {
            $providers = array_values(array_filter(
                self::PROVIDERS,
                fn(string $provider): bool => $this->providerDisplayEnabled($provider)
            ));
        } else {
            $providers = [$this->activeProvider()];
        }

        if ($providers === []) {
            return [];
        }

        $placeholders = [];
        $params = ['user_id' => $userId];
        foreach ($providers as $index => $provider) {
            $key = 'provider_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $provider;
        }

        return $this->db->query(
            'SELECT * FROM user_funding_accounts
             WHERE user_id = :user_id AND provider IN (' . implode(', ', $placeholders) . ')
             ORDER BY FIELD(provider, "katpay", "paystack", "xixapay"), id',
            $params
        );
    }

    public function primaryDisplayAccountForUser(int $userId): ?array
    {
        $accounts = $this->displayAccountsForUser($userId);
        if (!$this->multiProviderFunding()) {
            return $accounts[0] ?? null;
        }

        foreach ($accounts as $account) {
            if (($account['provider'] ?? '') === $this->activeProvider()) {
                return $account;
            }
        }

        return $accounts[0] ?? null;
    }

    public function allAccountsForUser(int $userId): array
    {
        return $this->db->query(
            'SELECT * FROM user_funding_accounts WHERE user_id = :user_id ORDER BY FIELD(provider, "katpay", "paystack", "xixapay"), id',
            ['user_id' => $userId]
        );
    }

    public function ensureActiveAccountForUser(int $userId, bool $forceRetry = false, ?int $adminId = null): array
    {
        return $this->ensureAccountForProvider($this->activeProvider(), $userId, $forceRetry, $adminId);
    }

    public function ensureAccountForProvider(string $provider, int $userId, bool $forceRetry = false, ?int $adminId = null): array
    {
        return match (strtolower(trim($provider))) {
            'katpay' => $this->katPay->ensureAccountForUser($userId, $forceRetry, $adminId),
            'paystack' => $this->paystack->ensureAccountForUser($userId, $forceRetry, $adminId),
            'xixapay' => throw new RuntimeException('XixaPay account generation requires a separate BVN/NIN flow and is not available from this button.'),
            default => throw new RuntimeException('Unsupported funding account provider.'),
        };
    }

    public function getActiveAccountForUser(int $userId): ?array
    {
        return match ($this->activeProvider()) {
            'katpay' => $this->katPay->getForUser($userId),
            'paystack' => $this->paystack->getForUser($userId),
            default => $this->db->first(
                'SELECT * FROM user_funding_accounts WHERE user_id = :user_id AND provider = :provider LIMIT 1',
                ['user_id' => $userId, 'provider' => $this->activeProvider()]
            ),
        };
    }
}
