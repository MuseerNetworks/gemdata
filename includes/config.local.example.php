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
        'allowed_sources' => ['paystack'],
    ],
    'payments' => [
        'default_gateway' => 'bank_transfer',
        'display_gateway_name' => 'Paystack',
        'auto_verify_mock_funding' => false,
        'auto_assign_dedicated_account' => true,
        'paystack_secret_key' => 'sk_live_replace_me',
        'paystack_base_url' => 'https://api.paystack.co',
        'dva_preferred_bank' => '',
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
