<?php

declare(strict_types=1);

$config = [
    'app' => [
        'name' => 'GemData',
        'base_url' => '/gemdata',
        'public_origin' => 'http://localhost',
        'currency' => 'NGN',
        'rate_limit_per_minute' => 60,
        'timezone' => 'Africa/Lagos',
        'environment' => 'local',
        'force_https_in_production' => true,
        'session_timeout_seconds' => 1800,
        'admin_login_attempt_limit' => 5,
        'admin_login_attempt_window_minutes' => 15,
        'user_login_attempt_limit' => 5,
        'user_login_attempt_window_minutes' => 15,
        'trusted_proxies' => [],
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'dbname' => 'gemdata_api',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'providers' => [
        'mock_main' => [
            'label' => 'Mock VTU Provider',
            'driver' => 'mock',
            'base_url' => 'local',
            'api_key' => '',
            'api_secret' => '',
        ],
    ],
    'webhooks' => [
        'shared_secret' => '',
        'allowed_sources' => ['generic'],
    ],
    'payments' => [
        'default_gateway' => 'mock_paystack',
        'display_gateway_name' => 'Paystack',
        'auto_verify_mock_funding' => false,
        'auto_assign_dedicated_account' => false,
        'paystack_secret_key' => '',
        'paystack_base_url' => 'https://api.paystack.co',
        'dva_preferred_bank' => '',
    ],
    'mail' => [
        'driver' => 'log',
        'from_email' => 'hello@gemdata.local',
        'from_name' => 'GemData',
        'debug_display_reset_links' => true,
    ],
    'pwa' => [
        'short_name' => 'GemData',
        'theme_color' => '#0f172a',
        'background_color' => '#f8fbff',
        'display' => 'standalone',
        'orientation' => 'portrait',
        'offline_page' => '/gemdata/offline.html',
    ],
    'mobile' => [
        'capacitor_app_id' => 'com.gemdata.app',
        'capacitor_app_name' => 'GemData',
        'webview_origin' => 'https://example.com/gemdata',
    ],
    'notifications' => [
        'web_push_enabled' => false,
    ],
];

$localOverride = __DIR__ . '/config.local.php';
if (is_file($localOverride)) {
    $localConfig = require $localOverride;
    if (is_array($localConfig)) {
        $config = array_replace_recursive($config, $localConfig);
    }
}

return $config;
