<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class ProviderSelectionException extends RuntimeException
{
    public function __construct(
        string $message,
        private array $diagnostic = []
    ) {
        parent::__construct($message);
    }

    public function diagnostic(): array
    {
        return $this->diagnostic;
    }
}
