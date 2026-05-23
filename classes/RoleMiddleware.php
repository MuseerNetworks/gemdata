<?php

declare(strict_types=1);

namespace GemData\Classes;

class RoleMiddleware
{
    public function __construct(private UserRoleManager $roles)
    {
    }

    public function requireRole(array $user, string $requiredRole, string $redirectPath = 'user/dashboard.php'): void
    {
        if (!$this->roles->canAccess($user, $requiredRole)) {
            flash('error', 'That area is not available for your account level yet.');
            redirect(base_url($redirectPath));
        }
    }
}
