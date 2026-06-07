<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class ProviderManager
{
    public function __construct(
        private Database $db,
        private AppLogger $logger,
        private SimpleCache $cache,
        private ProviderPlanService $planService,
        private ProviderRouter $router
    ) {
    }

    public function activeProviders(): array
    {
        $providers = $this->db->query('SELECT * FROM provider_accounts WHERE status = "active" ORDER BY priority_order ASC, id ASC');
        return array_values(array_filter($providers, function (array $provider): bool {
            if ($this->recoverExpiredCircuit($provider)) {
                $provider['circuit_breaker_status'] = 'half_open';
            }
            $config = $this->providerConfig($provider);
            return !empty($config['enabled']) && !$this->isCircuitOpen($provider);
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
        $requestReference = trim((string) ($payload['reference'] ?? ''));
        if ($requestReference === '') {
            throw new RuntimeException('Provider request reference is required for idempotent fulfillment.');
        }

        $routing = $this->router->route($serviceSlug, $payload, $this->supportedProviders($serviceSlug));
        $providers = $routing['providers'];
        $routingSetting = $routing['setting'];
        if ($providers === []) {
            throw new RuntimeException($this->providerSelectionFailureMessage($serviceSlug, $routing['diagnostics'] ?? []));
        }

        $attempts = [];
        $attemptNumber = 0;
        foreach ($providers as $provider) {
            $attemptNumber++;
            $startedAt = microtime(true);
            try {
                $providerPayload = $payload;
                $providerPayload['_provider_account'] = $provider;
                if (isset($provider['_route_plan_mapping'])) {
                    $providerPayload['_route_plan_mapping'] = $provider['_route_plan_mapping'];
                }
                $response = $this->driver($provider)->purchase($serviceSlug, $providerPayload);
                $response = $this->normalizePurchaseResponse($response, $provider, $payload);
                $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);
                $attempts[] = [
                    'provider_id' => (int) $provider['id'],
                    'provider_code' => $provider['code'],
                    'status' => $response['status'],
                    'provider_reference' => $response['provider_reference'] ?? null,
                    'routing_mode' => $routingSetting['routing_mode'] ?? 'priority',
                    'attempt_number' => $attemptNumber,
                    'response_time_ms' => $responseTimeMs,
                ];
                $attemptError = $this->providerAttemptErrorMessage($response);
                $this->recordProviderAttempt((int) ($payload['transaction_id'] ?? 0), $provider, $routingSetting, $attemptNumber, $response['status'], (string) ($payload['reference'] ?? ''), $response['provider_reference'] ?? null, $responseTimeMs, $attemptError, $response);
                $this->updateProviderOutcome($provider, $response['status'], $attemptError);

                $fallbackAllowed = !empty($routingSetting['fallback_enabled']) && (int) $provider['supports_fallback'] === 1;
                if (in_array(($response['status'] ?? 'failed'), ['successful', 'pending'], true) || !$fallbackAllowed) {
                    $response['attempts'] = $attempts;
                    return $response;
                }
            } catch (\Throwable $throwable) {
                $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);
                $attempts[] = [
                    'provider_id' => (int) $provider['id'],
                    'provider_code' => $provider['code'],
                    'status' => 'failed',
                    'provider_reference' => null,
                    'error' => $throwable->getMessage(),
                    'routing_mode' => $routingSetting['routing_mode'] ?? 'priority',
                    'attempt_number' => $attemptNumber,
                    'response_time_ms' => $responseTimeMs,
                ];
                $this->recordProviderAttempt((int) ($payload['transaction_id'] ?? 0), $provider, $routingSetting, $attemptNumber, 'failed', (string) ($payload['reference'] ?? ''), null, $responseTimeMs, $throwable->getMessage(), []);
                $this->updateProviderOutcome($provider, 'failed', $throwable->getMessage());
                $this->logger->warning('Provider purchase attempt failed.', [
                    'provider_code' => $provider['code'],
                    'service' => $serviceSlug,
                    'error' => $throwable->getMessage(),
                ]);

                if (empty($routingSetting['fallback_enabled']) || (int) $provider['supports_fallback'] !== 1) {
                    break;
                }
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

    public function routingSettings(): array
    {
        return $this->router->allRoutingSettings();
    }

    public function routingSetting(string $serviceSlug): array
    {
        return $this->router->routingSetting($serviceSlug);
    }

    public function upsertRoutingSetting(array $payload, ?int $adminId = null): array
    {
        return $this->router->upsertRoutingSetting($payload, $adminId);
    }

    private function providerSelectionFailureMessage(string $serviceSlug, array $diagnostics): string
    {
        $excluded = $diagnostics['excluded'] ?? [];
        foreach ($excluded as $item) {
            if (($item['reason'] ?? '') === 'provider_success_below_threshold') {
                $code = (string) ($item['provider_code'] ?? 'provider');
                $minimum = (float) ($item['minimum_success_rate'] ?? 0);
                $rate = (float) ($item['success_rate'] ?? 0);
                return sprintf(
                    'No eligible provider found for %s; %s excluded by success threshold %.2f > current rate %.2f.',
                    $serviceSlug,
                    $code,
                    $minimum,
                    $rate
                );
            }
        }

        if ((int) ($diagnostics['candidate_count'] ?? 0) === 0) {
            return 'No enabled provider is mapped for ' . $serviceSlug . '.';
        }

        return 'No eligible provider found for ' . $serviceSlug . '.';
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

        if ($this->db->columnExists('provider_accounts', 'current_balance')) {
            $this->db->execute(
                'UPDATE provider_accounts
                 SET current_balance = :current_balance, balance_refreshed_at = NOW()
                 WHERE id = :id',
                ['current_balance' => $amount, 'id' => $providerId]
            );
        }
    }

    public function upsertProvider(array $payload): void
    {
        $existing = !empty($payload['id'])
            ? $this->getById((int) $payload['id'])
            : $this->db->first('SELECT * FROM provider_accounts WHERE code = :code LIMIT 1', ['code' => $payload['code']]);

        $driver = strtolower(trim((string) ($payload['driver'] ?? 'albani')));
        if (!RealProviderRegistry::isAllowedDriver($driver)) {
            throw new RuntimeException(sprintf('Provider driver "%s" is not available for production use.', $driver !== '' ? $driver : 'unknown'));
        }

        $params = [
            'code' => strtolower(trim((string) $payload['code'])),
            'name' => trim((string) $payload['name']),
            'driver' => $driver,
            'status' => $this->normalizeProviderStatus((string) ($payload['status'] ?? 'active')),
            'priority_order' => max(1, (int) ($payload['priority_order'] ?? 1)),
            'supports_fallback' => !empty($payload['supports_fallback']) ? 1 : 0,
            'low_balance_threshold' => max(0, (float) ($payload['low_balance_threshold'] ?? 0)),
            'credentials_key' => trim((string) ($payload['credentials_key'] ?? '')),
            'base_url' => trim((string) ($payload['base_url'] ?? '')),
            'supported_services_json' => json_encode(array_values(array_filter(array_map('trim', explode(',', (string) ($payload['supported_services'] ?? '')))))),
            'notes' => trim((string) ($payload['notes'] ?? '')),
        ];

        $optional = [];
        foreach ([
            'cheapest_routing_enabled' => !empty($payload['cheapest_routing_enabled']) ? 1 : 0,
            'sandbox_mode' => !empty($payload['sandbox_mode']) ? 1 : 0,
            'auto_disable_enabled' => !empty($payload['auto_disable_enabled']) ? 1 : 0,
            'failure_threshold' => max(1, (int) ($payload['failure_threshold'] ?? 5)),
            'minimum_success_rate' => max(0, min(100, (float) ($payload['minimum_success_rate'] ?? 80))),
            'health_score' => max(0, min(100, (float) ($payload['health_score'] ?? 100))),
        ] as $column => $value) {
            if ($this->db->columnExists('provider_accounts', $column)) {
                $optional[$column] = $value;
            }
        }
        $params = array_merge($params, $optional);

        if ($existing) {
            $params['id'] = $existing['id'];
            $setClauses = [
                'code = :code',
                'name = :name',
                'driver = :driver',
                'status = :status',
                'priority_order = :priority_order',
                'supports_fallback = :supports_fallback',
                'low_balance_threshold = :low_balance_threshold',
                'credentials_key = :credentials_key',
                'base_url = :base_url',
                'supported_services_json = :supported_services_json',
                'notes = :notes',
            ];
            foreach (array_keys($optional) as $column) {
                $setClauses[] = $column . ' = :' . $column;
            }
            if ($params['status'] === 'archived' && $this->db->columnExists('provider_accounts', 'archived_at')) {
                $setClauses[] = 'archived_at = COALESCE(archived_at, NOW())';
            } elseif ($this->db->columnExists('provider_accounts', 'archived_at')) {
                $setClauses[] = 'archived_at = NULL';
            }
            $this->db->execute(
                'UPDATE provider_accounts
                 SET ' . implode(', ', $setClauses) . '
                 WHERE id = :id',
                $params
            );
            return;
        }

        $columns = array_merge([
            'code',
            'name',
            'driver',
            'status',
            'priority_order',
            'supports_fallback',
            'low_balance_threshold',
            'credentials_key',
            'base_url',
            'supported_services_json',
            'notes',
        ], array_keys($optional));

        $this->db->execute(
            'INSERT INTO provider_accounts
             (' . implode(', ', $columns) . ')
             VALUES
             (:' . implode(', :', $columns) . ')',
            $params
        );
    }

    public function updateProviderStatus(int $providerId, string $status): void
    {
        $status = $this->normalizeProviderStatus($status);
        $extra = '';
        if ($this->db->columnExists('provider_accounts', 'archived_at')) {
            $extra = $status === 'archived'
                ? ', archived_at = COALESCE(archived_at, NOW())'
                : ', archived_at = NULL';
        }
        $this->db->execute(
            'UPDATE provider_accounts SET status = :status' . $extra . ' WHERE id = :id',
            ['status' => $status, 'id' => $providerId]
        );
    }

    public function resetCircuitBreaker(int $providerId): void
    {
        if (!$this->db->columnExists('provider_accounts', 'circuit_breaker_status')) {
            return;
        }

        $this->db->execute(
            'UPDATE provider_accounts
             SET circuit_breaker_status = "closed", circuit_breaker_opened_at = NULL, circuit_breaker_until = NULL, last_api_error = NULL
             WHERE id = :id',
            ['id' => $providerId]
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
                    'sandbox' => !empty($provider['sandbox_mode']),
                    'balance_refreshed_at' => $provider['balance_refreshed_at'] ?? null,
                    'circuit_breaker_status' => $provider['circuit_breaker_status'] ?? 'closed',
                ];
            }
        }

        return $summary;
    }

    private function recordProviderAttempt(
        int $transactionId,
        array $provider,
        array $routingSetting,
        int $attemptNumber,
        string $status,
        string $requestReference,
        ?string $providerReference,
        ?int $responseTimeMs,
        ?string $errorMessage,
        array $meta
    ): void {
        if (!$this->db->tableExists('provider_transaction_attempts')) {
            return;
        }

        $normalizedStatus = in_array($status, ['pending', 'processing', 'successful', 'failed', 'skipped'], true) ? $status : 'failed';
        $safeMeta = $this->redactProviderMeta($meta);
        $rawMeta = is_array($safeMeta['raw'] ?? null) ? $safeMeta['raw'] : [];
        $requestMeta = is_array($rawMeta['request'] ?? null)
            ? $rawMeta['request']
            : (is_array($safeMeta['request'] ?? null) ? $safeMeta['request'] : null);
        $responseMeta = is_array($rawMeta['response'] ?? null)
            ? $rawMeta['response']
            : (is_array($safeMeta['response'] ?? null) ? $safeMeta['response'] : null);
        if ($responseMeta === null && $rawMeta !== []) {
            $responseMeta = [
                'http_code' => $rawMeta['http_code'] ?? null,
                'curl_errno' => $rawMeta['curl_errno'] ?? null,
                'curl_error' => $rawMeta['curl_error'] ?? null,
                'latency_ms' => $rawMeta['latency_ms'] ?? null,
                'json' => $rawMeta['json'] ?? null,
                'body' => $rawMeta['body'] ?? null,
                'message' => $rawMeta['message'] ?? null,
            ];
        }

        $this->db->safeExecute(
            'INSERT IGNORE INTO provider_transaction_attempts (
                transaction_id, provider_account_id, provider_code, routing_mode, attempt_number,
                status, request_reference, provider_reference, response_time_ms, error_message, meta_json
             ) VALUES (
                :transaction_id, :provider_account_id, :provider_code, :routing_mode, :attempt_number,
                :status, :request_reference, :provider_reference, :response_time_ms, :error_message, :meta_json
             )',
            [
                'transaction_id' => $transactionId > 0 ? $transactionId : null,
                'provider_account_id' => (int) $provider['id'],
                'provider_code' => (string) $provider['code'],
                'routing_mode' => (string) ($routingSetting['routing_mode'] ?? 'priority'),
                'attempt_number' => $attemptNumber,
                'status' => $normalizedStatus,
                'request_reference' => $requestReference !== '' ? $requestReference : null,
                'provider_reference' => $providerReference,
                'response_time_ms' => $responseTimeMs,
                'error_message' => $errorMessage !== null ? substr($errorMessage, 0, 255) : null,
                'meta_json' => json_encode([
                    'fallback_enabled' => !empty($routingSetting['fallback_enabled']),
                    'minimum_success_rate' => (float) ($routingSetting['minimum_success_rate'] ?? 80),
                    'sandbox' => !empty($provider['sandbox_mode']),
                    'request' => $requestMeta,
                    'response' => $responseMeta,
                    'provider_result' => $safeMeta,
                ], JSON_UNESCAPED_SLASHES),
            ]
        );
    }

    private function providerAttemptErrorMessage(array $response): ?string
    {
        $status = strtolower((string) ($response['status'] ?? ''));
        if (in_array($status, ['successful', 'pending', 'processing'], true)) {
            return null;
        }

        $raw = is_array($response['raw'] ?? null) ? $response['raw'] : [];
        $request = is_array($raw['request'] ?? null) ? $raw['request'] : [];
        $method = strtoupper((string) ($request['method'] ?? ''));
        $url = (string) ($request['url'] ?? '');
        $httpCode = (int) ($raw['http_code'] ?? ($raw['response']['http_code'] ?? 0));
        $curlError = trim((string) ($raw['curl_error'] ?? ($raw['response']['curl_error'] ?? '')));
        $message = trim((string) ($raw['message'] ?? 'Provider returned failed status.'));

        $parts = [];
        if ($httpCode > 0) {
            $parts[] = 'HTTP ' . $httpCode;
        }
        if ($method !== '') {
            $parts[] = $method;
        }
        if ($url !== '') {
            $parts[] = $url;
        }

        $prefix = trim(implode(' ', $parts));
        $detail = $curlError !== '' ? $curlError : $message;
        $error = $prefix !== '' ? $prefix . ': ' . $detail : $detail;

        return substr($this->logger->sanitizeProviderMeta(['error' => $error], 255)['error'], 0, 255);
    }

    private function updateProviderOutcome(array $provider, string $status, ?string $errorMessage): void
    {
        if (!$this->db->columnExists('provider_accounts', 'last_api_error')) {
            return;
        }

        $providerId = (int) $provider['id'];
        if (in_array($status, ['successful', 'pending'], true)) {
            $this->db->safeExecute(
                'UPDATE provider_accounts
                 SET last_successful_at = CASE WHEN :status = "successful" THEN NOW() ELSE last_successful_at END,
                     last_api_error = NULL,
                     circuit_breaker_status = CASE WHEN circuit_breaker_status = "half_open" THEN "closed" ELSE circuit_breaker_status END,
                     circuit_breaker_opened_at = CASE WHEN circuit_breaker_status = "closed" THEN NULL ELSE circuit_breaker_opened_at END,
                     circuit_breaker_until = CASE WHEN circuit_breaker_status = "closed" THEN NULL ELSE circuit_breaker_until END
                 WHERE id = :id',
                ['status' => $status, 'id' => $providerId]
            );
            return;
        }

        $this->db->safeExecute(
            'UPDATE provider_accounts SET last_api_error = :error WHERE id = :id',
            ['error' => $errorMessage !== null ? substr($errorMessage, 0, 255) : 'Provider returned failed status.', 'id' => $providerId]
        );

        if (empty($provider['auto_disable_enabled']) || !$this->db->columnExists('provider_accounts', 'circuit_breaker_status')) {
            return;
        }

        $threshold = max(1, (int) ($provider['failure_threshold'] ?? 5));
        $recent = $this->db->safeFirst(
            'SELECT COUNT(*) AS total
             FROM provider_transaction_attempts
             WHERE provider_account_id = :provider_id
               AND status = "failed"
               AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)',
            ['provider_id' => $providerId]
        );

        if ((int) ($recent['total'] ?? 0) < $threshold) {
            return;
        }

        $this->db->safeExecute(
            'UPDATE provider_accounts
             SET circuit_breaker_status = "open",
                 circuit_breaker_opened_at = NOW(),
                 circuit_breaker_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
             WHERE id = :id',
            ['id' => $providerId]
        );

        $this->db->safeExecute(
            'INSERT INTO activity_logs (actor_type, actor_id, action, description, meta_json)
             VALUES (:actor_type, :actor_id, :action, :description, :meta_json)',
            [
                'actor_type' => 'system',
                'actor_id' => 0,
                'action' => 'provider_circuit_opened',
                'description' => 'Provider circuit breaker opened after repeated failures.',
                'meta_json' => json_encode([
                    'provider_id' => $providerId,
                    'provider_code' => $provider['code'] ?? 'unknown',
                    'failure_threshold' => $threshold,
                    'cooldown_minutes' => 15,
                ]),
            ]
        );

        $this->logger->warning('Provider circuit breaker opened after repeated failures.', [
            'provider_id' => $providerId,
            'provider_code' => $provider['code'] ?? 'unknown',
            'failure_threshold' => $threshold,
        ]);
    }

    private function recoverExpiredCircuit(array $provider): bool
    {
        if (($provider['circuit_breaker_status'] ?? 'closed') !== 'open') {
            return false;
        }

        $until = trim((string) ($provider['circuit_breaker_until'] ?? ''));
        if ($until === '' || strtotime($until) > time()) {
            return false;
        }

        $this->db->safeExecute(
            'UPDATE provider_accounts
             SET circuit_breaker_status = "half_open"
             WHERE id = :id AND circuit_breaker_status = "open"',
            ['id' => (int) $provider['id']]
        );
        return true;
    }

    private function redactProviderMeta(array $meta): array
    {
        return $this->logger->sanitizeProviderMeta($meta);
    }

    private function driver(array $provider): VtuProviderInterface
    {
        $driver = strtolower((string) ($provider['driver'] ?? ''));
        $config = $this->providerConfig($provider);

        return match ($driver) {
            'albani' => new AlbaniProvider($config, $this->logger, $this->planService),
            'alrahuzdata' => new AlrahuzDataProvider($config, $this->logger, $this->planService),
            'abbpantami' => new AbbPantamiProvider($config, $this->logger, $this->planService),
            'cheapdatahub' => new CheapDataHubProvider($config, $this->logger, $this->planService),
            default => throw new RuntimeException(sprintf('Provider driver "%s" is not registered as a real production driver.', $driver !== '' ? $driver : 'unknown')),
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
            $config['driver'] = $config['driver'] ?? (string) ($provider['driver'] ?? '');
            $config['sandbox'] = !empty($provider['sandbox_mode']) || !empty($config['sandbox']);

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

        if (($provider['status'] ?? 'inactive') !== 'active') {
            $latest = $this->latestBalanceLog((int) $provider['id']);
            $result = [
                'status' => (string) ($provider['status'] ?? 'inactive'),
                'balance_amount' => (float) ($provider['current_balance'] ?? $latest['balance_amount'] ?? 0),
                'provider_code' => (string) $provider['code'],
                'provider_name' => (string) $provider['name'],
                'threshold' => (float) $provider['low_balance_threshold'],
                'is_low_balance' => false,
                'balance_status' => 'not_checked',
                'sandbox' => !empty($provider['sandbox_mode']),
                'health_score' => (float) ($provider['health_score'] ?? 0),
                'balance_refreshed_at' => $provider['balance_refreshed_at'] ?? ($latest['created_at'] ?? null),
                'circuit_breaker_status' => $provider['circuit_breaker_status'] ?? 'closed',
            ];
            $this->cache->put($cacheKey, $result, 60);
            return $result;
        }

        try {
            $startedAt = microtime(true);
            $driver = $this->driver($provider);
            $health = $driver->healthCheck();
            $balance = $driver->checkBalance();
            $health['response_time_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
        } catch (\Throwable $e) {
            // Provider is disabled or misconfigured — return safe defaults
            $this->logger->warning('Provider health/balance check skipped.', [
                'provider' => $provider['code'] ?? 'unknown',
                'reason' => $e->getMessage(),
            ]);
            $latest = $this->latestBalanceLog((int) $provider['id']);
            $result = [
                'status' => 'unavailable',
                'balance_amount' => (float) ($provider['current_balance'] ?? $latest['balance_amount'] ?? 0),
                'provider_code' => (string) $provider['code'],
                'provider_name' => (string) $provider['name'],
                'threshold' => (float) $provider['low_balance_threshold'],
                'is_low_balance' => false,
                'balance_status' => 'unavailable',
                'sandbox' => !empty($provider['sandbox_mode']),
                'health_score' => (float) ($provider['health_score'] ?? 0),
                'balance_refreshed_at' => $provider['balance_refreshed_at'] ?? null,
                'circuit_breaker_status' => $provider['circuit_breaker_status'] ?? 'closed',
                'error' => $e->getMessage(),
            ];
            $this->recordHealthSnapshot((int) $provider['id'], $result);
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
        $health['balance_amount'] = (float) ($provider['current_balance'] ?? $latest['balance_amount'] ?? 0);
        $health['provider_code'] = (string) $provider['code'];
        $health['provider_name'] = (string) $provider['name'];
        $health['threshold'] = (float) $provider['low_balance_threshold'];
        $health['is_low_balance'] = $health['balance_amount'] <= (float) $provider['low_balance_threshold'];
        $health['balance_status'] = $balance['status'] ?? 'unknown';
        $health['sandbox'] = !empty($provider['sandbox_mode']);
        $health['health_score'] = (float) ($provider['health_score'] ?? (($health['status'] ?? '') === 'ready' ? 100 : 0));
        $health['balance_refreshed_at'] = $provider['balance_refreshed_at'] ?? ($latest['created_at'] ?? null);
        $health['circuit_breaker_status'] = $provider['circuit_breaker_status'] ?? 'closed';
        $this->recordHealthSnapshot((int) $provider['id'], $health);
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

    private function normalizeProviderStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['active', 'inactive', 'maintenance', 'archived'], true) ? $status : 'inactive';
    }

    private function isCircuitOpen(array $provider): bool
    {
        if (($provider['circuit_breaker_status'] ?? 'closed') !== 'open') {
            return false;
        }

        $until = trim((string) ($provider['circuit_breaker_until'] ?? ''));
        return $until === '' || strtotime($until) > time();
    }

    private function recordHealthSnapshot(int $providerId, array $health): void
    {
        if (!$this->db->tableExists('provider_health_logs')) {
            return;
        }

        $columns = ['provider_account_id', 'status', 'health_score', 'success_rate', 'balance_amount', 'error_message'];
        $params = [
            'provider_account_id' => $providerId,
            'status' => (string) ($health['status'] ?? 'unknown'),
            'health_score' => (float) ($health['health_score'] ?? 0),
            'success_rate' => isset($health['success_rate']) ? (float) $health['success_rate'] : null,
            'balance_amount' => isset($health['balance_amount']) ? (float) $health['balance_amount'] : null,
            'error_message' => isset($health['error']) ? substr((string) $health['error'], 0, 255) : null,
        ];
        if ($this->db->columnExists('provider_health_logs', 'response_time_ms')) {
            $columns[] = 'response_time_ms';
            $params['response_time_ms'] = isset($health['response_time_ms']) ? (int) $health['response_time_ms'] : null;
        }

        $this->db->safeExecute(
            'INSERT INTO provider_health_logs (' . implode(', ', $columns) . ')
             VALUES (:' . implode(', :', $columns) . ')',
            $params
        );
    }
}
