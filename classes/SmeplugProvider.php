<?php

declare(strict_types=1);

namespace GemData\Classes;

class SmeplugProvider extends AbstractProviderAdapter
{
    public function code(): string
    {
        return 'smeplug';
    }

    public function purchase(string $serviceSlug, array $payload): array
    {
        return $this->unsupportedPurchase($serviceSlug, $payload);
    }
}
