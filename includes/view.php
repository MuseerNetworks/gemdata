<?php

declare(strict_types=1);

function current_relative_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = str_replace('\\', '/', rtrim((string) config('app.base_url', ''), '/'));
    if ($base !== '' && str_starts_with($script, $base . '/')) {
        $script = substr($script, strlen($base) + 1);
    }
    return ltrim($script, '/');
}

function current_page_key(string $section, string $path): string
{
    if ($section === 'admin') {
        return match (true) {
            str_contains($path, 'admin/users.php') => 'users',
            str_contains($path, 'admin/services.php') => 'services',
            str_contains($path, 'admin/service-control.php') => 'service-control',
            str_contains($path, 'admin/transactions.php') => 'transactions',
        str_contains($path, 'admin/wallet.php') => 'wallet',
        str_contains($path, 'admin/finance.php') => 'finance',
        str_contains($path, 'admin/providers.php') => 'providers',
            str_contains($path, 'admin/api-users.php') => 'api-users',
            str_contains($path, 'admin/withdrawals.php') => 'withdrawals',
            str_contains($path, 'admin/reports.php') => 'reports',
            str_contains($path, 'admin/security.php') => 'security',
            str_contains($path, 'admin/roles.php'),
            str_contains($path, 'admin/invites.php'),
            str_contains($path, 'admin/accept-invite.php') => 'roles',
            str_contains($path, 'admin/upgrades.php') => 'upgrades',
            str_contains($path, 'admin/alerts.php') => 'alerts',
            str_contains($path, 'admin/notifications.php') => 'notifications',
            str_contains($path, 'admin/settings.php') => 'settings',
            default => 'overview',
        };
    }

    return match (true) {
        str_contains($path, 'user/transactions.php') => 'transactions',
        str_contains($path, 'user/fund-wallet.php') => 'wallet',
        str_contains($path, 'user/buy-airtime.php') => 'airtime',
        str_contains($path, 'user/buy-data.php') => 'data',
        str_contains($path, 'user/cable-tv.php') => 'cable-tv',
        str_contains($path, 'user/electricity.php') => 'electricity',
        str_contains($path, 'user/exam-pin.php') => 'exam-pin',
        str_contains($path, 'user/bulk-sms.php') => 'bulk-sms',
        str_contains($path, 'user/customers.php') => 'customers',
        str_contains($path, 'user/pricing.php') => 'pricing',
        str_contains($path, 'user/referrals.php') => 'referrals',
        str_contains($path, 'user/support.php') => 'support',
        str_contains($path, 'user/profile.php') => 'settings',
        str_contains($path, 'user/api-center.php') => 'api-center',
        str_contains($path, 'user/api-docs.php') => 'api-docs',
        str_contains($path, 'user/webhooks.php') => 'webhooks',
        str_contains($path, 'user/request-logs.php') => 'api-logs',
        str_contains($path, 'user/billing.php') => 'billing',
        str_contains($path, 'user/api-dashboard.php') => 'api-center',
        str_contains($path, 'user/api-keys.php') => 'api-keys',
        str_contains($path, 'user/api-logs.php') => 'api-logs',
        str_contains($path, 'user/commission.php') => 'commission',
        str_contains($path, 'user/withdrawals.php') => 'withdrawals',
        str_contains($path, 'user/upgrade-request.php') => 'upgrade',
        str_contains($path, 'user/settings.php') => 'settings',
        str_contains($path, 'user/notifications.php') => 'notifications',
        default => 'dashboard',
    };
}

function nav_items(string $section): array
{
    if ($section === 'admin') {
        $items = [
            ['key' => 'overview', 'label' => 'Overview', 'href' => base_url('admin/dashboard.php'), 'icon' => 'dashboard'],
            ['key' => 'users', 'label' => 'Users', 'href' => base_url('admin/users.php'), 'icon' => 'users'],
            ['key' => 'transactions', 'label' => 'Transactions', 'href' => base_url('admin/transactions.php'), 'icon' => 'transactions'],
            ['key' => 'services', 'label' => 'Services', 'href' => base_url('admin/services.php'), 'icon' => 'services'],
            ['key' => 'service-control', 'label' => 'Service Control', 'href' => base_url('admin/service-control.php'), 'icon' => 'services'],
            ['key' => 'wallet', 'label' => 'Wallet Control', 'href' => base_url('admin/wallet.php'), 'icon' => 'wallet'],
            ['key' => 'finance', 'label' => 'Finance Ledger', 'href' => base_url('admin/finance.php'), 'icon' => 'profit'],
            ['key' => 'providers', 'label' => 'Providers', 'href' => base_url('admin/providers.php'), 'icon' => 'server'],
            ['key' => 'api-users', 'label' => 'API Users', 'href' => base_url('admin/api-users.php'), 'icon' => 'code'],
            ['key' => 'withdrawals', 'label' => 'Withdrawals', 'href' => base_url('admin/withdrawals.php'), 'icon' => 'wallet'],
            ['key' => 'reports', 'label' => 'Reports', 'href' => base_url('admin/reports.php'), 'icon' => 'chart'],
            ['key' => 'security', 'label' => 'Security Center', 'href' => base_url('admin/security.php'), 'icon' => 'shield'],
            ['key' => 'roles', 'label' => 'Roles & Invites', 'href' => base_url('admin/roles.php'), 'icon' => 'shield'],
            ['key' => 'upgrades', 'label' => 'Upgrade Requests', 'href' => base_url('admin/upgrades.php'), 'icon' => 'shield'],
            ['key' => 'alerts', 'label' => 'Alerts', 'href' => base_url('admin/alerts.php'), 'icon' => 'notification'],
            ['key' => 'notifications', 'label' => 'Notifications', 'href' => base_url('admin/notifications.php'), 'icon' => 'notification'],
            ['key' => 'settings', 'label' => 'Settings', 'href' => base_url('admin/settings.php'), 'icon' => 'settings'],
            ['key' => 'logout', 'label' => 'Logout', 'href' => base_url('admin/logout.php'), 'icon' => 'logout', 'method' => 'post'],
        ];

        return array_values(array_filter($items, static function (array $item): bool {
            return match ($item['key']) {
                'users' => admin_can('users.view'),
                'transactions' => admin_can('transactions.view'),
                'services', 'service-control' => admin_can('services.manage'),
                'wallet', 'finance' => admin_can('wallet.manage'),
                'providers' => admin_can('providers.manage'),
                'api-users' => admin_can('users.manage') || admin_can('api.manage'),
                'withdrawals' => admin_can('wallet.manage'),
                'reports' => admin_can('reports.view'),
                'security' => admin_can('security.manage') || admin_can('alerts.manage') || admin_can('roles.manage'),
                'roles' => admin_can('roles.manage'),
                'upgrades' => admin_can('upgrades.manage') || admin_can('users.manage') || admin_can('roles.manage'),
                'alerts', 'notifications' => admin_can('alerts.manage'),
                'settings' => admin_can('settings.manage'),
                default => true,
            };
        }));
    }

    $role = 'smart';
    if ($user = user()) {
        $role = app(\GemData\Classes\UserRoleManager::class)->roleFor($user);
    }

    $items = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => base_url('user/dashboard.php'), 'icon' => 'dashboard'],
        ['key' => 'wallet', 'label' => 'Fund Wallet', 'href' => base_url('user/fund-wallet.php'), 'icon' => 'wallet'],
        ['key' => 'services', 'label' => 'Buy Services', 'href' => base_url('user/dashboard.php#services'), 'icon' => 'services', 'data_key' => 'services'],
        ['key' => 'transactions', 'label' => 'Transactions', 'href' => base_url('user/transactions.php'), 'icon' => 'transactions'],
        ['key' => 'referrals', 'label' => 'Referrals', 'href' => base_url('user/dashboard.php#growth'), 'icon' => 'users', 'data_key' => 'growth'],
    ];

    if (in_array($role, ['reseller', 'api'], true)) {
        $items[] = ['key' => 'bulk', 'label' => 'Bulk Purchase', 'href' => base_url('user/dashboard.php#reseller-tools'), 'icon' => 'services', 'data_key' => 'reseller-tools'];
        $items[] = ['key' => 'customers', 'label' => 'Customers', 'href' => base_url('user/dashboard.php#reseller-tools'), 'icon' => 'users', 'data_key' => 'reseller-tools'];
        $items[] = ['key' => 'pricing', 'label' => 'Pricing', 'href' => base_url('user/dashboard.php#pricing'), 'icon' => 'chart', 'data_key' => 'pricing'];
        $items[] = ['key' => 'commission', 'label' => 'Commission Wallet', 'href' => base_url('user/commission.php'), 'icon' => 'wallet'];
        $items[] = ['key' => 'withdrawals', 'label' => 'Request Commission Withdrawal', 'href' => base_url('user/withdrawals.php'), 'icon' => 'refund'];
        $items[] = ['key' => 'reports', 'label' => 'Reports', 'href' => base_url('user/commission.php'), 'icon' => 'chart'];
    }

    if ($role === 'api') {
        $items[] = ['key' => 'api-center', 'label' => 'API Center', 'href' => base_url('user/api-dashboard.php'), 'icon' => 'code'];
        $items[] = ['key' => 'api-keys', 'label' => 'API Keys', 'href' => base_url('user/api-keys.php'), 'icon' => 'key'];
        $items[] = ['key' => 'api-docs', 'label' => 'API Docs', 'href' => base_url('docs/api.php'), 'icon' => 'server'];
        $items[] = ['key' => 'webhooks', 'label' => 'Webhooks', 'href' => base_url('user/dashboard.php#developer-center'), 'icon' => 'server', 'data_key' => 'developer-center'];
        $items[] = ['key' => 'api-logs', 'label' => 'Logs', 'href' => base_url('user/api-logs.php'), 'icon' => 'transactions'];
        $items[] = ['key' => 'billing', 'label' => 'Billing', 'href' => base_url('user/dashboard.php#developer-center'), 'icon' => 'wallet', 'data_key' => 'developer-center'];
    } else {
        $items[] = ['key' => 'upgrade', 'label' => 'Upgrade', 'href' => base_url('user/upgrade-request.php'), 'icon' => 'shield'];
    }

    $items[] = ['key' => 'settings', 'label' => 'Settings', 'href' => base_url('user/settings.php'), 'icon' => 'settings'];
    $items[] = ['key' => 'support', 'label' => 'Support', 'href' => base_url('user/dashboard.php#support'), 'icon' => 'notification', 'data_key' => 'support'];
    $items[] = ['key' => 'logout', 'label' => 'Logout', 'href' => base_url('user/logout.php'), 'icon' => 'logout', 'method' => 'post'];

    return $items;
}

function icon_svg(string $name): string
{
    return match ($name) {
        'menu' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>',
        'search' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803 7.5 7.5 0 0015.803 15.803z"/></svg>',
        'dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
        'airtime' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18M3 10h18M3 15h12M3 20h7"/><circle cx="19.5" cy="17.5" r="3"/></svg>',
        'data' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12 20.25h.008v.008H12v-.008z"/></svg>',
        'cable_tv' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="15" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 12v4m-2-2h4"/></svg>',
        'electricity' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>',
        'exam_pin' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>',
        'bulk_sms' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>',
        'plus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>',
        'services' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>',
        'services-mobile' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 7.5h15M6.75 4.5h10.5a2.25 2.25 0 012.25 2.25v10.5a2.25 2.25 0 01-2.25 2.25H6.75a2.25 2.25 0 01-2.25-2.25V6.75A2.25 2.25 0 016.75 4.5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 11h3v3H8zm5 0h3v3h-3zm-5 5h8"/></svg>',
        'transactions' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>',
        'wallet' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 9.563C9 9.252 9.252 9 9.563 9h4.874c.311 0 .563.252.563.563v4.874c0 .311-.252.563-.563.563H9.564A.562.562 0 019 14.437V9.564z"/></svg>',
        'chart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>',
        'revenue' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M8 15l3.25-3.25 2.5 2.5L19 8.75"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 8h3v3"/></svg>',
        'profit' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m4.5-14.25H9.75a3 3 0 000 6h4.5a3 3 0 010 6H6.75"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.5 5.5 20 4m-1.5 14.5L20 20"/></svg>',
        'pending' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5V12l3 2"/></svg>',
        'failed' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/><path stroke-linecap="round" stroke-linejoin="round" d="M10.29 3.86 1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>',
        'server' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path stroke-linecap="round" d="M2 10h20"/></svg>',
        'code' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5"/></svg>',
        'key' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="7.5" cy="14.5" r="3.5"/><path d="M10 12 21 1"/><path d="m16 6 2 2"/><path d="m14 8 2 2"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>',
        'profile' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>',
        'notification' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>',
        'view' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12z"/><circle cx="12" cy="12" r="2.75"/></svg>',
        'edit' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20h4l10.5-10.5a2.12 2.12 0 00-3-3L5 17v3z"/><path stroke-linecap="round" stroke-linejoin="round" d="m14.5 7.5 2 2"/></svg>',
        'approve' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>',
        'reject' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>',
        'retry' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7v5h-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 17v-5h5"/><path stroke-linecap="round" stroke-linejoin="round" d="M6.1 9A7 7 0 0118.6 7.8L20 12M4 12l1.4 4.2A7 7 0 0017.9 15"/></svg>',
        'refund' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14 4 9l5-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 9h11a5 5 0 010 10h-1"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 12.5v5"/></svg>',
        'archive' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="4" rx="1"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8M10 12h4"/></svg>',
        'filter' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M7 12h10M10 19h4"/></svg>',
        'export' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 19h14"/></svg>',
        'close' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>',
        'chevron' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>',
        default => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/></svg>',
    };
}

function render_sidebar(array $items, string $activeKey, string $section): void
{
    $brandSubtitle = 'Workspace';
    if ($section === 'admin') {
        $brandSubtitle = 'Admin Console';
    } elseif ($user = user()) {
        $roles = app(\GemData\Classes\UserRoleManager::class);
        $brandSubtitle = $roles->label($roles->roleFor($user));
    }
    ?>
    <aside class="app-sidebar" id="app-sidebar" data-section="<?= e($section); ?>">
        <div class="sidebar-brand">
            <a href="<?= e(base_url()); ?>" class="sidebar-logo-link" aria-label="GemData Home">
                <?= gemdata_logo('icon', 'auto', 'sidebar-logo-icon', 'GemData'); ?>
            </a>
            <div class="sidebar-brand-text">
                <p class="brand-title">GemData</p>
                <p class="brand-subtitle"><?= e($brandSubtitle); ?></p>
            </div>
        </div>
        <nav class="sidebar-nav">
            <?php foreach ($items as $item): ?>
                <?php
                $isActive = $activeKey === ($item['data_key'] ?? $item['key']);
                $itemLabel = $item['label'];
                $href = $item['href'];
                ?>
                <a class="sidebar-link<?= $isActive ? ' is-active' : ''; ?>" href="<?= e($href); ?>" data-nav-key="<?= e($item['data_key'] ?? $item['key']); ?>">
                    <span class="sidebar-icon"><?= icon_svg($item['icon']); ?></span>
                    <span><?= e($itemLabel); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <?php
}

function user_nav_groups(string $role): array
{
    if ($role === 'api') {
        return [
            'Main Menu' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => base_url('user/dashboard.php'), 'icon' => 'dashboard'],
                ['key' => 'wallet', 'label' => 'Wallet', 'href' => base_url('user/fund-wallet.php'), 'icon' => 'wallet'],
                ['key' => 'api-center', 'label' => 'API Center', 'href' => base_url('user/api-center.php'), 'icon' => 'code'],
                ['key' => 'api-keys', 'label' => 'API Keys', 'href' => base_url('user/api-keys.php'), 'icon' => 'key'],
                ['key' => 'api-docs', 'label' => 'API Docs', 'href' => base_url('user/api-docs.php'), 'icon' => 'server'],
                ['key' => 'webhooks', 'label' => 'Webhooks', 'href' => base_url('user/webhooks.php'), 'icon' => 'server'],
                ['key' => 'api-logs', 'label' => 'Request Logs', 'href' => base_url('user/request-logs.php'), 'icon' => 'transactions'],
                ['key' => 'billing', 'label' => 'Billing', 'href' => base_url('user/billing.php'), 'icon' => 'wallet'],
                ['key' => 'transactions', 'label' => 'Transactions', 'href' => base_url('user/transactions.php'), 'icon' => 'transactions'],
                ['key' => 'commission', 'label' => 'Commission Wallet', 'href' => base_url('user/commission.php'), 'icon' => 'wallet'],
                ['key' => 'withdrawals', 'label' => 'Request Commission Withdrawal', 'href' => base_url('user/withdrawals.php'), 'icon' => 'refund'],
            ],
            'Help' => [
                ['key' => 'support', 'label' => 'Support', 'href' => base_url('user/support.php'), 'icon' => 'notification'],
                ['key' => 'settings', 'label' => 'Settings', 'href' => base_url('user/settings.php'), 'icon' => 'settings'],
            ],
        ];
    }

    $main = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => base_url('user/dashboard.php'), 'icon' => 'dashboard'],
        ['key' => 'wallet', 'label' => 'Wallet', 'href' => base_url('user/fund-wallet.php'), 'icon' => 'wallet'],
        ['key' => 'services', 'label' => 'Buy Services', 'href' => base_url('user/dashboard.php#services'), 'icon' => 'services'],
        ['key' => 'transactions', 'label' => 'Transactions', 'href' => base_url('user/transactions.php'), 'icon' => 'transactions'],
    ];

    if ($role === 'reseller') {
        $main[] = ['key' => 'bulk-sms', 'label' => 'Bulk Purchase', 'href' => base_url('user/bulk-sms.php'), 'icon' => 'services'];
        $main[] = ['key' => 'customers', 'label' => 'Customers', 'href' => base_url('user/customers.php'), 'icon' => 'users'];
        $main[] = ['key' => 'pricing', 'label' => 'Pricing', 'href' => base_url('user/pricing.php'), 'icon' => 'chart'];
        $main[] = ['key' => 'commission', 'label' => 'Commission Wallet', 'href' => base_url('user/commission.php'), 'icon' => 'wallet'];
        $main[] = ['key' => 'withdrawals', 'label' => 'Request Commission Withdrawal', 'href' => base_url('user/withdrawals.php'), 'icon' => 'refund'];
        $main[] = ['key' => 'reports', 'label' => 'Reports', 'href' => base_url('user/commission.php'), 'icon' => 'chart'];
    }

    return [
        'Main Menu' => $main,
        'Growth' => [
            ['key' => 'referrals', 'label' => 'Referrals', 'href' => base_url('user/referrals.php'), 'icon' => 'users'],
            ['key' => 'upgrade', 'label' => $role === 'reseller' ? 'Request API Access' : 'Upgrade', 'href' => base_url('user/upgrade-request.php'), 'icon' => 'shield'],
        ],
        'Help' => [
            ['key' => 'support', 'label' => 'Support', 'href' => base_url('user/support.php'), 'icon' => 'notification'],
            ['key' => 'settings', 'label' => 'Settings', 'href' => base_url('user/settings.php'), 'icon' => 'settings'],
        ],
    ];
}

function render_user_sidebar(string $activeKey, array $user, string $role): void
{
    ?>
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0 lg:hidden" data-sidebar-close></div>
    <aside id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-white shadow-nav z-40 flex flex-col -translate-x-full lg:translate-x-0 transition-transform duration-300">
        <div class="flex items-center gap-3 px-5 py-5 border-b border-gem-border">
            <a href="<?= e(base_url('user/dashboard.php')); ?>" class="w-9 h-9 rounded-xl bg-gem-blue flex items-center justify-center shadow-panel flex-shrink-0" aria-label="GemData Dashboard">
                <?= gemdata_logo('icon', '28', 'rounded-lg', 'GemData'); ?>
            </a>
            <div class="leading-tight">
                <div class="font-extrabold text-[15px] text-gem-text tracking-tight">GemData</div>
                <div class="text-[10px] text-gem-muted font-semibold tracking-widest uppercase">VTU & Data Services</div>
            </div>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
            <?php foreach (user_nav_groups($role) as $group => $items): ?>
                <p class="text-[10px] font-bold text-gem-muted/60 uppercase tracking-widest px-3 pb-2 pt-<?= $group === 'Main Menu' ? '1' : '4'; ?>"><?= e($group); ?></p>
                <?php foreach ($items as $item): ?>
                    <?php $isActive = $activeKey === $item['key']; ?>
                    <?php if (($item['method'] ?? 'get') === 'post'): ?>
                        <form method="post" action="<?= e($item['href']); ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                            <button type="submit" class="nav-link<?= $isActive ? ' active' : ''; ?> flex w-full items-center gap-3 px-3 py-2.5 rounded-xl text-left text-[13.5px] <?= $isActive ? 'font-semibold text-gem-blue' : 'font-medium text-gem-muted'; ?>">
                                <span class="w-[18px] h-[18px] flex-shrink-0"><?= icon_svg($item['icon']); ?></span>
                                <?= e($item['label']); ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="<?= e($item['href']); ?>" class="nav-link<?= $isActive ? ' active' : ''; ?> flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13.5px] <?= $isActive ? 'font-semibold text-gem-blue' : 'font-medium text-gem-muted'; ?>">
                            <span class="w-[18px] h-[18px] flex-shrink-0"><?= icon_svg($item['icon']); ?></span>
                            <?= e($item['label']); ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <div class="border-t border-gem-border">
        <a href="<?= e(base_url('user/settings.php')); ?>" class="flex items-center gap-3 px-4 py-3.5">
            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center text-white font-bold text-sm flex-shrink-0"><?= e(strtoupper(substr((string) ($user['full_name'] ?? 'G'), 0, 1))); ?></div>
            <div class="flex-1 min-w-0">
                <div class="text-[13px] font-semibold text-gem-text truncate"><?= e((string) ($user['full_name'] ?? 'GemData User')); ?></div>
                <div class="text-[11px] text-gem-muted truncate"><?= e((string) ($user['email'] ?? '')); ?></div>
            </div>
            <span class="w-4 h-4 text-gem-muted flex-shrink-0"><?= icon_svg('chevron'); ?></span>
        </a>
        <form method="post" action="<?= e(base_url('user/logout.php')); ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <button type="submit" class="flex w-full items-center gap-3 px-4 py-3 text-left text-[13px] font-semibold text-gem-muted hover:text-gem-red hover:bg-red-50 transition-colors">
            <span class="w-[18px] h-[18px]"><?= icon_svg('logout'); ?></span>
            Logout
            </button>
        </form>
        </div>
    </aside>
    <?php
}

function render_header(string $title, string $section = 'user'): void
{
    $currentUser = user();
    $currentAdmin = admin_user();
    $currentPath = current_relative_path();
    $notificationsCount = $currentUser ? app(\GemData\Classes\NotificationService::class)->unreadCount((int) $currentUser['id']) : 0;
    $hasShell = ($section === 'user' && $currentUser) || ($section === 'admin' && $currentAdmin);
    $activeKey = current_page_key($section, $currentPath);
    $walletBalance = $currentUser ? app(\GemData\Classes\Wallet::class)->balance((int) $currentUser['id']) : null;
    $currentRole = 'smart';
    $currentRoleLabel = '';
    if ($currentUser) {
        $roles = app(\GemData\Classes\UserRoleManager::class);
        $currentRole = $roles->roleFor($currentUser);
        $currentRoleLabel = $roles->label($currentRole);
    }
    $identityName = $currentUser['full_name'] ?? $currentAdmin['full_name'] ?? 'Guest';
    $identityMeta = $currentUser['email'] ?? $currentAdmin['email'] ?? config('app.name');
    $adminRole = $currentAdmin['role_name'] ?? 'Administrator';
    $searchValue = trim((string) ($_GET['q'] ?? ''));
    $preservedQuery = query_except(['q', 'page', 'export']);
    $pwaTheme = (string) config('pwa.theme_color', '#0f172a');
    $pwaBackground = (string) config('pwa.background_color', '#f8fbff');
    $siteCssPath = dirname(__DIR__) . '/assets/css/site.css';
    $siteJsPath = dirname(__DIR__) . '/assets/js/app.js';
    $dashboardCssPath = dirname(__DIR__) . '/assets/css/dashboard-v2.css';
    $dashboardJsPath = dirname(__DIR__) . '/assets/js/dashboard-v2.js';
    $siteCssVersion = is_file($siteCssPath) ? (string) filemtime($siteCssPath) : '1';
    $siteJsVersion = is_file($siteJsPath) ? (string) filemtime($siteJsPath) : '1';
    $dashboardCssVersion = is_file($dashboardCssPath) ? (string) filemtime($dashboardCssPath) : '1';
    $dashboardJsVersion = is_file($dashboardJsPath) ? (string) filemtime($dashboardJsPath) : '1';
    $themeOptions = [
        'light-fintech' => ['name' => 'Light Fintech', 'copy' => 'Bright operational surfaces'],
        'warm-cream' => ['name' => 'Warm Cream', 'copy' => 'Softer premium neutrals'],
        'cool-glass' => ['name' => 'Cool Glass', 'copy' => 'Airy translucent workspace'],
        'dark' => ['name' => 'Dark Mode', 'copy' => 'Low-light focused view'],
    ];
    $GLOBALS['__gemdata_has_shell'] = $hasShell;
    $GLOBALS['__gemdata_shell_section'] = $section;
    $GLOBALS['__gemdata_active_key'] = $activeKey;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title); ?> | <?= e(config('app.name')); ?></title>
        <meta name="description" content="GemData — Fast, secure VTU platform for airtime, data bundles, electricity bills, and reseller operations in Nigeria.">
        <link rel="canonical" href="<?= e(rtrim(app_origin(), '/') . ($_SERVER['REQUEST_URI'] ?? '/')); ?>">
        <meta name="theme-color" content="<?= e($pwaTheme); ?>">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="<?= e((string) config('pwa.short_name', config('app.name'))); ?>">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="application-name" content="<?= e(config('app.name')); ?>">
        <meta name="msapplication-TileColor" content="<?= e($pwaTheme); ?>">
        <link rel="manifest" href="<?= e(base_url('manifest.json')); ?>">
        <!-- Favicon — PNG first, using base_url() for localhost + production compatibility -->
        <link rel="icon" type="image/png" sizes="32x32" href="<?= e(base_url('assets/brand/favicon-32x32.png')); ?>?v=20260522a">
        <link rel="icon" type="image/png" sizes="16x16" href="<?= e(base_url('assets/brand/favicon-16x16.png')); ?>?v=20260522a">
        <link rel="icon" type="image/png" sizes="48x48" href="<?= e(base_url('assets/brand/favicon-48x48.png')); ?>?v=20260522a">
        <link rel="shortcut icon" type="image/png" href="<?= e(base_url('assets/brand/favicon-32x32.png')); ?>?v=20260522a">
        <!-- Apple / iOS -->
        <link rel="apple-touch-icon" sizes="180x180" href="<?= e(base_url('assets/brand/apple-touch-icon.png')); ?>?v=20260522a">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="GemData">
        <!-- Android / Chrome -->
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="application-name" content="GemData">
        <meta name="theme-color" content="<?= e($pwaTheme); ?>">
        <!-- Windows -->
        <meta name="msapplication-TileColor" content="<?= e($pwaTheme); ?>">
        <meta name="msapplication-TileImage" content="/assets/brand/ms-tile-150.png">
        <meta name="msapplication-config" content="/browserconfig.xml">
        <!-- Open Graph -->
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="GemData">
        <meta property="og:title" content="<?= e($title); ?> | GemData">
        <meta property="og:description" content="GemData — Nigeria's fastest VTU platform for airtime, data bundles, electricity bills, and reseller API operations.">
        <meta property="og:image" content="<?= e(rtrim(app_origin(),'/')); ?>/assets/brand/og-image.png">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:url" content="<?= e(rtrim(app_origin(),'/') . ($_SERVER['REQUEST_URI'] ?? '/')); ?>">
        <!-- Twitter / X -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:site" content="@GemDataNG">
        <meta name="twitter:title" content="<?= e($title); ?> | GemData">
        <meta name="twitter:description" content="Fast, secure VTU for airtime, data, electricity, and reseller operations.">
        <meta name="twitter:image" content="<?= e(rtrim(app_origin(),'/')); ?>/assets/brand/og-image.png">
        <!-- Fonts -->
        <link rel="dns-prefetch" href="//cdn.tailwindcss.com">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800,900|inter:400,500,600,700,800,900&display=swap" rel="stylesheet">
        <script nonce="<?= e(csp_nonce()); ?>">
            tailwind = window.tailwind || {};
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'], mono: ['DM Mono', 'monospace'] },
                        colors: {
                            gem: {
                                blue: '#1B4DFF',
                                blueDk: '#1238CC',
                                blueLt: '#EEF2FF',
                                teal: '#00C6AE',
                                orange: '#FF7A00',
                                yellow: '#FFB800',
                                green: '#12B76A',
                                red: '#F04438',
                                purple: '#7C3AED',
                                gray: '#F8FAFC',
                                border: '#E2E8F0',
                                text: '#0F172A',
                                muted: '#64748B'
                            }
                        },
                        boxShadow: {
                            card: '0 1px 3px rgba(0,0,0,.07), 0 1px 2px rgba(0,0,0,.04)',
                            panel: '0 4px 24px rgba(27,77,255,.10)',
                            nav: '0 2px 12px rgba(0,0,0,.06)'
                        }
                    }
                }
            };
            (function () {
                var theme = localStorage.getItem('gemdata-theme') || 'light-fintech';
                document.documentElement.setAttribute('data-theme', theme);
                window.GEMDATA_RUNTIME = {
                    baseUrl: <?= json_encode(base_url()); ?>,
                    appName: <?= json_encode((string) config('app.name')); ?>,
                    publicOrigin: <?= json_encode(app_origin()); ?>,
                    offlinePage: <?= json_encode((string) config('pwa.offline_page', base_url('offline.html'))); ?>,
                    themeColor: <?= json_encode($pwaTheme); ?>,
                    backgroundColor: <?= json_encode($pwaBackground); ?>,
                    section: <?= json_encode($section); ?>,
                    pageKey: <?= json_encode($activeKey); ?>
                };
            }());
        </script>
        <script nonce="<?= e(csp_nonce()); ?>" src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="<?= e(base_url('assets/css/site.css') . '?v=' . $siteCssVersion); ?>">
        <?php if ($section === 'user'): ?>
            <link rel="stylesheet" href="<?= e(base_url('assets/css/dashboard-v2.css') . '?v=' . $dashboardCssVersion); ?>">
        <?php endif; ?>
        <script nonce="<?= e(csp_nonce()); ?>" defer src="<?= e(base_url('assets/js/app.js') . '?v=' . $siteJsVersion); ?>"></script>
        <?php if ($section === 'user'): ?>
            <script nonce="<?= e(csp_nonce()); ?>" defer src="<?= e(base_url('assets/js/dashboard-v2.js') . '?v=' . $dashboardJsVersion); ?>"></script>
        <?php endif; ?>
    </head>
    <body class="app-body<?= $hasShell ? ' app-shell-body' : ' app-guest-body'; ?>" data-app-section="<?= e($section); ?>" data-page-key="<?= e($activeKey); ?>">
        <a class="skip-link" href="#app-main-content">Skip to main content</a>
        <?php if ($hasShell && $section === 'user' && $currentUser): ?>
            <?php render_user_sidebar($activeKey, $currentUser, $currentRole); ?>
            <div class="lg:ml-64 min-h-screen flex flex-col">
                <header class="sticky top-0 z-20 bg-white/95 backdrop-blur border-b border-gem-border shadow-nav">
                    <div class="flex items-center gap-3 px-4 lg:px-6 h-16">
                        <button type="button" data-sidebar-open class="lg:hidden flex-shrink-0 w-9 h-9 rounded-xl bg-gem-gray flex items-center justify-center border border-gem-border" aria-label="Open navigation">
                            <?= icon_svg('menu'); ?>
                        </button>
                        <a href="<?= e(base_url('user/dashboard.php')); ?>" class="flex items-center gap-2 lg:hidden">
                            <span class="w-7 h-7 flex items-center justify-center"><?= gemdata_logo('icon', '22', 'rounded', 'GemData'); ?></span>
                            <span class="font-extrabold text-[14px] text-gem-text">GemData</span>
                        </a>
                        <div class="flex-1 hidden sm:block max-w-md">
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gem-muted"><?= icon_svg('search'); ?></span>
                                <input id="dashboard-search" type="text" placeholder="Search services, transactions, and more..." class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-gem-gray border border-gem-border text-[13px] font-normal text-gem-text placeholder:text-gem-muted focus:outline-none focus:border-gem-blue focus:ring-2 focus:ring-gem-blue/10 transition-all" data-dashboard-search>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 ml-auto">
                            <a href="<?= e(base_url('user/fund-wallet.php')); ?>" class="hidden sm:inline-flex items-center gap-2 bg-gem-blue hover:bg-gem-blueDk text-white text-[13px] font-bold px-4 py-2.5 rounded-xl shadow-panel transition-colors">
                                <span class="w-4 h-4"><?= icon_svg('plus'); ?></span>
                                Fund Wallet
                            </a>
                            <a class="relative w-10 h-10 rounded-xl bg-gem-gray border border-gem-border flex items-center justify-center hover:bg-white transition-colors" href="<?= e(base_url('user/notifications.php')); ?>" aria-label="Notifications">
                                <span class="w-5 h-5 text-gem-muted"><?= icon_svg('notification'); ?></span>
                                <?php if ($notificationsCount > 0): ?>
                                    <span class="absolute -top-1 -right-1 badge bg-gem-red text-white"><?= (int) $notificationsCount; ?></span>
                                <?php endif; ?>
                            </a>
                            <div class="profile-menu" data-profile-menu>
                                <button class="profile-trigger !p-0 !border-0 !bg-transparent" type="button" data-profile-toggle aria-label="Open profile menu">
                                    <span class="w-10 h-10 rounded-full bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center text-white font-bold text-sm"><?= e(strtoupper(substr($identityName, 0, 1))); ?></span>
                                </button>
                                <div class="profile-dropdown data-profile-compact" data-profile-dropdown>
                                    <div class="profile-theme-strip">
                                        <?php foreach ($themeOptions as $themeKey => $themeMeta): ?>
                                            <button class="profile-theme-chip" type="button" data-theme-option="<?= e($themeKey); ?>" title="<?= e($themeMeta['name']); ?>"><?= e(substr($themeMeta['name'], 0, 1)); ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <a href="<?= e(base_url('user/settings.php')); ?>">Profile</a>
                                    <a href="<?= e(base_url('user/fund-wallet.php')); ?>">Fund Wallet</a>
                                    <?php if ($currentRole === 'api'): ?>
                                        <a href="<?= e(base_url('user/api-dashboard.php')); ?>">API Center</a>
                                    <?php elseif ($currentRole === 'reseller'): ?>
                                        <a href="<?= e(base_url('user/customers.php')); ?>">Customers</a>
                                    <?php else: ?>
                                        <a href="<?= e(base_url('user/upgrade-request.php')); ?>">Upgrade</a>
                                    <?php endif; ?>
                                    <form method="post" action="<?= e(base_url('user/logout.php')); ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><button type="submit">Logout</button></form>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>
                <main class="flex-1 p-4 lg:p-6 pb-24 lg:pb-6" id="app-main-content">
                    <div class="connection-banner" id="connection-banner" hidden></div>
        <?php elseif ($hasShell): ?>
            <div class="app-shell">
                <div class="sidebar-backdrop" id="sidebar-backdrop"></div>
                <?php render_sidebar(nav_items($section), $activeKey, $section); ?>
                <div class="app-shell-main">
                    <header class="topbar">
                        <div class="topbar-left">
                            <button class="icon-button mobile-only" type="button" id="sidebar-toggle" aria-label="Toggle navigation">
                                <?= icon_svg('menu'); ?>
                            </button>
                            <div>
                                <p class="eyebrow"><?= $section === 'admin' ? 'Operations Center' : e($currentRoleLabel ?: 'GemData Workspace'); ?></p>
                                <h1 class="topbar-title"><?= e($title); ?></h1>
                            </div>
                        </div>
                        <div class="topbar-search">
                            <?php if ($section === 'admin'): ?>
                                <form method="get" class="topbar-search-form">
                                    <?php foreach ($preservedQuery as $key => $value): ?>
                                        <input type="hidden" name="<?= e((string) $key); ?>" value="<?= e((string) $value); ?>">
                                    <?php endforeach; ?>
                                    <span class="search-icon"><?= icon_svg('search'); ?></span>
                                    <input id="dashboard-search" name="q" type="search" value="<?= e($searchValue); ?>" placeholder="Search records on this page">
                                </form>
                            <?php else: ?>
                                <span class="search-icon"><?= icon_svg('search'); ?></span>
                                <input id="dashboard-search" type="search" placeholder="Search services, widgets, and records" data-dashboard-search>
                            <?php endif; ?>
                        </div>
                        <div class="topbar-right">
                            <?php if ($currentUser): ?>
                                <div class="wallet-chip">
                                    <span class="wallet-label">Wallet</span>
                                    <strong><?= e(money($walletBalance)); ?></strong>
                                </div>
                                <button class="secondary-action pwa-install-button" type="button" data-install-trigger hidden>Install App</button>
                                <a class="icon-button notification-button" href="<?= e(base_url('user/notifications.php')); ?>" aria-label="Notifications">
                                    <?= icon_svg('notification'); ?>
                                    <?php if ($notificationsCount > 0): ?>
                                        <span class="notification-count"><?= (int) $notificationsCount; ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php else: ?>
                                <button class="secondary-action pwa-install-button" type="button" data-install-trigger hidden>Install App</button>
                                <div class="wallet-chip admin-chip">
                                    <span class="wallet-label">Role</span>
                                    <strong><?= e($adminRole); ?></strong>
                                </div>
                            <?php endif; ?>
                            <div class="profile-menu" data-profile-menu>
                                <button class="profile-trigger" type="button" data-profile-toggle>
                                    <span class="profile-avatar"><?= e(strtoupper(substr($identityName, 0, 1))); ?></span>
                                    <span class="profile-copy">
                                        <strong><?= e($identityName); ?></strong>
                                        <small><?= e($identityMeta); ?></small>
                                    </span>
                                    <span class="profile-chevron"><?= icon_svg('chevron'); ?></span>
                                </button>
                                <div class="profile-dropdown" data-profile-dropdown>
                                    <div class="profile-theme-strip">
                                        <?php foreach ($themeOptions as $themeKey => $themeMeta): ?>
                                            <button class="profile-theme-chip" type="button" data-theme-option="<?= e($themeKey); ?>" title="<?= e($themeMeta['name']); ?>"><?= e(substr($themeMeta['name'], 0, 1)); ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if ($section === 'user'): ?>
                                        <a href="<?= e(base_url('user/settings.php')); ?>">Profile</a>
                                        <a href="<?= e(base_url('user/fund-wallet.php')); ?>">Fund Wallet</a>
                                        <?php if ($currentRole === 'api'): ?>
                                            <a href="<?= e(base_url('user/api-dashboard.php')); ?>">API Center</a>
                                            <a href="<?= e(base_url('user/api-keys.php')); ?>">API Keys</a>
                                        <?php else: ?>
                                            <a href="<?= e(base_url('user/upgrade-request.php')); ?>">Upgrade</a>
                                        <?php endif; ?>
                                        <a href="<?= e(base_url('user/settings.php')); ?>">Settings</a>
                                        <form method="post" action="<?= e(base_url('user/logout.php')); ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><button type="submit">Logout</button></form>
                                    <?php else: ?>
                                        <a href="<?= e(base_url('admin/dashboard.php')); ?>">Overview</a>
                                        <?php if (admin_can('users.view')): ?><a href="<?= e(base_url('admin/users.php')); ?>">Users</a><?php endif; ?>
                                        <?php if (admin_can('roles.manage')): ?><a href="<?= e(base_url('admin/invites.php')); ?>">Invites</a><?php endif; ?>
                                        <?php if (admin_can('settings.manage')): ?><a href="<?= e(base_url('admin/settings.php')); ?>">Settings</a><?php endif; ?>
                                        <form method="post" action="<?= e(base_url('admin/logout.php')); ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><button type="submit">Logout</button></form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </header>
                    <main class="app-content" id="app-main-content">
                        <?php if ($section === 'user'): ?>
                            <div class="connection-banner" id="connection-banner" hidden></div>
                        <?php endif; ?>
        <?php else: ?>
            <div class="guest-shell">
                <header class="guest-topbar">
                    <div class="guest-brand">
                        <a href="<?= e(base_url()); ?>" class="sidebar-logo-link" aria-label="GemData Home">
                            <?= gemdata_logo('icon', 'auto', 'sidebar-logo-icon', 'GemData'); ?>
                        </a>
                        <div class="sidebar-brand-text">
                            <p class="brand-title">GemData</p>
                            <p class="brand-subtitle">Trust In Data</p>
                        </div>
                    </div>
                    <nav class="guest-nav">
                        <a href="<?= e(base_url('user/login.php')); ?>">User Login</a>
                        <a href="<?= e(base_url('user/register.php')); ?>">Register</a>
                        <a href="<?= e(base_url('admin/login.php')); ?>">Admin Login</a>
                        <a href="<?= e(base_url('docs/api.php')); ?>">API Docs</a>
                        <button class="secondary-action pwa-install-button" type="button" data-install-trigger hidden>Install App</button>
                    </nav>
                    <button class="icon-button mobile-only" type="button" data-guest-nav-toggle aria-label="Toggle public navigation">
                        <?= icon_svg('menu'); ?>
                    </button>
                </header>
                <div class="guest-mobile-nav" data-guest-nav-panel hidden>
                    <a href="<?= e(base_url()); ?>#home">Home</a>
                    <a href="<?= e(base_url()); ?>#services">Services</a>
                    <a href="<?= e(base_url('docs/api.php')); ?>">API Docs</a>
                    <a href="<?= e(base_url('user/login.php')); ?>">User Login</a>
                    <a href="<?= e(base_url('user/register.php')); ?>">Register</a>
                </div>
                <main class="guest-content" id="app-main-content">
        <?php endif; ?>
    <?php
}

function render_mobile_bottom_nav(string $activeKey): void
{
    $user = user();
    if (!$user) {
        return;
    }

    $role = app(\GemData\Classes\UserRoleManager::class)->roleFor($user);
    $items = $role === 'reseller'
        ? [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => base_url('user/dashboard.php'), 'icon' => 'dashboard'],
            ['key' => 'services', 'label' => 'Services', 'href' => base_url('user/dashboard.php#services'), 'icon' => 'services-mobile', 'data_key' => 'services'],
            ['key' => 'customers', 'label' => 'Customers', 'href' => base_url('user/customers.php'), 'icon' => 'users'],
            ['key' => 'wallet', 'label' => 'Wallet', 'href' => base_url('user/fund-wallet.php'), 'icon' => 'wallet', 'featured' => true],
            ['key' => 'reports', 'label' => 'Reports', 'href' => base_url('user/commission.php'), 'icon' => 'chart'],
        ]
        : [
            ['key' => 'dashboard', 'label' => 'Home', 'href' => base_url('user/dashboard.php'), 'icon' => 'dashboard'],
            ['key' => 'services', 'label' => 'Services', 'href' => base_url('user/dashboard.php#services'), 'icon' => 'services-mobile', 'data_key' => 'services'],
            ['key' => 'wallet', 'label' => 'Wallet', 'href' => base_url('user/fund-wallet.php'), 'icon' => 'wallet', 'featured' => true],
            ['key' => 'transactions', 'label' => 'Activity', 'href' => base_url('user/transactions.php'), 'icon' => 'transactions'],
            $role === 'api'
                ? ['key' => 'api-center', 'label' => 'API', 'href' => base_url('user/api-dashboard.php'), 'icon' => 'code']
                : ['key' => 'settings', 'label' => 'Account', 'href' => base_url('user/settings.php'), 'icon' => 'profile'],
        ];
    ?>
    <nav class="user-bottom-nav fixed bottom-0 left-0 right-0 bg-white border-t border-gem-border z-20 lg:hidden" aria-label="Primary mobile navigation">
        <div class="grid grid-cols-5 h-16">
        <?php foreach ($items as $item): ?>
            <?php $itemKey = $item['data_key'] ?? $item['key']; ?>
            <a class="<?= !empty($item['featured']) ? 'flex flex-col items-center justify-center -mt-5' : 'bottom-nav-item flex flex-col items-center justify-center gap-1'; ?> <?= $activeKey === $itemKey ? 'active text-gem-blue' : 'text-gem-muted'; ?>" href="<?= e($item['href']); ?>" data-nav-key="<?= e($itemKey); ?>">
                <?php if (!empty($item['featured'])): ?>
                    <span class="w-14 h-14 rounded-2xl bg-gem-blue shadow-panel flex items-center justify-center text-white"><?= icon_svg($item['icon']); ?></span>
                    <span class="text-[10px] font-semibold text-gem-blue mt-0.5"><?= e($item['label']); ?></span>
                <?php else: ?>
                    <span class="w-5 h-5"><?= icon_svg($item['icon']); ?></span>
                    <span class="text-[10px] <?= $activeKey === $itemKey ? 'font-semibold' : 'font-medium'; ?>"><?= e($item['label']); ?></span>
                    <?php if ($activeKey === $itemKey): ?><span class="dot"></span><?php endif; ?>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
        </div>
    </nav>
    <?php
}

function render_footer(): void
{
    ?>
                    </main>
                <?php if (!empty($GLOBALS['__gemdata_has_shell']) && ($GLOBALS['__gemdata_shell_section'] ?? '') === 'user'): ?>
                    </div>
                    <?php render_mobile_bottom_nav((string) ($GLOBALS['__gemdata_active_key'] ?? 'dashboard')); ?>
                <?php elseif (!empty($GLOBALS['__gemdata_has_shell'])): ?>
                </div>
            </div>
                <?php else: ?>
            </div>
                <?php endif; ?>
    </body>
    </html>
    <?php
}
