<?php

declare(strict_types=1);

namespace GemData\Classes;

interface VtuProviderInterface
{
    public function code(): string;

    public function isConfigured(): bool;

    public function healthCheck(): array;

    public function purchase(string $serviceSlug, array $payload): array;
}
