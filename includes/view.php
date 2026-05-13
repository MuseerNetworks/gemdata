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
            str_contains($path, 'admin/providers.php') => 'providers',
            str_contains($path, 'admin/reports.php') => 'reports',
            str_contains($path, 'admin/roles.php'),
            str_contains($path, 'admin/invites.php'),
            str_contains($path, 'admin/accept-invite.php') => 'roles',
            str_contains($path, 'admin/alerts.php') => 'alerts',
            str_contains($path, 'admin/notifications.php') => 'notifications',
            str_contains($path, 'admin/settings.php') => 'settings',
            default => 'overview',
        };
    }

    return match (true) {
        str_contains($path, 'user/transactions.php') => 'transactions',
        str_contains($path, 'user/fund-wallet.php') => 'wallet',
        str_contains($path, 'user/api-keys.php') => 'profile',
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
            ['key' => 'providers', 'label' => 'Providers', 'href' => base_url('admin/providers.php'), 'icon' => 'server'],
            ['key' => 'reports', 'label' => 'Reports', 'href' => base_url('admin/reports.php'), 'icon' => 'chart'],
            ['key' => 'roles', 'label' => 'Roles & Invites', 'href' => base_url('admin/roles.php'), 'icon' => 'shield'],
            ['key' => 'alerts', 'label' => 'Alerts', 'href' => base_url('admin/alerts.php'), 'icon' => 'notification'],
            ['key' => 'notifications', 'label' => 'Notifications', 'href' => base_url('admin/notifications.php'), 'icon' => 'notification'],
            ['key' => 'settings', 'label' => 'Settings', 'href' => base_url('admin/settings.php'), 'icon' => 'settings'],
            ['key' => 'logout', 'label' => 'Logout', 'href' => base_url('admin/logout.php'), 'icon' => 'logout'],
        ];

        return array_values(array_filter($items, static function (array $item): bool {
            return match ($item['key']) {
                'users' => admin_can('users.view'),
                'transactions' => admin_can('transactions.view'),
                'services', 'service-control' => admin_can('services.manage'),
                'wallet' => admin_can('wallet.manage'),
                'providers' => admin_can('providers.manage'),
                'reports' => admin_can('reports.view'),
                'roles' => admin_can('roles.manage'),
                'alerts', 'notifications' => admin_can('alerts.manage'),
                'settings' => admin_can('settings.manage'),
                default => true,
            };
        }));
    }

    return [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => base_url('user/dashboard.php'), 'icon' => 'dashboard'],
        ['key' => 'services', 'label' => 'Services', 'href' => base_url('user/dashboard.php#services'), 'icon' => 'services', 'data_key' => 'services'],
        ['key' => 'transactions', 'label' => 'Transactions', 'href' => base_url('user/transactions.php'), 'icon' => 'transactions'],
        ['key' => 'wallet', 'label' => 'Wallet', 'href' => base_url('user/fund-wallet.php'), 'icon' => 'wallet'],
        ['key' => 'profile', 'label' => 'Profile', 'href' => base_url('user/api-keys.php'), 'icon' => 'profile'],
        ['key' => 'settings', 'label' => 'Settings', 'href' => base_url('user/settings.php'), 'icon' => 'settings'],
        ['key' => 'logout', 'label' => 'Logout', 'href' => base_url('user/logout.php'), 'icon' => 'logout'],
    ];
}

function icon_svg(string $name): string
{
    return match ($name) {
        'menu' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
        'search' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',
        'dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 13.5 12 4l9 9.5"/><path d="M5 10.5V20h14v-9.5"/><path d="M10 20v-6h4v6"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="3.5"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a3.5 3.5 0 0 1 0 6.74"/></svg>',
        'services' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="7" height="7" rx="1.5"/><rect x="14" y="4" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
        'transactions' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 7h11a3 3 0 1 1 0 6H6"/><path d="m9 4-3 3 3 3"/><path d="M17 17H6a3 3 0 1 1 0-6h12"/><path d="m15 20 3-3-3-3"/></svg>',
        'wallet' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h11A2.5 2.5 0 0 1 19 7.5V9h2v10H5a2 2 0 0 1-2-2z"/><path d="M19 9H7a2 2 0 0 0 0 4h12"/><circle cx="16.5" cy="11" r=".8" fill="currentColor" stroke="none"/></svg>',
        'chart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3v18h18"/><path d="m7 14 3-3 3 2 4-5"/></svg>',
        'server' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l7 3v6c0 5-3.5 8-7 9-3.5-1-7-4-7-9V6l7-3z"/></svg>',
        'profile' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8.5A3.5 3.5 0 1 0 12 15.5A3.5 3.5 0 1 0 12 8.5z"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1.03 1.55V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1.03-1.55 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.55-1.03H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.64 8.4a1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34H9a1.7 1.7 0 0 0 1.03-1.55V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1.03 1.55 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9v.03a1.7 1.7 0 0 0 1.55 1.03H21a2 2 0 1 1 0 4h-.09A1.7 1.7 0 0 0 19.4 15z"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>',
        'notification' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 9a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10 20a2 2 0 0 0 4 0"/></svg>',
        'close' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18"/></svg>',
        'chevron' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m9 6 6 6-6 6"/></svg>',
        default => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/></svg>',
    };
}

function render_sidebar(array $items, string $activeKey, string $section): void
{
    ?>
    <aside class="app-sidebar" id="app-sidebar" data-section="<?= e($section); ?>">
        <div class="sidebar-brand">
            <a href="<?= e(base_url()); ?>" class="sidebar-logo-link" aria-label="GemData Home">
                <?= gemdata_logo('icon', 'auto', 'sidebar-logo-icon', 'GemData'); ?>
            </a>
            <div class="sidebar-brand-text">
                <p class="brand-title">GemData</p>
                <p class="brand-subtitle"><?= $section === 'admin' ? 'Admin Console' : 'Workspace'; ?></p>
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

function render_header(string $title, string $section = 'user'): void
{
    $currentUser = user();
    $currentAdmin = admin_user();
    $currentPath = current_relative_path();
    $notificationsCount = $currentUser ? app(\GemData\Classes\NotificationService::class)->unreadCount((int) $currentUser['id']) : 0;
    $hasShell = ($section === 'user' && $currentUser) || ($section === 'admin' && $currentAdmin);
    $activeKey = current_page_key($section, $currentPath);
    $walletBalance = $currentUser ? app(\GemData\Classes\Wallet::class)->balance((int) $currentUser['id']) : null;
    $identityName = $currentUser['full_name'] ?? $currentAdmin['full_name'] ?? 'Guest';
    $identityMeta = $currentUser['email'] ?? $currentAdmin['email'] ?? config('app.name');
    $adminRole = $currentAdmin['role_name'] ?? 'Administrator';
    $searchValue = trim((string) ($_GET['q'] ?? ''));
    $preservedQuery = query_except(['q', 'page', 'export']);
    $pwaTheme = (string) config('pwa.theme_color', '#0f172a');
    $pwaBackground = (string) config('pwa.background_color', '#f8fbff');
    $themeOptions = [
        'light-fintech' => ['name' => 'Light Fintech', 'copy' => 'Bright operational surfaces'],
        'warm-cream' => ['name' => 'Warm Cream', 'copy' => 'Softer premium neutrals'],
        'cool-glass' => ['name' => 'Cool Glass', 'copy' => 'Airy translucent workspace'],
        'dark' => ['name' => 'Dark Mode', 'copy' => 'Low-light focused view'],
    ];
    $GLOBALS['__gemdata_has_shell'] = $hasShell;
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
        <link rel="icon" type="image/png" sizes="32x32" href="<?= e(base_url('assets/brand/favicon-32x32.png')); ?>?v=20260513c">
        <link rel="icon" type="image/png" sizes="16x16" href="<?= e(base_url('assets/brand/favicon-16x16.png')); ?>?v=20260513c">
        <link rel="icon" type="image/png" sizes="48x48" href="<?= e(base_url('assets/brand/favicon-48x48.png')); ?>?v=20260513c">
        <link rel="shortcut icon" type="image/png" href="<?= e(base_url('assets/brand/favicon-32x32.png')); ?>?v=20260513c">
        <!-- Apple / iOS -->
        <link rel="apple-touch-icon" sizes="180x180" href="<?= e(base_url('assets/brand/apple-touch-icon.png')); ?>?v=20260513c">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="GemData">
        <!-- Android / Chrome -->
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="application-name" content="GemData">
        <meta name="theme-color" content="#1d4ed8">
        <!-- Windows -->
        <meta name="msapplication-TileColor" content="#1d4ed8">
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
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900&display=swap" rel="stylesheet">
        <script>
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
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="<?= e(base_url('assets/css/site.css')); ?>">
        <script defer src="<?= e(base_url('assets/js/app.js')); ?>"></script>
    </head>
    <body class="app-body<?= $hasShell ? ' app-shell-body' : ' app-guest-body'; ?>" data-app-section="<?= e($section); ?>" data-page-key="<?= e($activeKey); ?>">
        <a class="skip-link" href="#app-main-content">Skip to main content</a>
        <?php if ($hasShell): ?>
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
                                <p class="eyebrow"><?= $section === 'admin' ? 'Operations Center' : 'GemData Workspace'; ?></p>
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
                                    <?php if ($section === 'user'): ?>
                                        <div class="profile-theme-strip">
                                            <?php foreach ($themeOptions as $themeKey => $themeMeta): ?>
                                                <button class="profile-theme-chip" type="button" data-theme-option="<?= e($themeKey); ?>"><?= e(substr($themeMeta['name'], 0, 1)); ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($section === 'user'): ?>
                                        <a href="<?= e(base_url('user/api-keys.php')); ?>">Profile</a>
                                        <a href="<?= e(base_url('user/settings.php')); ?>">Settings</a>
                                        <a href="<?= e(base_url('user/logout.php')); ?>">Logout</a>
                                    <?php else: ?>
                                        <a href="<?= e(base_url('admin/dashboard.php')); ?>">Overview</a>
                                        <?php if (admin_can('users.view')): ?><a href="<?= e(base_url('admin/users.php')); ?>">Users</a><?php endif; ?>
                                        <?php if (admin_can('roles.manage')): ?><a href="<?= e(base_url('admin/invites.php')); ?>">Invites</a><?php endif; ?>
                                        <?php if (admin_can('settings.manage')): ?><a href="<?= e(base_url('admin/settings.php')); ?>">Settings</a><?php endif; ?>
                                        <a href="<?= e(base_url('admin/logout.php')); ?>">Logout</a>
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

function render_footer(): void
{
    ?>
                    </main>
                <?php if (!empty($GLOBALS['__gemdata_has_shell'])): ?>
                </div>
            </div>
                <?php else: ?>
            </div>
                <?php endif; ?>
    </body>
    </html>
    <?php
}
