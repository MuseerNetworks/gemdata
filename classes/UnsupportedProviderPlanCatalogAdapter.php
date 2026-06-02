<?php

declare(strict_types=1);

namespace GemData\Classes;

class UnsupportedProviderPlanCatalogAdapter implements ProviderPlanCatalogAdapterInterface
{
    public function supportsSync(array $provider): bool
    {
        return false;
    }

    public function syncPlans(array $provider, array $service): array
    {
        return [];
    }
}
