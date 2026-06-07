<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class AlbaniProvider extends ProductionProviderAdapter
{
    public function code(): string
    {
        return 'albani';
    }

    protected function authHeaders(): array
    {
        return $this->bearerHeaders();
    }

    public function checkBalance(): array
    {
        $this->ensureReady('balance');

        $result = $this->request('GET', $this->path('balance_path', '/wallet/balance'), [], 'balance', false);
        if (($result['ok'] ?? false) !== true) {
            return [
                'status' => 'failed',
                'balance' => null,
                'currency' => 'NGN',
                'provider_reference' => null,
                'raw' => $result,
            ];
        }

        $decoded = $result['json'];
        $balance = $this->extractMoneyValue($decoded, ['balance', 'amount', 'wallet_balance', 'main_balance']);

        return [
            'status' => $balance !== null ? 'successful' : 'failed',
            'balance' => $balance,
            'currency' => $this->extractStringValue($decoded, ['currency']) ?: 'NGN',
            'provider_reference' => null,
            'raw' => $decoded + [
                'request' => $result['request'] ?? null,
                'response' => $result['response'] ?? null,
            ],
        ];
    }

    public function purchase(string $serviceSlug, array $payload): array
    {
        return match ($serviceSlug) {
            'airtime' => $this->purchaseAirtime($payload),
            'data' => $this->purchaseData($payload),
            'cable_tv' => $this->unsupportedService('cable_tv'),
            'electricity' => $this->unsupportedService('electricity'),
            'exam_pin' => $this->unsupportedService('exam_pin'),
            'data_card' => $this->unsupportedService('data_card'),
            'recharge_card' => $this->unsupportedService('recharge_card'),
            'bulk_sms' => $this->unsupportedService('bulk_sms'),
            default => $this->unsupportedService($serviceSlug),
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

        $path = str_replace('{reference}', rawurlencode($reference), $this->path('status_path', '/transaction/status/{reference}'));
        $result = $this->request('GET', $path, [], 'status:' . $reference, false);
        if (($result['ok'] ?? false) !== true) {
            return [
                'status' => ($result['transient'] ?? false) ? 'pending' : 'failed',
                'provider_reference' => $reference,
                'raw' => $result,
            ];
        }

        $decoded = $result['json'];

        return [
            'status' => $this->normalizeStatus($decoded),
            'provider_reference' => $this->extractStringValue($decoded, ['provider_reference', 'reference', 'transaction_reference', 'transaction_id']) ?: $reference,
            'raw' => $decoded + [
                'request' => $result['request'] ?? null,
                'response' => $result['response'] ?? null,
            ],
        ];
    }

    private function purchaseAirtime(array $payload): array
    {
        $this->ensureReady('airtime');

        $phone = $this->sanitizePhone((string) ($payload['phone'] ?? $payload['recipient'] ?? ''));
        $network = $this->normalizeNetworkName((string) ($payload['network'] ?? ''));
        $amount = round((float) ($payload['amount'] ?? 0), 2);
        $reference = trim((string) ($payload['reference'] ?? ''));

        if ($phone === '') {
            throw new RuntimeException('A valid phone number is required for Albani airtime purchase.');
        }
        if ($network === '') {
            throw new RuntimeException('A valid network is required for Albani airtime purchase.');
        }
        if ($amount <= 0) {
            throw new RuntimeException('Airtime amount must be greater than zero.');
        }

        $requestPayload = [
            'network' => $network,
            'amount' => $amount,
            'phone' => $phone,
        ];
        if ($reference !== '' && !empty($this->config['include_reference'])) {
            $requestPayload['reference'] = $reference;
        }

        $result = $this->request('POST', $this->path('airtime_path', '/airtime/purchase'), $requestPayload, 'airtime:' . $reference, true);
        return $this->purchaseResult($result, $reference, $amount, $phone);
    }

    private function purchaseData(array $payload): array
    {
        $this->ensureReady('data');

        $plan = $this->resolveMappedPlan($payload, 'data', 'No Albani plan mapping exists for the selected data plan and network.');
        $phone = $this->sanitizePhone((string) ($payload['phone'] ?? $payload['recipient'] ?? ''));
        $reference = trim((string) ($payload['reference'] ?? ''));
        $amount = round((float) ($payload['amount'] ?? $plan['amount'] ?? 0), 2);
        $planId = trim((string) ($plan['provider_plan_id'] ?? ''));

        if ($phone === '') {
            throw new RuntimeException('A valid phone number is required for Albani data purchase.');
        }

        $requestPayload = [
            'plan_id' => $planId,
            'phone' => $phone,
        ];
        if ($reference !== '' && !empty($this->config['include_reference'])) {
            $requestPayload['reference'] = $reference;
        }

        $result = $this->request('POST', $this->path('data_path', '/data/purchase'), $requestPayload, 'data:' . $reference, true);
        return $this->purchaseResult($result, $reference, $amount, $phone);
    }
}
