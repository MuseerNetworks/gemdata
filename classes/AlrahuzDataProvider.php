<?php

declare(strict_types=1);

namespace GemData\Classes;

class AlrahuzDataProvider extends AbstractProviderAdapter
{
    public function code(): string
    {
        return 'alrahuzdata';
    }

    public function purchase(string $serviceSlug, array $payload): array
    {
        return $this->unsupportedPurchase($serviceSlug, $payload);
    }
}
