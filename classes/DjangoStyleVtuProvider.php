<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

abstract class DjangoStyleVtuProvider extends ProductionProviderAdapter
{
    protected function authHeaders(): array
    {
        return $this->tokenHeaders();
    }

    public function checkBalance(): array
    {
        $this->ensureReady('balance');

        $result = $this->request('GET', $this->path('balance_path', '/user/'), [], 'balance', false);
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
        $balance = $this->extractMoneyValue($decoded, ['balance', 'wallet_balance', 'account_balance', 'main_balance', 'amount']);

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
            'exam_pin' => $this->supportsExamPin() ? $this->purchaseExamPin($payload) : $this->unsupportedService('exam_pin'),
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

        $service = strtolower((string) ($this->config['default_status_service'] ?? 'data'));
        $pathKey = match ($service) {
            'airtime' => 'airtime_status_path',
            'electricity' => 'electricity_status_path',
            'cable_tv', 'cable' => 'cable_status_path',
            default => 'data_status_path',
        };
        $defaultPath = match ($service) {
            'electricity' => '/billpayment/{id}',
            'cable_tv', 'cable' => '/cablesub/{id}',
            default => '/data/{id}',
        };
        $path = str_replace(['{id}', '{reference}'], rawurlencode($reference), $this->path($pathKey, $defaultPath));
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

    protected function supportsExamPin(): bool
    {
        return false;
    }

    private function purchaseAirtime(array $payload): array
    {
        $this->ensureReady('airtime');

        $phone = $this->sanitizePhone((string) ($payload['phone'] ?? $payload['recipient'] ?? ''));
        $network = $this->mappedValue('network_map', (string) ($payload['network'] ?? ''));
        $amount = round((float) ($payload['amount'] ?? 0), 2);
        $reference = trim((string) ($payload['reference'] ?? ''));

        if ($phone === '') {
            throw new RuntimeException('A valid phone number is required for ' . $this->code() . ' airtime purchase.');
        }
        if ($network === '') {
            throw new RuntimeException('A provider network ID is required. Configure providers.' . $this->code() . '.network_map.');
        }
        if ($amount <= 0) {
            throw new RuntimeException('Airtime amount must be greater than zero.');
        }

        $requestPayload = [
            'network' => is_numeric($network) ? (int) $network : $network,
            'amount' => $amount,
            'mobile_number' => $phone,
            'Ported_number' => (bool) ($this->config['ported_number'] ?? true),
            'airtime_type' => (string) ($this->config['airtime_type'] ?? 'VTU'),
        ];

        $result = $this->request('POST', $this->path('airtime_path', '/topup/'), $requestPayload, 'airtime:' . $reference, true);
        return $this->purchaseResult($result, $reference, $amount, $phone);
    }

    private function purchaseData(array $payload): array
    {
        $this->ensureReady('data');

        $plan = $this->resolveMappedPlan($payload, 'data', 'No ' . $this->code() . ' plan mapping exists for the selected data plan and network.');
        $phone = $this->sanitizePhone((string) ($payload['phone'] ?? $payload['recipient'] ?? ''));
        $network = $this->mappedValue('network_map', (string) ($payload['network'] ?? ''));
        $reference = trim((string) ($payload['reference'] ?? ''));
        $amount = round((float) ($payload['amount'] ?? $plan['amount'] ?? 0), 2);

        if ($phone === '') {
            throw new RuntimeException('A valid phone number is required for ' . $this->code() . ' data purchase.');
        }
        if ($network === '') {
            throw new RuntimeException('A provider network ID is required. Configure providers.' . $this->code() . '.network_map.');
        }

        $requestPayload = [
            'network' => is_numeric($network) ? (int) $network : $network,
            'mobile_number' => $phone,
            'plan' => is_numeric($plan['provider_plan_id']) ? (int) $plan['provider_plan_id'] : (string) $plan['provider_plan_id'],
            'Ported_number' => (bool) ($this->config['ported_number'] ?? true),
        ];

        $result = $this->request('POST', $this->path('data_path', '/data/'), $requestPayload, 'data:' . $reference, true);
        return $this->purchaseResult($result, $reference, $amount, $phone);
    }

    private function purchaseElectricity(array $payload): array
    {
        $this->ensureReady('electricity');

        $meter = preg_replace('/\D+/', '', (string) ($payload['meter_number'] ?? $payload['recipient'] ?? '')) ?? '';
        $disco = $this->mappedValue('disco_map', (string) ($payload['disco'] ?? $payload['provider'] ?? $payload['network'] ?? ''));
        $meterType = $this->mappedValue('meter_type_map', (string) ($payload['meter_type'] ?? $payload['type'] ?? 'prepaid'));
        $amount = round((float) ($payload['amount'] ?? 0), 2);
        $reference = trim((string) ($payload['reference'] ?? ''));

        if ($meter === '') {
            throw new RuntimeException('A valid meter number is required for electricity purchase.');
        }
        if ($disco === '') {
            throw new RuntimeException('A provider disco ID is required. Configure providers.' . $this->code() . '.disco_map.');
        }
        if ($meterType === '') {
            throw new RuntimeException('A provider meter type ID is required. Configure providers.' . $this->code() . '.meter_type_map.');
        }
        if ($amount <= 0) {
            throw new RuntimeException('Electricity amount must be greater than zero.');
        }

        $requestPayload = [
            'disco_name' => is_numeric($disco) ? (int) $disco : $disco,
            'amount' => $amount,
            'meter_number' => $meter,
            'MeterType' => is_numeric($meterType) ? (int) $meterType : $meterType,
        ];

        $result = $this->request('POST', $this->path('electricity_path', '/billpayment/'), $requestPayload, 'electricity:' . $reference, true);
        return $this->purchaseResult($result, $reference, $amount, $meter);
    }

    private function purchaseCable(array $payload): array
    {
        $this->ensureReady('cable_tv');

        $plan = $this->resolveMappedPlan($payload, 'cable_tv', 'No ' . $this->code() . ' cable plan mapping exists for the selected package.');
        $smartCard = preg_replace('/\D+/', '', (string) ($payload['smart_card_number'] ?? $payload['iuc'] ?? $payload['recipient'] ?? '')) ?? '';
        $cable = $this->mappedValue('cable_map', (string) ($payload['cable'] ?? $payload['provider'] ?? $payload['network'] ?? ''));
        $reference = trim((string) ($payload['reference'] ?? ''));
        $amount = round((float) ($payload['amount'] ?? $plan['amount'] ?? 0), 2);

        if ($smartCard === '') {
            throw new RuntimeException('A valid smart card/IUC number is required for cable purchase.');
        }
        if ($cable === '') {
            throw new RuntimeException('A provider cable ID is required. Configure providers.' . $this->code() . '.cable_map.');
        }

        $requestPayload = [
            'cablename' => is_numeric($cable) ? (int) $cable : $cable,
            'cableplan' => is_numeric($plan['provider_plan_id']) ? (int) $plan['provider_plan_id'] : (string) $plan['provider_plan_id'],
            'smart_card_number' => $smartCard,
        ];

        $result = $this->request('POST', $this->path('cable_path', '/cablesub/'), $requestPayload, 'cable:' . $reference, true);
        return $this->purchaseResult($result, $reference, $amount, $smartCard);
    }

    private function purchaseExamPin(array $payload): array
    {
        $this->ensureReady('exam_pin');

        $examName = strtoupper(trim((string) ($payload['exam_type'] ?? $payload['exam_name'] ?? $payload['plan'] ?? '')));
        $quantity = max(1, min(5, (int) ($payload['quantity'] ?? 1)));
        $reference = trim((string) ($payload['reference'] ?? ''));
        $amount = round((float) ($payload['amount'] ?? 0), 2);

        if ($examName === '') {
            throw new RuntimeException('Exam name is required for ' . $this->code() . ' exam PIN purchase.');
        }

        $requestPayload = [
            'exam_name' => $examName,
            'quantity' => $quantity,
        ];

        $result = $this->request('POST', $this->path('exam_pin_path', '/epin/'), $requestPayload, 'exam-pin:' . $reference, true);
        return $this->purchaseResult($result, $reference, $amount, $examName);
    }
}
