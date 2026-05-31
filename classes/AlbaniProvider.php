<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class AlbaniProvider extends AbstractProviderAdapter
{
    private const TRANSIENT_HTTP_CODES = [408, 425, 429, 500, 502, 503, 504];

    public function __construct(
        array $config,
        AppLogger $logger,
        private ProviderPlanService $planService
    ) {
        parent::__construct($config, $logger);
    }

    public function code(): string
    {
        return 'albani';
    }

    public function healthCheck(): array
    {
        $health = parent::healthCheck();
        if (($health['status'] ?? '') !== 'ready') {
            return $health;
        }

        $balance = $this->checkBalance();
        $health['status'] = ($balance['status'] ?? 'failed') === 'successful' ? 'ready' : (($balance['status'] ?? 'failed') === 'pending' ? 'degraded' : 'failed');
        $health['balance'] = $balance['balance'] ?? null;
        $health['currency'] = $balance['currency'] ?? 'NGN';
        $health['balance_raw'] = $balance['raw'] ?? [];

        return $health;
    }

    public function checkBalance(): array
    {
        $this->ensureReady('balance');

        $paths = array_values(array_filter(array_unique(array_map(
            static fn($value): string => trim((string) $value),
            array_merge(
                [(string) ($this->config['balance_path'] ?? '/wallet/balance')],
                (array) ($this->config['balance_fallback_paths'] ?? ['/wallet/balance'])
            )
        ))));

        $lastFailure = null;
        foreach ($paths as $path) {
            $result = $this->sendRequest('GET', $path, [], 'balance:' . trim($path, '/'), false);
            if (($result['ok'] ?? false) !== true) {
                $lastFailure = $result;
                continue;
            }

            $decoded = $result['json'];
            $balance = $this->extractMoneyValue($decoded, ['balance', 'amount', 'wallet_balance', 'main_balance']);
            if ($balance === null) {
                $lastFailure = $result + ['message' => 'Balance field missing from provider response.'];
                continue;
            }

            return [
                'status' => 'successful',
                'balance' => $balance,
                'currency' => $this->extractStringValue($decoded, ['currency']) ?: 'NGN',
                'provider_reference' => null,
                'raw' => $decoded,
            ];
        }

        return [
            'status' => 'failed',
            'balance' => null,
            'currency' => 'NGN',
            'provider_reference' => null,
            'raw' => $lastFailure ?? ['message' => 'Unable to fetch provider balance.'],
        ];
    }

    public function purchase(string $serviceSlug, array $payload): array
    {
        return match ($serviceSlug) {
            'airtime' => $this->purchaseAirtime($payload),
            'data' => $this->purchaseData($payload),
            default => $this->unsupportedPurchase($serviceSlug, $payload),
        };
    }

    public function queryTransaction(string $reference): array
    {
        $this->ensureReady('query-transaction');

        $reference = trim($reference);
        if ($reference === '') {
            return [
                'status' => 'failed',
                'provider_reference' => null,
                'raw' => ['message' => 'Transaction reference is required.'],
            ];
        }

        $result = $this->sendRequest('GET', '/transaction/status/' . rawurlencode($reference), [], 'status:' . $reference, false);
        if (($result['ok'] ?? false) !== true) {
            return [
                'status' => ($result['transient'] ?? false) ? 'pending' : 'failed',
                'provider_reference' => $reference,
                'raw' => $result,
            ];
        }

        $decoded = $result['json'];
        $status = $this->normalizeStatus($decoded);
        $providerReference = $this->extractStringValue($decoded, ['provider_reference', 'reference', 'transaction_reference']) ?: $reference;

        return [
            'status' => $status,
            'provider_reference' => $providerReference,
            'raw' => $decoded,
        ];
    }

    public function purchaseAirtime(array $payload): array
    {
        $this->ensureReady('airtime');

        $phone = $this->sanitizePhone((string) ($payload['phone'] ?? $payload['recipient'] ?? ''));
        $network = $this->normalizeNetwork((string) ($payload['network'] ?? ''));
        $amount = round((float) ($payload['amount'] ?? 0), 2);
        $reference = trim((string) ($payload['reference'] ?? ''));

        if ($phone === '') {
            throw new RuntimeException('A valid phone number is required for airtime purchase.');
        }
        if ($network === '') {
            throw new RuntimeException('A valid airtime network is required.');
        }
        if ($amount <= 0) {
            throw new RuntimeException('Airtime amount must be greater than zero.');
        }

        $requestPayload = [
            'network' => $network,
            'amount' => $amount,
            'phone' => $phone,
            'reference' => $reference,
        ];

        return $this->fulfillPurchase('/airtime/purchase', 'airtime:' . $reference, $requestPayload, $phone, $amount, $reference);
    }

    public function purchaseData(array $payload): array
    {
        $this->ensureReady('data');

        $providerAccount = $payload['_provider_account'] ?? null;
        $providerId = (int) (($providerAccount['id'] ?? 0));
        $serviceId = (int) ($payload['service_id'] ?? 0);
        $networkCode = (string) ($payload['network'] ?? '');
        $localPlanCode = (string) ($payload['plan'] ?? '');
        $plan = $this->planService->resolveForProvider($providerId, $serviceId, $networkCode, $localPlanCode);

        if (!$plan) {
            throw new RuntimeException('No Albani plan mapping exists for the selected data plan and network.');
        }

        $phone = $this->sanitizePhone((string) ($payload['phone'] ?? $payload['recipient'] ?? ''));
        $reference = trim((string) ($payload['reference'] ?? ''));
        $amount = round((float) ($payload['amount'] ?? 0), 2);

        if ($phone === '') {
            throw new RuntimeException('A valid phone number is required for data purchase.');
        }

        $planId = trim((string) ($plan['provider_plan_id'] ?? ''));
        if ($planId === '' || !preg_match('/^[A-Za-z0-9._:-]+$/', $planId)) {
            throw new RuntimeException('Albani plan ID is invalid for the selected data mapping.');
        }

        $requestPayload = [
            'plan_id' => $planId,
            'phone' => $phone,
            'reference' => $reference,
        ];

        return $this->fulfillPurchase('/data/purchase', 'data:' . $reference, $requestPayload, $phone, $amount, $reference);
    }

    private function fulfillPurchase(string $path, string $operation, array $requestPayload, string $recipient, float $amount, string $fallbackReference): array
    {
        $result = $this->sendRequest('POST', $path, $requestPayload, $operation, true);
        if (($result['ok'] ?? false) !== true) {
            return [
                'status' => ($result['transient'] ?? false) ? 'pending' : 'failed',
                'provider_reference' => $fallbackReference !== '' ? $fallbackReference : null,
                'amount' => $amount,
                'recipient' => $recipient,
                'raw' => $result,
            ];
        }

        $decoded = $result['json'];
        $providerReference = $this->extractStringValue($decoded, ['provider_reference', 'reference', 'transaction_reference']) ?: $fallbackReference;

        return [
            'status' => $this->normalizeStatus($decoded),
            'provider_reference' => $providerReference,
            'amount' => $amount,
            'recipient' => $recipient,
            'raw' => $decoded,
        ];
    }

    private function sendRequest(string $method, string $path, array $payload, string $operation, bool $retryTransient): array
    {
        $timeout = max(5, (int) ($this->config['timeout_seconds'] ?? 20));
        $retryCount = max(0, (int) ($this->config['retry_count'] ?? 1));
        $requestId = $this->logger->requestId();
        $url = rtrim((string) ($this->config['base_url'] ?? ''), '/') . '/' . ltrim($path, '/');
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . (string) ($this->config['api_key'] ?? ''),
            'X-Request-Id: ' . $requestId,
        ];

        $attempt = 0;
        $lastResult = null;
        $maxAttempts = 1 + ($retryTransient ? $retryCount : 0);

        while ($attempt < $maxAttempts) {
            $attempt++;
            $startedAt = microtime(true);
            $ch = curl_init();
            $body = $method === 'POST' ? json_encode($payload, JSON_UNESCAPED_SLASHES) : null;

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
                CURLOPT_HTTPHEADER => array_merge($headers, $method === 'POST' ? ['Content-Type: application/json'] : []),
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

            $decoded = null;
            if (is_string($responseBody) && $responseBody !== '') {
                $decoded = json_decode($responseBody, true);
            }

            $transient = in_array($httpCode, self::TRANSIENT_HTTP_CODES, true)
                || in_array($curlErrno, [6, 7, 28, 35, 52, 56], true);

            $ok = $curlErrno === 0
                && $httpCode >= 200
                && $httpCode < 300
                && is_array($decoded);

            $responseBodySnippet = is_string($responseBody) ? substr($responseBody, 0, 4000) : null;
            $lastResult = [
                'ok' => $ok,
                'transient' => $transient,
                'http_code' => $httpCode,
                'curl_errno' => $curlErrno,
                'curl_error' => $curlError !== '' ? $curlError : null,
                'latency_ms' => $latencyMs,
                'json' => $decoded,
                'body' => $responseBodySnippet,
                'message' => $ok
                    ? 'Provider request completed.'
                    : ($curlError !== '' ? 'Provider request failed at transport layer.' : 'Provider returned an invalid or unsuccessful response.'),
                'request' => [
                    'method' => $method,
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
                    'body' => $responseBodySnippet !== null ? $this->logger->sanitizeProviderMeta(['body' => $responseBodySnippet])['body'] : null,
                ],
            ];

            $this->logProviderRequest($operation, $method, $path, $payload, $lastResult, $attempt);

            if ($ok) {
                return $lastResult;
            }

            if (!$retryTransient || !$transient || $attempt >= $maxAttempts) {
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

    private function logProviderRequest(string $operation, string $method, string $path, array $payload, array $result, int $attempt): void
    {
        $this->logger->writeToFile(
            (string) config('app.provider_log_file', dirname(__DIR__) . '/storage/logs/provider.log'),
            ($result['ok'] ?? false) ? 'info' : 'warning',
            'Albani provider request completed.',
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
                'status' => $result['ok'] ?? false ? 'successful' : (($result['transient'] ?? false) ? 'pending' : 'failed'),
                'response' => $result['response'] ?? ($result['json'] ?? ['body' => $result['body'] ?? null]),
            ]
        );
    }

    private function sanitizePhone(string $phone): string
    {
        $normalized = preg_replace('/\D+/', '', $phone) ?? '';
        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, '234') && strlen($normalized) === 13) {
            return '0' . substr($normalized, 3);
        }

        if (strlen($normalized) >= 10 && strlen($normalized) <= 15) {
            return $normalized;
        }

        return '';
    }

    private function normalizeNetwork(string $network): string
    {
        $network = strtolower(trim($network));

        return match ($network) {
            'mtn' => 'MTN',
            'airtel' => 'AIRTEL',
            'glo' => 'GLO',
            '9mobile', 'etisalat' => '9MOBILE',
            default => '',
        };
    }

    private function normalizeStatus(array $response): string
    {
        if (array_key_exists('status', $response) && is_bool($response['status'])) {
            if ($response['status'] === true) {
                return 'successful';
            }

            $message = strtolower(trim((string) ($response['message'] ?? '')));
            return in_array($message, ['pending', 'processing', 'queued'], true) ? 'pending' : 'failed';
        }

        $status = strtolower((string) (
            $this->extractStringValue($response, ['status', 'transaction_status', 'state'])
            ?? $this->extractStringValue($response['data'] ?? [], ['status', 'transaction_status', 'state'])
            ?? ''
        ));

        $successFlag = $response['success'] ?? $response['status_code'] ?? $response['code'] ?? null;
        if ($status === '' && ($successFlag === true || (string) $successFlag === '200' || (string) $successFlag === 'success')) {
            return 'successful';
        }

        return match ($status) {
            'success', 'successful', 'completed', 'delivered' => 'successful',
            'pending', 'processing', 'queued', 'initiated' => 'pending',
            default => 'failed',
        };
    }

    private function extractMoneyValue(array $response, array $keys): ?float
    {
        foreach ([$response, $response['data'] ?? []] as $bucket) {
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

    private function extractStringValue(array $response, array $keys): ?string
    {
        foreach ([$response, $response['data'] ?? []] as $bucket) {
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
}
