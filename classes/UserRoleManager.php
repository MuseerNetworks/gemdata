<?php

declare(strict_types=1);

namespace GemData\Classes;

class UserRoleManager
{
    public function roleFor(array $user): string
    {
        $type = strtolower(trim((string) ($user['user_type'] ?? '')));
        if (in_array($type, ['smart', 'reseller', 'api'], true)) {
            return $type;
        }

        if ((int) ($user['is_api_user'] ?? 0) === 1) {
            return 'api';
        }

        $tier = strtoupper(trim((string) ($user['tier'] ?? '')));
        if (in_array($tier, ['RESELLER', 'AGENT'], true)) {
            return 'reseller';
        }
        if ($tier === 'API_RESELLER') {
            return 'api';
        }

        return 'smart';
    }

    public function label(string $role): string
    {
        return match ($role) {
            'api' => 'API User',
            'reseller' => 'Reseller',
            default => 'Smart User',
        };
    }

    public function nextRole(string $role): ?string
    {
        return match ($role) {
            'smart' => 'reseller',
            'reseller' => 'api',
            default => null,
        };
    }

    public function canAccess(array $user, string $requiredRole): bool
    {
        $rank = ['smart' => 1, 'reseller' => 2, 'api' => 3];
        $actual = $this->roleFor($user);

        return ($rank[$actual] ?? 1) >= ($rank[$requiredRole] ?? 1);
    }
}
