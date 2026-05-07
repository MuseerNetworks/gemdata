<?php

declare(strict_types=1);

return [
    'app' => [
        'environment' => 'production',
        'public_origin' => 'https://your-domain.example',
        'force_https_in_production' => true,
        'trusted_proxies' => [],
    ],
    'providers' => [
        'mock_main' => [
            'api_key' => '',
            'api_secret' => '',
        ],
        'smeplug' => [
            'label' => 'SMEPlug',
            'driver' => 'mock',
            'base_url' => 'https://example.test',
            'api_key' => '',
            'api_secret' => '',
        ],
    ],
    'webhooks' => [
        'shared_secret' => 'replace-with-a-long-random-string',
        'allowed_sources' => ['generic', 'provider_a'],
    ],
    'payments' => [
        'default_gateway' => 'paystack',
        'display_gateway_name' => 'Paystack',
        'auto_verify_mock_funding' => false,
        'auto_assign_dedicated_account' => true,
        'paystack_secret_key' => 'sk_live_replace_me',
        'paystack_base_url' => 'https://api.paystack.co',
        'dva_preferred_bank' => '',
    ],
    'mail' => [
        'driver' => 'smtp',
        'from_email' => 'no-reply@example.com',
        'from_name' => 'GemData',
        'debug_display_reset_links' => false,
    ],
    'mobile' => [
        'webview_origin' => 'https://your-domain.example/gemdata',
    ],
];
