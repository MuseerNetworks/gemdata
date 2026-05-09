<?php

declare(strict_types=1);

namespace GemData\Classes;

class EasyAccessApiProvider extends AbstractProviderAdapter
{
    public function code(): string
    {
        return 'easyaccessapi';
    }

    public function purchase(string $serviceSlug, array $payload): array
    {
        return $this->unsupportedPurchase($serviceSlug, $payload);
    }
}
