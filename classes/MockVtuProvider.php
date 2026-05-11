<?php

declare(strict_types=1);

namespace GemData\Classes;

class MockVtuProvider implements VtuProviderInterface
{
    public function code(): string
    {
        return 'mock';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function healthCheck(): array
    {
        return [
            'provider' => 'mock',
            'status' => 'sandbox',
            'sandbox' => true,
            'checked_at' => date('c'),
        ];
    }

    public function purchase(string $serviceSlug, array $payload): array
    {
        $recipient = (string) ($payload['recipient'] ?? '');
        $amount = (float) ($payload['amount'] ?? 0);
        $providerReference = strtoupper('PRV' . bin2hex(random_bytes(5)));
        $shouldFail = str_contains($recipient, '999') || $amount <= 0;

        return [
            'status' => $shouldFail ? 'failed' : 'successful',
            'provider_reference' => $providerReference,
            'amount' => $amount,
            'recipient' => $recipient,
            'raw' => [
                'service' => $serviceSlug,
                'simulated_at' => date('c'),
                'network_delay_ms' => random_int(80, 250),
            ],
        ];
    }

    public function checkBalance(): array
    {
        return [
            'status' => 'successful',
            'balance' => 0.0,
            'currency' => 'NGN',
            'provider_reference' => null,
            'raw' => ['provider' => 'mock'],
        ];
    }

    public function queryTransaction(string $reference): array
    {
        return [
            'status' => 'pending',
            'provider_reference' => trim($reference) !== '' ? trim($reference) : null,
            'raw' => ['provider' => 'mock', 'message' => 'Mock transaction query is informational only.'],
        ];
    }
}
