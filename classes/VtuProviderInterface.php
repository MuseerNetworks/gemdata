<?php

declare(strict_types=1);

namespace GemData\Classes;

interface VtuProviderInterface
{
    public function purchase(string $serviceSlug, array $payload): array;
}
