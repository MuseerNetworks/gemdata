<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class VtuNgPlanCatalogAdapter implements ProviderPlanCatalogAdapterInterface
{
    public function __construct(private AppLogger $logger)
    {
    }

    public function supportsSync(array $provider): bool
    {
        return in_array(strtolower((string) ($provider['driver'] ?? '')), ['vtung', 'nigerianvtu'], true);
    }

    public function syncPlans(array $provider, array $service): array
    {
        $serviceSlug = (string) ($service['slug'] ?? '');
        $path = match ($serviceSlug) {
            'data' => '/api/v2/variations/data',
            'cable_tv' => '/api/v2/variations/tv',
            default => '',
        };

        if ($path === '') {
            return [];
        }

        $baseUrl = rtrim((string) ($provider['base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            $baseUrl = 'https://vtu.ng/wp-json';
        }

        $url = $baseUrl . $path;
        $startedAt = microtime(true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->logger->writeToFile(
            (string) config('app.provider_log_file', dirname(__DIR__) . '/storage/logs/provider.log'),
            $curlErrno === 0 && $httpCode >= 200 && $httpCode < 300 ? 'info' : 'warning',
            'Provider plan sync request completed.',
            [
                'provider_code' => (string) ($provider['code'] ?? ''),
                'service_slug' => $serviceSlug,
                'url' => $url,
                'http_code' => $httpCode,
                'curl_errno' => $curlErrno,
                'curl_error' => $curlError !== '' ? $curlError : null,
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]
        );

        if ($curlErrno !== 0) {
            throw new RuntimeException('Provider plan sync failed at transport layer.');
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('Provider plan sync returned HTTP ' . $httpCode . '.');
        }

        $decoded = is_string($body) && $body !== '' ? json_decode($body, true) : null;
        if (!is_array($decoded)) {
            throw new RuntimeException('Provider plan sync did not return valid JSON.');
        }

        $items = is_array($decoded['data'] ?? null) ? $decoded['data'] : $decoded;
        if (!is_array($items)) {
            return [];
        }

        $plans = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $providerPlanId = trim((string) ($item['variation_id'] ?? $item['id'] ?? $item['code'] ?? ''));
            if ($providerPlanId === '') {
                continue;
            }

            $network = trim((string) ($item['service_id'] ?? $item['network'] ?? ''));
            $providerPlanName = trim((string) ($item['data_plan'] ?? $item['package_bouquet'] ?? $item['name'] ?? $providerPlanId));
            $plans[] = [
                'service_slug' => $serviceSlug,
                'network_code' => $network,
                'local_plan_code' => '',
                'local_plan_name' => $providerPlanName,
                'provider_plan_id' => $providerPlanId,
                'provider_plan_name' => trim((string) ($item['service_name'] ?? '')) !== ''
                    ? trim((string) $item['service_name']) . ' - ' . $providerPlanName
                    : $providerPlanName,
                'amount' => (string) ($item['price'] ?? ''),
                'is_enabled' => 0,
            ];
        }

        return $plans;
    }
}
