<?php

declare(strict_types=1);

return [
    'app' => [
        'environment' => 'production',
        'base_url' => '',
        'public_origin' => 'https://gemdata.com.ng',
        'force_https_in_production' => true,
        'trusted_proxies' => [],
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
        'mock_main' => [
            'label' => 'Mock VTU Provider',
            'driver' => 'mock',
            'base_url' => 'local',
            'api_key' => '',
            'api_secret' => '',
            'enabled' => true,
            'sandbox' => true,
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
    ],
    'webhooks' => [
        'shared_secret' => 'replace-with-a-long-random-string',
        'allowed_sources' => ['xixapay'],
    ],
    'payments' => [
        'default_gateway' => 'xixapay',
        'display_gateway_name' => 'XixaPay',
        'active_funding_provider' => 'katpay',
        'multi_provider_funding' => false,
        'auto_verify_mock_funding' => false,
        'xixapay_api_key' => 'replace_with_xixapay_api_key',
        'xixapay_api_secret' => 'replace_with_xixapay_api_secret',
        'xixapay_business_id' => 'replace_with_xixapay_business_id',
        'xixapay_base_url' => 'https://api.xixapay.com',
        'xixapay_bank_codes' => ['20867'],
        'xixapay_webhook_url' => 'https://gemdata.com.ng/api/xixapay.php',
        'katpay_enabled' => false,
        'katpay_api_key' => 'replace_with_katpay_api_key',
        'katpay_secret_key' => 'replace_with_katpay_secret_key',
        'katpay_base_url' => 'https://api.katpay.co/v1',
        'katpay_merchant_id' => 'replace_with_katpay_merchant_id',
        'katpay_bank_codes' => ['PALMPAY', 'OPAY'],
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
