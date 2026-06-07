<?php

declare(strict_types=1);

namespace GemData\Classes;

class AlrahuzDataProvider extends DjangoStyleVtuProvider
{
    public function code(): string
    {
        return 'alrahuzdata';
    }

    protected function supportsExamPin(): bool
    {
        return true;
    }
}
