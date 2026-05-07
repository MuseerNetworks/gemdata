<?php

declare(strict_types=1);

use GemData\Classes\Database;

function db(): Database
{
    return app(Database::class);
}
