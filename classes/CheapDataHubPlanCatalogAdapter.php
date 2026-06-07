<?php

declare(strict_types=1);

namespace GemData\Classes;

class CheapDataHubPlanCatalogAdapter implements ProviderPlanCatalogAdapterInterface
{
    public function __construct(private AppLogger $logger)
    {
    }

    public function supportsSync(array $provider): bool
    {
        return strtolower((string) ($provider['driver'] ?? '')) === 'cheapdatahub';
    }

    public function syncPlans(array $provider, array $service): array
    {
        if ((string) ($service['slug'] ?? '') !== 'exam_pin') {
            return [];
        }

        $configKey = strtolower((string) ($provider['credentials_key'] ?: $provider['code']));
        $config = (array) config('providers.' . $configKey, []);
        if (!empty($provider['base_url'])) {
            $config['base_url'] = (string) $provider['base_url'];
        }

        $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $apiKey = trim((string) ($config['api_key'] ?? $config['token'] ?? ''));
        if ($baseUrl === '' || $apiKey === '') {
            return [];
        }

        $path = trim((string) ($config['exam_pin_products_path'] ?? '/exam-pin/products/'));
        $url = $baseUrl . '/' . ltrim($path, '/');
        $timeout = max(5, (int) ($config['timeout_seconds'] ?? 20));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = is_string($body) && $body !== '' ? json_decode($body, true) : null;
        if ($errno !== 0 || $httpCode < 200 || $httpCode >= 300 || !is_array($decoded)) {
            $this->logger->warning('CheapDataHub plan sync failed.', [
                'provider' => $provider['code'] ?? 'cheapdatahub',
                'http_code' => $httpCode,
                'curl_errno' => $errno,
                'curl_error' => $error,
                'body' => is_string($body) ? substr($body, 0, 500) : null,
            ]);
            return [];
        }

        $items = $this->extractItems($decoded);
        $rows = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = trim((string) ($item['id'] ?? $item['product_id'] ?? $item['code'] ?? ''));
            $name = trim((string) ($item['name'] ?? $item['product_name'] ?? $item['title'] ?? ''));
            $amount = $item['amount'] ?? $item['price'] ?? $item['selling_price'] ?? null;
            if ($id === '' || $name === '' || !is_numeric($amount)) {
                continue;
            }

            $rows[] = [
                'service_slug' => 'exam_pin',
                'network_code' => '',
                'local_plan_code' => 'EXAM_' . strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $id) ?? $id),
                'local_plan_name' => $name,
                'provider_plan_id' => $id,
                'provider_plan_name' => $name,
                'amount' => (float) $amount,
                'is_enabled' => 1,
            ];
        }

        return $rows;
    }

    private function extractItems(array $decoded): array
    {
        foreach (['data', 'products', 'results', 'items'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                return $decoded[$key];
            }
        }

        return $this->isList($decoded) ? $decoded : [];
    }

    private function isList(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }
}
