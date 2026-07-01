<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class KatPayPayoutService
{
    public const PROVIDER = 'katpay';
    public const MINIMUM_AMOUNT = 100.00;

    public function __construct(
        private AppLogger $logger
    ) {
    }

    public function isConfigured(): bool
    {
        return (bool) config('payments.katpay_enabled', false)
            && trim((string) config('payments.katpay_api_key', '')) !== ''
            && trim((string) config('payments.katpay_secret_key', '')) !== ''
            && trim((string) config('payments.katpay_base_url', '')) !== '';
    }

    public function configurationMessage(): string
    {
        return 'KatPay payout is not enabled. Record manual transfer only after sending money outside GemData.';
    }

    public function banks(): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $response = $this->request(
                'GET',
                '/api/bank-list',
                [],
                (string) config('payments.katpay_bank_list_base_url', 'https://api.katpay.co')
            );
        } catch (\Throwable $throwable) {
            $this->logger->warning('KatPay bank list fetch failed.', [
                'error' => $throwable->getMessage(),
            ]);
            return [];
        }

        $banks = [];
        foreach ($this->extractList($response) as $bank) {
            if (!is_array($bank)) {
                continue;
            }

            $code = $this->firstString($bank, ['bankCode', 'bank_code', 'code']);
            $name = $this->firstString($bank, ['bankName', 'bank_name', 'name']);
            if ($code === '' || $name === '') {
                continue;
            }

            $banks[$code] = ['bank_code' => $code, 'bank_name' => $name];
        }

        uasort($banks, static fn(array $a, array $b): int => strcasecmp($a['bank_name'], $b['bank_name']));
        return array_values($banks);
    }

    public function payout(array $payload): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException($this->configurationMessage());
        }

        $amount = round((float) ($payload['amount'] ?? 0), 2);
        if ($amount < self::MINIMUM_AMOUNT) {
            throw new RuntimeException('KatPay payout minimum amount is NGN 100.00.');
        }

        $accountNumber = preg_replace('/\D+/', '', (string) ($payload['account_number'] ?? '')) ?? '';
        if (!preg_match('/^\d{10}$/', $accountNumber)) {
            throw new RuntimeException('KatPay payout requires a 10-digit NUBAN account number.');
        }

        $request = [
            'amount' => $amount,
            'bank_code' => trim((string) ($payload['bank_code'] ?? '')),
            'account_number' => $accountNumber,
            'account_name' => trim((string) ($payload['account_name'] ?? '')),
        ];

        foreach (['bank_code', 'account_name'] as $required) {
            if ($request[$required] === '') {
                throw new RuntimeException('KatPay payout requires bank, account number, and account name.');
            }
        }

        foreach (['description', 'reference'] as $optional) {
            $value = trim((string) ($payload[$optional] ?? ''));
            if ($value !== '') {
                $request[$optional] = $value;
            }
        }

        $response = $this->request('POST', '/payouts', $request);
        return [
            'status' => $this->classifyStatus($response),
            'provider_reference' => $this->providerReference($response, (string) ($request['reference'] ?? '')),
            'safe_response' => $this->redactResponse($response),
            'message' => (string) ($response['message'] ?? $response['error'] ?? ''),
        ];
    }

    public function classifyStatus(array $response): string
    {
        $status = strtolower(trim((string) (
            $response['status']
            ?? $response['data']['status']
            ?? $response['data']['payout_status']
            ?? $response['data']['transaction_status']
            ?? $response['code']
            ?? ''
        )));

        if (is_bool($response['status'] ?? null)) {
            return $response['status'] === true ? 'successful' : 'failed';
        }

        if (in_array($status, ['success', 'successful', 'processed', 'completed', 'paid'], true)) {
            return 'successful';
        }

        if (in_array($status, ['pending', 'processing', 'queued', 'initiated'], true)) {
            return 'processing';
        }

        if (in_array($status, ['failed', 'failure', 'error', 'reversed', 'rejected'], true)) {
            return 'failed';
        }

        $event = strtolower(trim((string) ($response['event_type'] ?? $response['event'] ?? $response['type'] ?? '')));
        if ($event === 'payout.processed') {
            return 'successful';
        }

        return 'processing';
    }

    public function providerReference(array $response, string $fallback = ''): string
    {
        foreach ([
            $response['data'] ?? null,
            $response['data']['payout'] ?? null,
            $response['data']['transaction'] ?? null,
            $response['payout'] ?? null,
            $response['transaction'] ?? null,
            $response,
        ] as $source) {
            if (!is_array($source)) {
                continue;
            }

            $reference = $this->firstString($source, [
                'reference',
                'payout_reference',
                'transaction_reference',
                'transactionReference',
                'id',
                'order_no',
                'session_id',
            ]);
            if ($reference !== '') {
                return $reference;
            }
        }

        return $fallback;
    }

    public function redactResponse(array $response): array
    {
        $redacted = [];
        foreach ($response as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (preg_match('/authorization|secret|api.?key|token|signature|bvn|nin|password|pin/i', $normalizedKey)) {
                $redacted[$key] = '[redacted]';
                continue;
            }

            if (preg_match('/account.?number/i', $normalizedKey) && is_scalar($value)) {
                $redacted[$key] = $this->mask((string) $value);
                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redactResponse($value) : $value;
        }

        return $redacted;
    }

    private function request(string $method, string $path, array $payload = [], ?string $baseUrlOverride = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for KatPay payout integration.');
        }

        $baseUrl = rtrim($baseUrlOverride !== null && trim($baseUrlOverride) !== ''
            ? $baseUrlOverride
            : (string) config('payments.katpay_base_url', 'https://api.katpay.co/v1'), '/');
        $apiKey = trim((string) config('payments.katpay_api_key', ''));
        $secretKey = trim((string) config('payments.katpay_secret_key', ''));
        if ($apiKey === '' || $secretKey === '') {
            throw new RuntimeException($this->configurationMessage());
        }

        $handle = curl_init($baseUrl . '/' . ltrim($path, '/'));
        $headers = [
            'Authorization: Bearer ' . $secretKey,
            'api-key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ];
        if (strtoupper($method) === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload);
        }
        curl_setopt_array($handle, $options);

        $body = curl_exec($handle);
        $curlError = curl_error($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($body === false) {
            throw new RuntimeException('KatPay request failed: ' . $curlError);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('KatPay returned an invalid response.');
        }

        $decoded['_http_status'] = $statusCode;
        if ($statusCode >= 400) {
            throw new RuntimeException((string) ($decoded['message'] ?? $decoded['error'] ?? 'KatPay payout request failed.'));
        }

        return $decoded;
    }

    private function extractList(array $response): array
    {
        foreach ([
            $response['data']['banks'] ?? null,
            $response['data']['bankList'] ?? null,
            $response['data'] ?? null,
            $response['banks'] ?? null,
            $response,
        ] as $candidate) {
            if (is_array($candidate) && $this->isList($candidate)) {
                return $candidate;
            }
        }

        return [];
    }

    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    private function firstString(array $source, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $source[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function mask(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            return '[redacted]';
        }

        return strlen($digits) <= 4 ? str_repeat('*', strlen($digits)) : str_repeat('*', max(4, strlen($digits) - 4)) . substr($digits, -4);
    }
}
