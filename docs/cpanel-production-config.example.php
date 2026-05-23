<?php

declare(strict_types=1);

return [
    'app' => [
        'environment' => 'production',
        'base_url' => '',
        'public_origin' => 'https://gemdata.com.ng',
        'force_https_in_production' => true,
        'trusted_proxies' => [],
        'bootstrap_log_to_file' => true,
        'bootstrap_log_file' => '/home/<cpanel_user>/public_html/storage/logs/bootstrap.log',
        'provider_log_to_file' => true,
        'provider_log_file' => '/home/<cpanel_user>/public_html/storage/logs/provider.log',
        'required_extensions' => ['json', 'pdo_mysql', 'curl'],
    ],
    'db' => [
        'host' => 'localhost',
        'port' => '3306',
        'dbname' => 'yyrigitd_gemdata',
        'username' => 'yyrigitd_admin',
        'password' => 'replace-with-your-cpanel-db-password',
        'charset' => 'utf8mb4',
    ],
    'providers' => [
        'albani' => [
            'label' => 'AlbaniAPI',
            'driver' => 'albani',
            'base_url' => 'https://albanidata.com/api/v1',
            'api_key' => 'replace-with-your-albani-api-key',
            'enabled' => true,
            'sandbox' => false,
            'timeout_seconds' => 20,
            'retry_count' => 1,
            'balance_path' => '/wallet/balance',
            'balance_fallback_paths' => ['/wallet/balance'],
        ],
        'smeplug' => [
            'label' => 'SMEPlug',
            'driver' => 'smeplug',
            'base_url' => 'https://example.test',
            'api_key' => '',
            'api_secret' => '',
            'enabled' => false,
            'sandbox' => true,
        ],
        'vtpass' => [
            'label' => 'VTpass',
            'driver' => 'vtpass',
            'base_url' => 'https://example.test',
            'api_key' => '',
            'api_secret' => '',
            'enabled' => false,
            'sandbox' => true,
        ],
    ],
    'webhooks' => [
        'shared_secret' => 'replace-with-a-long-random-string',
        'allowed_sources' => ['xixapay'],
    ],
    'payments' => [
        'default_gateway' => 'xixapay',
        'display_gateway_name' => 'XixaPay',
        'auto_verify_mock_funding' => false,
        'xixapay_api_key' => 'replace-with-xixapay-api-key',
        'xixapay_api_secret' => 'replace-with-xixapay-api-secret',
        'xixapay_business_id' => 'replace-with-xixapay-business-id',
        'xixapay_base_url' => 'https://api.xixapay.com',
        'xixapay_bank_codes' => ['20867'],
        'xixapay_webhook_url' => 'https://gemdata.com.ng/api/xixapay.php',
    ],
    'mail' => [
        'driver' => 'smtp',
        'from_email' => 'no-reply@gemdata.com.ng',
        'from_name' => 'GemData',
        'debug_display_reset_links' => false,
    ],
    'mobile' => [
        'webview_origin' => 'https://gemdata.com.ng',
    ],
];
