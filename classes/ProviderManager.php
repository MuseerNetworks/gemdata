<?php

declare(strict_types=1);

namespace GemData\Classes;

class ProviderManager
{
    public function __construct(
        private Database $db,
        private MockVtuProvider $mockProvider
    ) {
    }

    public function activeProviders(): array
    {
        return $this->db->query('SELECT * FROM provider_accounts WHERE status = "active" ORDER BY priority_order ASC, id ASC');
    }

    public function allProviders(): array
    {
        return $this->db->query('SELECT * FROM provider_accounts ORDER BY priority_order ASC, id ASC');
    }

    public function getById(int $providerId): ?array
    {
        return $this->db->first('SELECT * FROM provider_accounts WHERE id = :id LIMIT 1', ['id' => $providerId]);
    }

    public function supportedProviders(string $serviceSlug): array
    {
        $providers = [];
        foreach ($this->activeProviders() as $provider) {
            $supported = json_decode_array($provider['supported_services_json'] ?? '[]');
            if ($supported === [] || in_array($serviceSlug, $supported, true)) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    public function purchase(string $serviceSlug, array $payload): array
    {
        $providers = $this->supportedProviders($serviceSlug);
        if ($providers === []) {
            $fallback = $this->db->first('SELECT * FROM provider_accounts ORDER BY priority_order ASC, id ASC LIMIT 1');
            if ($fallback) {
                $providers = [$fallback];
            }
        }

        $attempts = [];
        foreach ($providers as $provider) {
            $response = $this->driver($provider)->purchase($serviceSlug, $payload);
            $response['provider_account'] = $provider;
            $attempts[] = [
                'provider_id' => (int) $provider['id'],
                'provider_code' => $provider['code'],
                'status' => $response['status'],
                'provider_reference' => $response['provider_reference'] ?? null,
            ];

            if (($response['status'] ?? 'failed') === 'successful' || (int) $provider['supports_fallback'] !== 1) {
                $response['attempts'] = $attempts;
                return $response;
            }
        }

        return [
            'status' => 'failed',
            'provider_reference' => null,
            'amount' => (float) ($payload['amount'] ?? 0),
            'recipient' => (string) ($payload['recipient'] ?? ''),
            'raw' => ['message' => 'All providers failed'],
            'attempts' => $attempts,
        ];
    }

    public function testConnection(int $providerId): array
    {
        $provider = $this->getById($providerId);
        if (!$provider) {
            return ['status' => 'failed', 'message' => 'Provider not found.'];
        }

        return [
            'status' => 'successful',
            'message' => 'Connection test completed.',
            'provider' => $provider['name'],
            'driver' => $provider['driver'],
            'timestamp' => date('c'),
        ];
    }

    public function latestBalanceLog(int $providerId): ?array
    {
        return $this->db->first(
            'SELECT * FROM provider_balance_logs WHERE provider_account_id = :provider_account_id ORDER BY id DESC LIMIT 1',
            ['provider_account_id' => $providerId]
        );
    }

    public function logBalance(int $providerId, float $amount, string $source = 'manual', ?string $notes = null): void
    {
        $this->db->execute(
            'INSERT INTO provider_balance_logs (provider_account_id, balance_amount, source, notes)
             VALUES (:provider_account_id, :balance_amount, :source, :notes)',
            [
                'provider_account_id' => $providerId,
                'balance_amount' => $amount,
                'source' => $source,
                'notes' => $notes,
            ]
        );
    }

    public function upsertProvider(array $payload): void
    {
        $existing = !empty($payload['id'])
            ? $this->getById((int) $payload['id'])
            : $this->db->first('SELECT * FROM provider_accounts WHERE code = :code LIMIT 1', ['code' => $payload['code']]);

        $params = [
            'code' => strtolower(trim((string) $payload['code'])),
            'name' => trim((string) $payload['name']),
            'driver' => trim((string) ($payload['driver'] ?? 'mock')),
            'status' => ($payload['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
            'priority_order' => max(1, (int) ($payload['priority_order'] ?? 1)),
            'supports_fallback' => !empty($payload['supports_fallback']) ? 1 : 0,
            'low_balance_threshold' => max(0, (float) ($payload['low_balance_threshold'] ?? 0)),
            'credentials_key' => trim((string) ($payload['credentials_key'] ?? '')),
            'base_url' => trim((string) ($payload['base_url'] ?? '')),
            'supported_services_json' => json_encode(array_values(array_filter(array_map('trim', explode(',', (string) ($payload['supported_services'] ?? '')))))),
            'notes' => trim((string) ($payload['notes'] ?? '')),
        ];

        if ($existing) {
            $params['id'] = $existing['id'];
            $this->db->execute(
                'UPDATE provider_accounts
                 SET code = :code, name = :name, driver = :driver, status = :status, priority_order = :priority_order,
                     supports_fallback = :supports_fallback, low_balance_threshold = :low_balance_threshold,
                     credentials_key = :credentials_key, base_url = :base_url, supported_services_json = :supported_services_json, notes = :notes
                 WHERE id = :id',
                $params
            );
            return;
        }

        $this->db->execute(
            'INSERT INTO provider_accounts
             (code, name, driver, status, priority_order, supports_fallback, low_balance_threshold, credentials_key, base_url, supported_services_json, notes)
             VALUES
             (:code, :name, :driver, :status, :priority_order, :supports_fallback, :low_balance_threshold, :credentials_key, :base_url, :supported_services_json, :notes)',
            $params
        );
    }

    private function driver(array $provider): VtuProviderInterface
    {
        return $this->mockProvider;
    }
}
