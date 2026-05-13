<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class ProviderManager
{
    public function __construct(
        private Database $db,
        private MockVtuProvider $mockProvider,
        private AppLogger $logger,
        private SimpleCache $cache,
        private ProviderPlanService $planService
    ) {
    }

    public function activeProviders(): array
    {
        $providers = $this->db->query('SELECT * FROM provider_accounts WHERE status = "active" ORDER BY priority_order ASC, id ASC');
        return array_values(array_filter($providers, function (array $provider): bool {
            $config = $this->providerConfig($provider);
            return !empty($config['enabled']);
        }));
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
            throw new RuntimeException('No enabled provider is mapped for this service.');
        }

        $attempts = [];
        foreach ($providers as $provider) {
            try {
                $response = $this->driver($provider)->purchase($serviceSlug, $payload);
                $response = $this->normalizePurchaseResponse($response, $provider, $payload);
                $attempts[] = [
                    'provider_id' => (int) $provider['id'],
                    'provider_code' => $provider['code'],
                    'status' => $response['status'],
                    'provider_reference' => $response['provider_reference'] ?? null,
                ];

                if (in_array(($response['status'] ?? 'failed'), ['successful', 'pending'], true) || (int) $provider['supports_fallback'] !== 1) {
                    $response['attempts'] = $attempts;
                    return $response;
                }
            } catch (\Throwable $throwable) {
                $attempts[] = [
                    'provider_id' => (int) $provider['id'],
                    'provider_code' => $provider['code'],
                    'status' => 'failed',
                    'provider_reference' => null,
                    'error' => $throwable->getMessage(),
                ];
                $this->logger->warning('Provider purchase attempt failed.', [
                    'provider_code' => $provider['code'],
                    'service' => $serviceSlug,
                    'error' => $throwable->getMessage(),
                ]);
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

        $health = $this->healthForProvider($provider);
        return [
            'status' => $health['status'] === 'ready' ? 'successful' : 'failed',
            'message' => 'Connection test completed.',
            'provider' => $provider['name'],
            'driver' => $provider['driver'],
            'timestamp' => date('c'),
            'health' => $health,
        ];
    }

    public function queryTransaction(array $provider, string $reference): array
    {
        $response = $this->driver($provider)->queryTransaction($reference);
        return $this->normalizeProviderStatusResponse($response, $provider, $reference);
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

    public function providerHealthSummary(): array
    {
        $summary = [];
        foreach ($this->allProviders() as $provider) {
            try {
                $summary[] = $this->healthForProvider($provider);
            } catch (\Throwable $e) {
                $this->logger->warning('Provider health check failed gracefully.', [
                    'provider' => $provider['code'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
                $summary[] = [
                    'status' => 'unavailable',
                    'balance_amount' => 0.0,
                    'provider_code' => (string) ($provider['code'] ?? 'unknown'),
                    'provider_name' => (string) ($provider['name'] ?? 'Unknown'),
                    'threshold' => (float) ($provider['low_balance_threshold'] ?? 0),
                    'is_low_balance' => false,
                    'balance_status' => 'unavailable',
                    'sandbox' => false,
                ];
            }
        }

        return $summary;
    }

    private function driver(array $provider): VtuProviderInterface
    {
        $driver = strtolower((string) ($provider['driver'] ?? 'mock'));
        $config = $this->providerConfig($provider);

        return match ($driver) {
            'albani' => new AlbaniProvider($config, $this->logger, $this->planService),
            'smeplug' => new SmeplugProvider($config, $this->logger),
            'vtpass' => new VTpassProvider($config, $this->logger),
            'clubkonnect' => new ClubKonnectProvider($config, $this->logger),
            'alrahuzdata' => new AlrahuzDataProvider($config, $this->logger),
            'easyaccessapi' => new EasyAccessApiProvider($config, $this->logger),
            default => $this->mockProvider,
        };
    }

    private function providerConfig(array|string $provider): array
    {
        if (is_array($provider)) {
            $configKey = strtolower((string) ($provider['credentials_key'] ?: $provider['code']));
            $config = (array) config('providers.' . $configKey, []);
            if (!empty($provider['base_url'])) {
                $config['base_url'] = (string) $provider['base_url'];
            }
            $config['label'] = $config['label'] ?? (string) ($provider['name'] ?? $configKey);
            $config['driver'] = $config['driver'] ?? (string) ($provider['driver'] ?? 'mock');

            return $config;
        }

        return (array) config('providers.' . strtolower($provider), []);
    }

    private function healthForProvider(array $provider): array
    {
        $cacheKey = 'provider-health:' . strtolower((string) $provider['code']);
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $driver = $this->driver($provider);
            $health = $driver->healthCheck();
            $balance = $driver->checkBalance();
        } catch (\Throwable $e) {
            // Provider is disabled or misconfigured — return safe defaults
            $this->logger->warning('Provider health/balance check skipped.', [
                'provider' => $provider['code'] ?? 'unknown',
                'reason' => $e->getMessage(),
            ]);
            $latest = $this->latestBalanceLog((int) $provider['id']);
            $result = [
                'status' => 'unavailable',
                'balance_amount' => (float) ($latest['balance_amount'] ?? 0),
                'provider_code' => (string) $provider['code'],
                'provider_name' => (string) $provider['name'],
                'threshold' => (float) $provider['low_balance_threshold'],
                'is_low_balance' => false,
                'balance_status' => 'unavailable',
                'sandbox' => false,
                'error' => $e->getMessage(),
            ];
            $this->cache->put($cacheKey, $result, 60);
            return $result;
        }

        if (($balance['status'] ?? '') === 'successful' && isset($balance['balance'])) {
            $this->logBalance(
                (int) $provider['id'],
                (float) $balance['balance'],
                'provider_api',
                'Fetched from provider health check'
            );
        }

        $latest = $this->latestBalanceLog((int) $provider['id']);
        $health['balance_amount'] = (float) ($latest['balance_amount'] ?? 0);
        $health['provider_code'] = (string) $provider['code'];
        $health['provider_name'] = (string) $provider['name'];
        $health['threshold'] = (float) $provider['low_balance_threshold'];
        $health['is_low_balance'] = $health['balance_amount'] <= (float) $provider['low_balance_threshold'];
        $health['balance_status'] = $balance['status'] ?? 'unknown';
        $this->cache->put($cacheKey, $health, 60);

        return $health;
    }

    private function normalizePurchaseResponse(array $response, array $provider, array $payload): array
    {
        $normalized = $this->normalizeProviderStatusResponse($response, $provider, (string) ($payload['reference'] ?? ''));
        $normalized['amount'] = (float) ($response['amount'] ?? $payload['amount'] ?? 0);
        $normalized['recipient'] = (string) ($response['recipient'] ?? $payload['recipient'] ?? $payload['phone'] ?? '');

        return $normalized;
    }

    private function normalizeProviderStatusResponse(array $response, array $provider, string $fallbackReference): array
    {
        $status = strtolower((string) ($response['status'] ?? 'failed'));
        $normalizedStatus = match ($status) {
            'successful', 'success', 'completed' => 'successful',
            'pending', 'processing', 'queued', 'timeout' => 'pending',
            default => 'failed',
        };

        $response['status'] = $normalizedStatus;
        $response['provider_reference'] = $response['provider_reference'] ?? ($fallbackReference !== '' ? $fallbackReference : null);
        $response['provider_account'] = $provider;
        $response['raw'] = is_array($response['raw'] ?? null) ? $response['raw'] : ['message' => 'Provider did not return a structured response.'];

        return $response;
    }
}
