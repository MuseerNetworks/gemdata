<?php

declare(strict_types=1);

namespace GemData\Classes;

class MockVtuProvider implements VtuProviderInterface
{
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
}
