<?php

declare(strict_types=1);

return [
    'app' => [
        'environment' => 'local',
        'base_url' => '/gemdata',
        'public_origin' => 'http://localhost',
        'force_https_in_production' => false,
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
        'default_gateway' => 'mock_paystack',
        'display_gateway_name' => 'Paystack',
        'auto_verify_mock_funding' => true,
        'auto_assign_dedicated_account' => false,
        'paystack_secret_key' => '',
        'paystack_base_url' => 'https://api.paystack.co',
        'dva_preferred_bank' => '',
    ],
    'mail' => [
        'driver' => 'smtp',
        'from_email' => 'no-reply@gemdata.local',
        'from_name' => 'GemData',
        'debug_display_reset_links' => true,
    ],
    'mobile' => [
        'webview_origin' => 'http://localhost/gemdata',
    ],
];
