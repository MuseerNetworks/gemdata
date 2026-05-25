# GemData Production Environment Example

This is a sanitized example for the private production config file. Use it as a guide for `/home/<cpanel_user>/gemdata-config.php` or the equivalent path outside the web root.

Do not place real secrets in this Markdown file. Do not commit the real `gemdata-config.php`.

## Private Config Example

```php
<?php

declare(strict_types=1);

return [
    'app' => [
        'environment' => 'production',
        'base_url' => '',
        'public_origin' => 'https://gemdata.com.ng',
        'force_https_in_production' => true,
        'timezone' => 'Africa/Lagos',
        'trusted_proxies' => [],
        'required_extensions' => ['json', 'pdo_mysql', 'curl'],
        'cache_dir' => '/home/<cpanel_user>/gemdata-storage/cache',
        'bootstrap_log_to_file' => true,
        'bootstrap_log_file' => '/home/<cpanel_user>/gemdata-storage/logs/bootstrap.log',
        'provider_log_to_file' => true,
        'provider_log_file' => '/home/<cpanel_user>/gemdata-storage/logs/provider.log',
    ],

    'db' => [
        'host' => 'localhost',
        'port' => '3306',
        'dbname' => '<cpanel_user>_gemdata',
        'username' => '<cpanel_user>_gemdata_app',
        'password' => '__CHANGE_ME_DB_PASSWORD__',
        'charset' => 'utf8mb4',
    ],

    'security' => [
        'admin_2fa_enabled' => true,
        'email_verification_required_for_money_movement' => true,
    ],

    'webhooks' => [
        'shared_secret' => '__CHANGE_ME_LONG_RANDOM_WEBHOOK_SECRET__',
        'allowed_sources' => ['xixapay'],
    ],

    'payments' => [
        'default_gateway' => 'xixapay',
        'display_gateway_name' => 'XixaPay',
        'active_funding_provider' => 'katpay',
        'multi_provider_funding' => false,
        'auto_verify_mock_funding' => false,
        'xixapay_api_key' => '__CHANGE_ME_XIXAPAY_API_KEY__',
        'xixapay_api_secret' => '__CHANGE_ME_XIXAPAY_API_SECRET__',
        'xixapay_business_id' => '__CHANGE_ME_XIXAPAY_BUSINESS_ID__',
        'xixapay_base_url' => 'https://api.xixapay.com',
        'xixapay_bank_codes' => ['__CHANGE_ME_BANK_CODE__'],
        'xixapay_webhook_url' => 'https://gemdata.com.ng/api/xixapay.php',
        'katpay_enabled' => false,
        'katpay_api_key' => '__CHANGE_ME_KATPAY_API_KEY__',
        'katpay_secret_key' => '__CHANGE_ME_KATPAY_SECRET_KEY__',
        'katpay_base_url' => 'https://api.katpay.co/v1',
        'katpay_merchant_id' => '__CHANGE_ME_KATPAY_MERCHANT_ID__',
        'katpay_bank_codes' => ['PALMPAY', 'OPAY'],
    ],

    'mail' => [
        'driver' => 'smtp',
        'from_email' => 'support@gemdata.com.ng',
        'from_name' => 'GemData',
        'debug_display_reset_links' => false,
        'smtp' => [
            'host' => '__CHANGE_ME_SMTP_HOST__',
            'port' => 465,
            'username' => '__CHANGE_ME_SMTP_USERNAME__',
            'password' => '__CHANGE_ME_SMTP_PASSWORD__',
            'encryption' => 'ssl',
        ],
    ],

    'providers' => [
        'mock_main' => [
            'enabled' => false,
            'sandbox' => true,
        ],
        'albani' => [
            'label' => 'AlbaniAPI',
            'driver' => 'albani',
            'base_url' => 'https://albanidata.com/api/v1',
            'api_key' => '__CHANGE_ME_ALBANI_API_KEY__',
            'enabled' => false,
            'sandbox' => true,
            'timeout_seconds' => 20,
            'retry_count' => 1,
            'balance_path' => '/wallet/balance',
            'balance_fallback_paths' => ['/wallet/balance'],
        ],
        'smeplug' => [
            'label' => 'SMEPlug',
            'driver' => 'smeplug',
            'base_url' => '__CHANGE_ME_SMEPLUG_BASE_URL__',
            'api_key' => '__CHANGE_ME_SMEPLUG_API_KEY__',
            'api_secret' => '__CHANGE_ME_SMEPLUG_API_SECRET__',
            'enabled' => false,
            'sandbox' => true,
        ],
        'vtpass' => [
            'label' => 'VTpass',
            'driver' => 'vtpass',
            'base_url' => '__CHANGE_ME_VTPASS_BASE_URL__',
            'api_key' => '__CHANGE_ME_VTPASS_API_KEY__',
            'api_secret' => '__CHANGE_ME_VTPASS_API_SECRET__',
            'enabled' => false,
            'sandbox' => true,
        ],
    ],

    'mobile' => [
        'webview_origin' => 'https://gemdata.com.ng',
    ],
];
```

## File Placement

Place the real file outside the public web root:

```text
/home/<cpanel_user>/gemdata-config.php
```

Do not place it here:

```text
/home/<cpanel_user>/public_html/gemdata-config.php
/home/<cpanel_user>/public_html/.env
/home/<cpanel_user>/public_html/includes/config.local.php
```

## Permission Guidance

Use the most restrictive permissions allowed by the host:

```text
gemdata-config.php: 600 or 640
storage directories: 750, 755, or host-approved writable equivalent
PHP files: 644
directories: 755
```

## Production Secret Checklist

- Replace every `__CHANGE_ME_*__` value.
- Generate a unique webhook secret of at least 32 random bytes.
- Use a dedicated database user, not the cPanel master user if avoidable.
- Keep provider `enabled` set to `false` until live tests pass.
- Keep `debug_display_reset_links` set to `false`.
- Keep `auto_verify_mock_funding` set to `false`.
- Rotate any temporary test passwords before go-live.
- Rotate secrets immediately if they appear in logs, screenshots, chat, or committed files.
