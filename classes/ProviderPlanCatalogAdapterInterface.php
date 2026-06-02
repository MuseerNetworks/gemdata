<?php

declare(strict_types=1);

namespace GemData\Classes;

interface ProviderPlanCatalogAdapterInterface
{
    public function supportsSync(array $provider): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function syncPlans(array $provider, array $service): array;
}
