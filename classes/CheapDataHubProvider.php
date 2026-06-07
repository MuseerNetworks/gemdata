<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class CheapDataHubProvider extends ProductionProviderAdapter
{
    public function code(): string
    {
        return 'cheapdatahub';
    }

    protected function authHeaders(): array
    {
        return $this->bearerHeaders();
    }

    public function checkBalance(): array
    {
        $this->ensureReady('balance');

        $result = $this->request('GET', $this->path('balance_path', '/wallet/balance/'), [], 'balance', false);
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
        $balance = $this->extractMoneyValue($decoded, ['balance', 'wallet_balance', 'amount', 'available_balance']);

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
            'electricity' => $this->purchaseElectricity($payload),
            'cable_tv' => $this->purchaseCable($payload),
            'exam_pin' => $this->purchaseExamPin($payload),
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

        $path = str_replace(['{id}', '{reference}'], rawurlencode($reference), $this->path('status_path', '/transactions/{id}/'));
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
            'provider_reference' => $this->extractStringValue($decoded, ['provider_reference', 'reference', 'transaction_reference', 'transaction_id', 'id']) ?: $reference,
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
        $providerId = $this->mappedValue('network_map', (string) ($payload['network'] ?? ''));
        $amount = round((float) ($payload['amount'] ?? 0), 2);
        $reference = trim((string) ($payload['reference'] ?? ''));

        if ($phone === '') {
            throw new RuntimeException('A valid phone number is required for CheapDataHub airtime purchase.');
        }
        if ($providerId === '') {
            throw new RuntimeException('A CheapDataHub provider_id is required. Configure providers.cheapdatahub.network_map.');
        }
        if ($amount <= 0) {
            throw new RuntimeException('Airtime amount must be greater than zero.');
        }

        $requestPayload = [
            'provider_id' => is_numeric($providerId) ? (int) $providerId : $providerId,
            'phone_number' => $phone,
            'amount' => $amount,
        ];

        $result = $this->request('POST', $this->path('airtime_path', '/airtime/purchase/'), $requestPayload, 'airtime:' . $reference, true);
        return $this->purchaseResult($result, $reference, $amount, $phone);
    }

    private function purchaseData(array $payload): array
    {
        $this->ensureReady('data');

        $plan = $this->resolveMappedPlan($payload, 'data', 'No CheapDataHub plan mapping exists for the selected data plan and network.');
        $phone = $this->sanitizePhone((string) ($payload['phone'] ?? $payload['recipient'] ?? ''));
        $reference = trim((string) ($payload['reference'] ?? ''));
        $amount = round((float) ($payload['amount'] ?? $plan['amount'] ?? 0), 2);

        if ($phone === '') {
            throw new RuntimeException('A valid phone number is required for CheapDataHub data purchase.');
        }

        $requestPayload = [
            'bundle_id' => is_numeric($plan['provider_plan_id']) ? (int) $plan['provider_plan_id'] : (string) $plan['provider_plan_id'],
            'phone_number' => $phone,
        ];

        $result = $this->request('POST', $this->path('data_path', '/data/purchase/'), $requestPayload, 'data:' . $reference, true);
        return $this->purchaseResult($result, $reference, $amount, $phone);
    }

    private function purchaseElectricity(array $payload): array
    {
        $this->ensureReady('electricity');

        $meter = preg_replace('/\D+/', '', (string) ($payload['meter_number'] ?? $payload['recipient'] ?? '')) ?? '';
        $phone = $this->sanitizePhone((string) ($payload['phone'] ?? $payload['customer_phone'] ?? $payload['recipient_phone'] ?? ''));
        $disco = $this->mappedValue('disco_map', (string) ($payload['disco'] ?? $payload['provider'] ?? $payload['network'] ?? ''));
        $meterType = $this->mappedValue('meter_type_map', (string) ($payload['meter_type'] ?? $payload['type'] ?? 'prepaid'));
        $amount = round((float) ($payload['amount'] ?? 0), 2);
        $reference = trim((string) ($payload['reference'] ?? ''));

        if ($meter === '') {
            throw new RuntimeException('A valid meter number is required for CheapDataHub electricity purchase.');
        }
        if ($phone === '') {
            throw new RuntimeException('A valid customer phone is required for CheapDataHub electricity purchase.');
        }
        if ($disco === '') {
            throw new RuntimeException('A CheapDataHub disco_id is required. Configure providers.cheapdatahub.disco_map.');
        }
        if ($meterType === '') {
            throw new RuntimeException('A CheapDataHub meter_type is required. Configure providers.cheapdatahub.meter_type_map.');
        }
        if ($amount <= 0) {
            throw new RuntimeException('Electricity amount must be greater than zero.');
        }

        $requestPayload = [
            'disco_id' => is_numeric($disco) ? (int) $disco : $disco,
            'meter_number' => $meter,
            'amount' => $amount,
            'meter_type' => $meterType,
            'phone' => $phone,
        ];

        $result = $this->request('POST', $this->path('electricity_path', '/electricity/purchase/'), $requestPayload, 'electricity:' . $reference, true);
        return $this->purchaseResult($result, $reference, $amount, $meter);
    }

    private function purchaseCable(array $payload): array
    {
        $this->ensureReady('cable_tv');

        $plan = $this->resolveMappedPlan($payload, 'cable_tv', 'No CheapDataHub cable plan mapping exists for the selected package.');
        $cardNumber = preg_replace('/\D+/', '', (string) ($payload['smart_card_number'] ?? $payload['iuc'] ?? $payload['recipient'] ?? '')) ?? '';
        $phone = $this->sanitizePhone((string) ($payload['phone'] ?? $payload['customer_phone'] ?? $payload['recipient_phone'] ?? ''));
        $reference = trim((string) ($payload['reference'] ?? ''));
        $amount = round((float) ($payload['amount'] ?? $plan['amount'] ?? 0), 2);

        if ($cardNumber === '') {
            throw new RuntimeException('A valid smart card/IUC number is required for CheapDataHub cable purchase.');
        }
        if ($phone === '') {
            throw new RuntimeException('A valid customer phone is required for CheapDataHub cable purchase.');
        }

        $requestPayload = [
            'plan_id' => is_numeric($plan['provider_plan_id']) ? (int) $plan['provider_plan_id'] : (string) $plan['provider_plan_id'],
            'cardnumber' => $cardNumber,
            'phone' => $phone,
        ];

        $result = $this->request('POST', $this->path('cable_path', '/cable/purchase/'), $requestPayload, 'cable:' . $reference, true);
        return $this->purchaseResult($result, $reference, $amount, $cardNumber);
    }

    private function purchaseExamPin(array $payload): array
    {
        $this->ensureReady('exam_pin');

        $plan = $this->resolveMappedPlan($payload, 'exam_pin', 'No CheapDataHub exam PIN product mapping exists for the selected exam.');
        $quantity = max(1, (int) ($payload['quantity'] ?? 1));
        $reference = trim((string) ($payload['reference'] ?? ''));
        $amount = round((float) ($payload['amount'] ?? $plan['amount'] ?? 0), 2);

        $requestPayload = [
            'product_id' => is_numeric($plan['provider_plan_id']) ? (int) $plan['provider_plan_id'] : (string) $plan['provider_plan_id'],
            'quantity' => $quantity,
        ];

        $result = $this->request('POST', $this->path('exam_pin_path', '/exam-pin/purchase/'), $requestPayload, 'exam-pin:' . $reference, true);
        return $this->purchaseResult($result, $reference, $amount, (string) ($plan['local_plan_name'] ?? $plan['provider_plan_name'] ?? 'exam_pin'));
    }
}
