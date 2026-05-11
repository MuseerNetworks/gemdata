<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

abstract class AbstractProviderAdapter implements VtuProviderInterface
{
    public function __construct(
        protected array $config,
        protected AppLogger $logger
    ) {
    }

    public function isConfigured(): bool
    {
        return trim((string) ($this->config['base_url'] ?? '')) !== ''
            && trim((string) ($this->config['api_key'] ?? '')) !== '';
    }

    public function healthCheck(): array
    {
        return [
            'provider' => $this->code(),
            'status' => ($this->config['enabled'] ?? false) ? ($this->isConfigured() ? 'ready' : 'misconfigured') : 'disabled',
            'sandbox' => !empty($this->config['sandbox']),
            'checked_at' => date('c'),
        ];
    }

    protected function ensureReady(string $serviceSlug): void
    {
        if (empty($this->config['enabled'])) {
            throw new RuntimeException(sprintf('Provider %s is disabled.', $this->code()));
        }

        if (!$this->isConfigured()) {
            throw new RuntimeException(sprintf('Provider %s is not configured for %s.', $this->code(), $serviceSlug));
        }
    }

    protected function unsupportedPurchase(string $serviceSlug, array $payload): array
    {
        $this->ensureReady($serviceSlug);

        $recipient = (string) ($payload['recipient'] ?? '');
        $amount = (float) ($payload['amount'] ?? 0);
        $this->logger->warning('Provider adapter is running in placeholder mode.', [
            'provider' => $this->code(),
            'service' => $serviceSlug,
        ]);

        return [
            'status' => 'failed',
            'provider_reference' => null,
            'amount' => $amount,
            'recipient' => $recipient,
            'raw' => [
                'provider' => $this->code(),
                'message' => 'Adapter is enabled but still requires endpoint mapping for this provider.',
            ],
        ];
    }

    public function checkBalance(): array
    {
        return [
            'status' => empty($this->config['enabled']) ? 'disabled' : 'failed',
            'balance' => null,
            'currency' => 'NGN',
            'provider_reference' => null,
            'raw' => ['message' => 'Balance check is not implemented for this provider adapter yet.'],
        ];
    }

    public function queryTransaction(string $reference): array
    {
        $reference = trim($reference);

        return [
            'status' => 'failed',
            'provider_reference' => $reference !== '' ? $reference : null,
            'raw' => ['message' => 'Transaction query is not implemented for this provider adapter yet.'],
        ];
    }
}
