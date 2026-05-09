<?php

declare(strict_types=1);

namespace GemData\Classes;

class ClubKonnectProvider extends AbstractProviderAdapter
{
    public function code(): string
    {
        return 'clubkonnect';
    }

    public function purchase(string $serviceSlug, array $payload): array
    {
        return $this->unsupportedPurchase($serviceSlug, $payload);
    }
}
