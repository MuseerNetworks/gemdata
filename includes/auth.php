<?php

declare(strict_types=1);

use GemData\Classes\AdminService;
use GemData\Classes\SessionAuth;

function auth(): SessionAuth
{
    return app(SessionAuth::class);
}

function user(): ?array
{
    return auth()->user();
}

function admin_user(): ?array
{
    return auth()->admin();
}

function admin_service(): AdminService
{
    return app(AdminService::class);
}

function admin_permissions(): array
{
    $admin = admin_user();
    if (!$admin) {
        return [];
    }

    return admin_service()->permissionsForAdmin((int) $admin['id']);
}

function admin_can(string $permission): bool
{
    return admin_service()->hasPermission(admin_user(), $permission);
}

function require_user(): array
{
    $user = auth()->requireUser();
    if (!$user) {
        redirect(base_url('user/login.php'));
    }
    if (session_timed_out()) {
        auth()->logoutUser();
        flash('success', 'Your session expired. Please sign in again.');
        redirect(base_url('user/login.php'));
    }
    $_SESSION['last_activity_at'] = time();
    return $user;
}

function require_admin(): array
{
    $admin = auth()->requireAdmin();
    if (!$admin) {
        redirect(base_url('admin/login.php'));
    }
    if (session_timed_out()) {
        auth()->logoutAdmin();
        flash('success', 'Your admin session expired. Please sign in again.');
        redirect(base_url('admin/login.php'));
    }
    $_SESSION['last_activity_at'] = time();
    $currentScript = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ((int) ($admin['force_password_change'] ?? 0) === 1 && $currentScript !== 'change-password.php') {
        redirect(base_url('admin/change-password.php'));
    }
    return $admin;
}

function require_permission(string $permission): array
{
    $admin = require_admin();
    if (!admin_can($permission)) {
        http_response_code(403);
        render_header('Access Denied', 'admin');
        echo '<div class="surface-card p-6"><h1 class="text-3xl font-black text-white">Access denied</h1><p class="mt-3 text-slate-300">Your admin role does not have permission to access this area.</p></div>';
        render_footer();
        exit;
    }

    return $admin;
}

function session_timed_out(): bool
{
    $lastActivity = (int) ($_SESSION['last_activity_at'] ?? 0);
    if ($lastActivity === 0) {
        return false;
    }

    return (time() - $lastActivity) > (int) config('session.timeout_seconds', 1800);
}
