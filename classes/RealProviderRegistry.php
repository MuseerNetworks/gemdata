<?php

declare(strict_types=1);

namespace GemData\Classes;

final class RealProviderRegistry
{
    public const CODES = ['albani', 'alrahuzdata', 'abbpantami', 'cheapdatahub'];
    public const DRIVERS = ['albani', 'alrahuzdata', 'abbpantami', 'cheapdatahub'];

    public static function isAllowedCode(string $code): bool
    {
        return in_array(strtolower(trim($code)), self::CODES, true);
    }

    public static function isAllowedDriver(string $driver): bool
    {
        return in_array(strtolower(trim($driver)), self::DRIVERS, true);
    }

    public static function sqlInList(array $values): string
    {
        return "'" . implode("','", array_map(static fn(string $value): string => str_replace("'", "''", $value), $values)) . "'";
    }
}
