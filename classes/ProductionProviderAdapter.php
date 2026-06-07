<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

abstract class ProductionProviderAdapter extends AbstractProviderAdapter
{
    private const TRANSIENT_HTTP_CODES = [408, 425, 429, 500, 502, 503, 504];

    public function __construct(
        array $config,
        AppLogger $logger,
        protected ProviderPlanService $planService
    ) {
        parent::__construct($config, $logger);
    }

    public function isConfigured(): bool
    {
        return trim((string) ($this->config['base_url'] ?? '')) !== ''
            && trim($this->credential()) !== '';
    }

    public function healthCheck(): array
    {
        $health = parent::healthCheck();
        if (($health['status'] ?? '') !== 'ready') {
            return $health;
        }

        $balance = $this->checkBalance();
        $health['status'] = ($balance['status'] ?? 'failed') === 'successful' ? 'ready' : 'failed';
        $health['balance'] = $balance['balance'] ?? null;
        $health['currency'] = $balance['currency'] ?? 'NGN';
        $health['balance_raw'] = $balance['raw'] ?? [];

        return $health;
    }

    protected function credential(): string
    {
        return trim((string) ($this->config['api_key'] ?? $this->config['token'] ?? ''));
    }

    protected function bearerHeaders(): array
    {
        return ['Authorization: Bearer ' . $this->credential()];
    }

    protected function tokenHeaders(): array
    {
        return ['Authorization: Token ' . $this->credential()];
    }

    protected function request(string $method, string $path, array $payload = [], string $operation = '', bool $retryTransient = true, array $query = []): array
    {
        $timeout = max(5, (int) ($this->config['timeout_seconds'] ?? 20));
        $retryCount = max(0, (int) ($this->config['retry_count'] ?? 1));
        $requestId = $this->logger->requestId();
        $url = $this->buildUrl($path, $query);
        $headers = array_merge([
            'Accept: application/json',
            'X-Request-Id: ' . $requestId,
        ], $this->authHeaders());

        $attempt = 0;
        $maxAttempts = 1 + ($retryTransient ? $retryCount : 0);
        $lastResult = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $startedAt = microtime(true);
            $body = strtoupper($method) === 'POST' || strtoupper($method) === 'PUT'
                ? json_encode($payload, JSON_UNESCAPED_SLASHES)
                : null;

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
                CURLOPT_HTTPHEADER => array_merge($headers, $body !== null ? ['Content-Type: application/json'] : []),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $responseBody = curl_exec($ch);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            curl_close($ch);

            $decoded = is_string($responseBody) && $responseBody !== '' ? json_decode($responseBody, true) : null;
            $transient = in_array($httpCode, self::TRANSIENT_HTTP_CODES, true)
                || in_array($curlErrno, [6, 7, 28, 35, 52, 56], true);
            $ok = $curlErrno === 0 && $httpCode >= 200 && $httpCode < 300 && is_array($decoded);
            $bodySnippet = is_string($responseBody) ? substr($responseBody, 0, 4000) : null;

            $lastResult = [
                'ok' => $ok,
                'transient' => $transient,
                'http_code' => $httpCode,
                'curl_errno' => $curlErrno,
                'curl_error' => $curlError !== '' ? $curlError : null,
                'latency_ms' => $latencyMs,
                'json' => $decoded,
                'body' => $bodySnippet,
                'message' => $ok ? 'Provider request completed.' : ($curlError !== '' ? 'Provider request failed at transport layer.' : 'Provider returned an invalid or unsuccessful response.'),
                'request' => [
                    'method' => strtoupper($method),
                    'path' => $path,
                    'url' => $url,
                    'payload' => $this->logger->sanitizeProviderMeta($payload),
                ],
                'response' => [
                    'http_code' => $httpCode,
                    'curl_errno' => $curlErrno,
                    'curl_error' => $curlError !== '' ? $curlError : null,
                    'latency_ms' => $latencyMs,
                    'transient' => $transient,
                    'json' => is_array($decoded) ? $this->logger->sanitizeProviderMeta($decoded) : null,
                    'body' => $bodySnippet !== null ? $this->logger->sanitizeProviderMeta(['body' => $bodySnippet])['body'] : null,
                ],
            ];

            $this->logProviderRequest($operation !== '' ? $operation : trim($path, '/'), strtoupper($method), $path, $payload, $lastResult, $attempt);

            if ($ok || !$retryTransient || !$transient || $attempt >= $maxAttempts) {
                break;
            }

            usleep(250000 * $attempt);
        }

        return $lastResult ?? [
            'ok' => false,
            'transient' => false,
            'http_code' => 0,
            'curl_errno' => 0,
            'curl_error' => null,
            'latency_ms' => 0,
            'json' => null,
            'body' => null,
            'message' => 'Provider request did not execute.',
        ];
    }

    abstract protected function authHeaders(): array;

    protected function buildUrl(string $path, array $query = []): string
    {
        $url = rtrim((string) ($this->config['base_url'] ?? ''), '/') . '/' . ltrim($path, '/');
        $query = array_filter($query, static fn($value): bool => $value !== null && $value !== '');
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        return $url;
    }

    protected function purchaseResult(array $result, string $fallbackReference, float $amount, string $recipient, array $referenceKeys = []): array
    {
        if (($result['ok'] ?? false) !== true) {
            return [
                'status' => ($result['transient'] ?? false) ? 'pending' : 'failed',
                'provider_reference' => $fallbackReference !== '' ? $fallbackReference : null,
                'amount' => $amount,
                'recipient' => $recipient,
                'raw' => $result,
            ];
        }

        $decoded = is_array($result['json'] ?? null) ? $result['json'] : [];
        $providerReference = $this->extractStringValue($decoded, array_merge($referenceKeys, [
            'provider_reference',
            'reference',
            'transaction_reference',
            'transaction_id',
            'id',
        ])) ?: $fallbackReference;

        return [
            'status' => $this->normalizeStatus($decoded),
            'provider_reference' => $providerReference !== '' ? $providerReference : null,
            'amount' => $amount,
            'recipient' => $recipient,
            'raw' => $decoded + [
                'request' => $result['request'] ?? null,
                'response' => $result['response'] ?? null,
            ],
        ];
    }

    protected function unsupportedService(string $serviceSlug): array
    {
        return [
            'status' => 'failed',
            'provider_reference' => null,
            'raw' => [
                'provider' => $this->code(),
                'message' => sprintf('%s does not have a confirmed %s endpoint in the reviewed provider documentation.', $this->code(), $serviceSlug),
            ],
        ];
    }

    protected function resolveMappedPlan(array $payload, string $serviceSlug, string $message): array
    {
        if (is_array($payload['_route_plan_mapping'] ?? null)) {
            return $payload['_route_plan_mapping'];
        }

        $provider = is_array($payload['_provider_account'] ?? null) ? $payload['_provider_account'] : [];
        $providerId = (int) ($provider['id'] ?? 0);
        $serviceId = (int) ($payload['service_id'] ?? 0);
        $networkCode = (string) ($payload['network'] ?? $payload['provider'] ?? '');
        $localPlanCode = (string) ($payload['plan'] ?? $payload['package'] ?? $payload['exam_type'] ?? $payload['local_plan_code'] ?? '');
        $plan = $this->planService->resolveForProvider($providerId, $serviceId, $networkCode, $localPlanCode);
        if (!$plan) {
            throw new RuntimeException($message);
        }

        $providerPlanId = trim((string) ($plan['provider_plan_id'] ?? ''));
        if ($providerPlanId === '') {
            throw new RuntimeException(sprintf('Provider plan ID is required for %s %s purchase.', $this->code(), $serviceSlug));
        }

        return $plan;
    }

    protected function path(string $configKey, string $default): string
    {
        return trim((string) ($this->config[$configKey] ?? $default));
    }

    protected function sanitizePhone(string $phone): string
    {
        $normalized = preg_replace('/\D+/', '', $phone) ?? '';
        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, '234') && strlen($normalized) === 13) {
            return '0' . substr($normalized, 3);
        }

        return strlen($normalized) >= 10 && strlen($normalized) <= 15 ? $normalized : '';
    }

    protected function mappedValue(string $mapKey, string $raw, ?string $fallback = null): string
    {
        $value = strtolower(trim($raw));
        $map = is_array($this->config[$mapKey] ?? null) ? $this->config[$mapKey] : [];
        foreach ($map as $key => $mapped) {
            if (strtolower(trim((string) $key)) === $value) {
                return trim((string) $mapped);
            }
        }

        return trim((string) ($fallback ?? $raw));
    }

    protected function normalizeNetworkName(string $network): string
    {
        return match (strtolower(trim($network))) {
            'mtn' => 'MTN',
            'airtel' => 'AIRTEL',
            'glo' => 'GLO',
            '9mobile', 'etisalat' => '9MOBILE',
            default => strtoupper(trim($network)),
        };
    }

    protected function normalizeStatus(array $response): string
    {
        if (array_key_exists('status', $response) && is_bool($response['status'])) {
            return $response['status'] ? 'successful' : 'failed';
        }

        $status = strtolower((string) (
            $this->extractStringValue($response, ['status', 'transaction_status', 'state'])
            ?? $this->extractStringValue(is_array($response['data'] ?? null) ? $response['data'] : [], ['status', 'transaction_status', 'state'])
            ?? ''
        ));
        $successFlag = $response['success'] ?? $response['status_code'] ?? $response['code'] ?? null;
        if ($status === '' && ($successFlag === true || (string) $successFlag === '200' || strtolower((string) $successFlag) === 'success')) {
            return 'successful';
        }

        return match ($status) {
            'true', 'success', 'successful', 'completed', 'delivered', 'approved' => 'successful',
            'pending', 'processing', 'queued', 'initiated' => 'pending',
            default => 'failed',
        };
    }

    protected function extractMoneyValue(array $response, array $keys): ?float
    {
        foreach ([$response, $response['data'] ?? [], $response['user'] ?? []] as $bucket) {
            if (!is_array($bucket)) {
                continue;
            }
            foreach ($keys as $key) {
                if (isset($bucket[$key]) && is_numeric($bucket[$key])) {
                    return round((float) $bucket[$key], 2);
                }
            }
        }

        return null;
    }

    protected function extractStringValue(array $response, array $keys): ?string
    {
        foreach ([$response, $response['data'] ?? [], $response['transaction'] ?? []] as $bucket) {
            if (!is_array($bucket)) {
                continue;
            }
            foreach ($keys as $key) {
                if (isset($bucket[$key]) && trim((string) $bucket[$key]) !== '') {
                    return trim((string) $bucket[$key]);
                }
            }
        }

        return null;
    }

    private function logProviderRequest(string $operation, string $method, string $path, array $payload, array $result, int $attempt): void
    {
        $this->logger->writeToFile(
            (string) config('app.provider_log_file', dirname(__DIR__) . '/storage/logs/provider.log'),
            ($result['ok'] ?? false) ? 'info' : 'warning',
            ucfirst($this->code()) . ' provider request completed.',
            [
                'provider' => $this->code(),
                'operation' => $operation,
                'attempt' => $attempt,
                'method' => $method,
                'path' => $path,
                'url' => (string) ($result['request']['url'] ?? ''),
                'request' => $this->logger->sanitizeProviderMeta($payload),
                'http_code' => $result['http_code'] ?? 0,
                'latency_ms' => $result['latency_ms'] ?? 0,
                'curl_errno' => $result['curl_errno'] ?? 0,
                'curl_error' => $result['curl_error'] ?? null,
                'status' => ($result['ok'] ?? false) ? 'successful' : (($result['transient'] ?? false) ? 'pending' : 'failed'),
                'response' => $result['response'] ?? ($result['json'] ?? ['body' => $result['body'] ?? null]),
            ]
        );
    }
}
