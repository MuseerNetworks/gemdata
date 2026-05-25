<?php

declare(strict_types=1);

$config = [
    'app' => [
        'name' => 'GemData',
        'base_url' => '',
        'public_origin' => '',
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
        'cache_dir' => dirname(__DIR__) . '/storage/cache',
        'bootstrap_log_to_file' => true,
        'bootstrap_log_file' => dirname(__DIR__) . '/storage/logs/bootstrap.log',
        'provider_log_to_file' => true,
        'provider_log_file' => dirname(__DIR__) . '/storage/logs/provider.log',
        'required_extensions' => ['json', 'pdo_mysql'],
        'maintenance_bypass_paths' => ['/admin/', '/api/xixapay.php', '/api/paystack.php'],
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
            'enabled' => false,
            'sandbox' => true,
        ],
        'smeplug' => [
            'label' => 'SMEPlug',
            'driver' => 'smeplug',
            'base_url' => '',
            'api_key' => '',
            'api_secret' => '',
            'enabled' => false,
            'sandbox' => true,
        ],
        'vtpass' => [
            'label' => 'VTpass',
            'driver' => 'vtpass',
            'base_url' => '',
            'api_key' => '',
            'api_secret' => '',
            'enabled' => false,
            'sandbox' => true,
        ],
        'clubkonnect' => [
            'label' => 'ClubKonnect',
            'driver' => 'clubkonnect',
            'base_url' => '',
            'api_key' => '',
            'api_secret' => '',
            'enabled' => false,
            'sandbox' => true,
        ],
        'alrahuzdata' => [
            'label' => 'AlrahuzData',
            'driver' => 'alrahuzdata',
            'base_url' => '',
            'api_key' => '',
            'api_secret' => '',
            'enabled' => false,
            'sandbox' => true,
        ],
        'easyaccessapi' => [
            'label' => 'EasyAccessAPI',
            'driver' => 'easyaccessapi',
            'base_url' => '',
            'api_key' => '',
            'api_secret' => '',
            'enabled' => false,
            'sandbox' => true,
        ],
        'albani' => [
            'label' => 'AlbaniAPI',
            'driver' => 'albani',
            'base_url' => 'https://albanidata.com/api/v1',
            'api_key' => '',
            'enabled' => false,
            'sandbox' => false,
            'timeout_seconds' => 20,
            'retry_count' => 1,
            'balance_path' => '/wallet/balance',
            'balance_fallback_paths' => ['/wallet/balance'],
        ],
    ],
    'webhooks' => [
        'shared_secret' => '',
        'allowed_sources' => ['xixapay', 'paystack'],
    ],
    'security' => [
        'admin_2fa_enabled' => false,
        'email_verification_required_for_money_movement' => true,
    ],
    'payments' => [
        'default_gateway' => 'paystack',
        'display_gateway_name' => 'Paystack',
        'auto_verify_mock_funding' => false,
        'xixapay_api_key' => '',
        'xixapay_api_secret' => '',
        'xixapay_business_id' => '',
        'xixapay_base_url' => 'https://api.xixapay.com',
        'xixapay_bank_codes' => ['20867'],
        'xixapay_webhook_url' => '/api/xixapay.php',
        'paystack_secret_key' => '',
        'paystack_preferred_bank' => 'titan-paystack',
        'paystack_base_url' => 'https://api.paystack.co',
        'paystack_webhook_url' => '/api/paystack.php',
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
        'offline_page' => '/offline.html',
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

$privateOverride = dirname(__DIR__, 2) . '/gemdata-config.php';
$config['__meta'] = [
    'private_override_path' => $privateOverride,
    'private_override_loaded' => false,
    'local_override_loaded' => false,
];

if (is_file($privateOverride)) {
    $privateConfig = require $privateOverride;
    if (is_array($privateConfig)) {
        $config = array_replace_recursive($config, $privateConfig);
        $config['__meta']['private_override_loaded'] = true;
    }
}

$localOverride = __DIR__ . '/config.local.php';
if (($config['app']['environment'] ?? 'local') !== 'production' && is_file($localOverride)) {
    $localConfig = require $localOverride;
    if (is_array($localConfig)) {
        $config = array_replace_recursive($config, $localConfig);
        $config['__meta']['local_override_loaded'] = true;
    }
}

return $config;
