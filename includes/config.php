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
        'maintenance_bypass_paths' => ['/admin/', '/api/xixapay.php', '/api/paystack.php', '/api/katpay.php'],
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
        'alrahuzdata' => [
            'label' => 'AlrahuzData',
            'driver' => 'alrahuzdata',
            'base_url' => 'https://alrahuzdata.com.ng/api',
            'token' => '',
            'enabled' => false,
            'sandbox' => true,
            'timeout_seconds' => 20,
            'retry_count' => 1,
            'network_map' => ['mtn' => '', 'airtel' => '', 'glo' => '', '9mobile' => ''],
            'disco_map' => [],
            'meter_type_map' => ['prepaid' => '1', 'postpaid' => '2'],
            'cable_map' => [],
            'balance_path' => '/user/',
            'airtime_path' => '/topup/',
            'data_path' => '/data/',
            'electricity_path' => '/billpayment/',
            'cable_path' => '/cablesub/',
            'exam_pin_path' => '/epin/',
            'data_status_path' => '/data/{id}',
            'electricity_status_path' => '/billpayment/{id}',
            'cable_status_path' => '/cablesub/{id}',
        ],
        'abbpantami' => [
            'label' => 'AbbPantami',
            'driver' => 'abbpantami',
            'base_url' => 'https://abbapantamiapi.com/api',
            'token' => '',
            'enabled' => false,
            'sandbox' => true,
            'timeout_seconds' => 20,
            'retry_count' => 1,
            'network_map' => ['mtn' => '', 'airtel' => '', 'glo' => '', '9mobile' => ''],
            'disco_map' => [],
            'meter_type_map' => ['prepaid' => '1', 'postpaid' => '2'],
            'cable_map' => [],
            'balance_path' => '/user/',
            'airtime_path' => '/topup/',
            'data_path' => '/data/',
            'electricity_path' => '/billpayment/',
            'cable_path' => '/cablesub/',
            'data_status_path' => '/data/{id}',
            'electricity_status_path' => '/billpayment/{id}',
            'cable_status_path' => '/cablesub/{id}',
        ],
        'cheapdatahub' => [
            'label' => 'CheapDataHub',
            'driver' => 'cheapdatahub',
            'base_url' => 'https://www.cheapdatahub.ng/api/v1/resellers',
            'api_key' => '',
            'enabled' => false,
            'sandbox' => true,
            'timeout_seconds' => 20,
            'retry_count' => 1,
            'network_map' => ['mtn' => '', 'airtel' => '', 'glo' => '', '9mobile' => ''],
            'disco_map' => [],
            'meter_type_map' => ['prepaid' => 'prepaid', 'postpaid' => 'postpaid'],
            'balance_path' => '/wallet/balance/',
            'airtime_path' => '/airtime/purchase/',
            'data_path' => '/data/purchase/',
            'electricity_path' => '/electricity/purchase/',
            'cable_path' => '/cable/purchase/',
            'exam_pin_path' => '/exam-pin/purchase/',
            'exam_pin_products_path' => '/exam-pin/products/',
            'status_path' => '/transactions/{id}/',
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
            'airtime_path' => '/airtime/purchase',
            'data_path' => '/data/purchase',
            'cable_path' => '',
            'electricity_path' => '',
            'exam_pin_path' => '',
            'data_card_path' => '',
            'recharge_card_path' => '',
            'bulk_sms_path' => '',
            'status_path' => '/transaction/status/{reference}',
        ],
    ],
    'webhooks' => [
        'shared_secret' => '',
        'allowed_sources' => ['xixapay', 'paystack', 'katpay'],
    ],
    'security' => [
        'admin_2fa_enabled' => false,
        'email_verification_required_for_money_movement' => true,
    ],
    'feature_flags' => [
        'show_inactive_provider_plans_for_testing' => false,
    ],
    'payments' => [
        'default_gateway' => 'paystack',
        'display_gateway_name' => 'Paystack',
        'active_funding_provider' => 'katpay',
        'multi_provider_funding' => false,
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
        'katpay_enabled' => false,
        'katpay_api_key' => '',
        'katpay_secret_key' => '',
        'katpay_base_url' => 'https://api.katpay.co/v1',
        'katpay_bank_list_base_url' => 'https://api.katpay.co',
        'katpay_merchant_id' => '',
        'katpay_bank_codes' => ['PALMPAY', 'OPAY'],
    ],
    'mail' => [
        'driver' => 'log',
        'from_email' => 'hello@gemdata.local',
        'from_name' => 'GemData',
        'debug_display_reset_links' => true,
    ],
    'pwa' => [
        'short_name' => 'GemData',
        'theme_color' => '#1B4DFF',
        'background_color' => '#f4f8fc',
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
