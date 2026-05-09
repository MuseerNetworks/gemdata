<?php

declare(strict_types=1);

namespace GemData\Classes;

class VTpassProvider extends AbstractProviderAdapter
{
    public function code(): string
    {
        return 'vtpass';
    }

    public function purchase(string $serviceSlug, array $payload): array
    {
        return $this->unsupportedPurchase($serviceSlug, $payload);
    }
}
