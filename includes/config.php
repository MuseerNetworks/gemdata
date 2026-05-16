<?php

declare(strict_types=1);

/**
 * GemData — MASTER CONFIGURATION FILE
 * 
 * This file is production-ready and expects environment variables.
 * For local development, use a .env file or rely on config.local.php.
 * For cPanel/Production, use environment variables or a private override file
 * outside of the public_html directory (e.g. /home/user/gemdata-config.php).
 */

if (!function_exists('load_env_file')) {
    function load_env_file(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $separatorPos = strpos($trimmed, '=');
            if ($separatorPos === false) {
                continue;
            }

            $key = trim(substr($trimmed, 0, $separatorPos));
            $value = trim(substr($trimmed, $separatorPos + 1));

            if ($key === '') {
                continue;
            }

            $existingValue = getenv($key);
            if ($existingValue === false && array_key_exists($key, $_ENV)) {
                $existingValue = $_ENV[$key];
            }

            if ($existingValue !== false && $existingValue !== null && trim((string) $existingValue) !== '') {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

load_env_file(dirname(__DIR__) . '/.env');

// Helper function to read env variables with fallback
if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? null;
        }
        if ($value === false || $value === null) {
            return $default;
        }
        switch (strtolower((string)$value)) {
            case 'true': return true;
            case 'false': return false;
            case 'null': return null;
        }
        return $value;
    }
}

$config = [
    // ── 1. App Configuration ───────────────────────────────────────────────
    'app' => [
        'name' => env('APP_NAME', 'GemData'),
        'environment' => env('APP_ENV', 'production'), // Default to 'production' for safety
        'base_url' => env('APP_BASE_URL', ''),
        'public_origin' => env('APP_PUBLIC_ORIGIN', 'https://gemdata.com.ng'),
        'currency' => env('APP_CURRENCY', 'NGN'),
        'timezone' => env('APP_TIMEZONE', 'Africa/Lagos'),
        'force_https_in_production' => env('FORCE_HTTPS', true),
        'trusted_proxies' => explode(',', env('TRUSTED_PROXIES', '127.0.0.1')),
        
        // Security & Rate Limiting
        'rate_limit_per_minute' => (int) env('RATE_LIMIT_PER_MINUTE', 60),
        'admin_login_attempt_limit' => (int) env('ADMIN_LOGIN_LIMIT', 5),
        'admin_login_attempt_window_minutes' => (int) env('ADMIN_LOGIN_WINDOW', 15),
        'user_login_attempt_limit' => (int) env('USER_LOGIN_LIMIT', 5),
        'user_login_attempt_window_minutes' => (int) env('USER_LOGIN_WINDOW', 15),
        
        // Paths & Caching
        'cache_dir' => env('CACHE_DIR', dirname(__DIR__) . '/storage/cache'),
        'cache_driver' => env('CACHE_DRIVER', 'file'), // 'file', 'redis', 'memcached'
        
        // Error & Logging
        'log_level' => env('LOG_LEVEL', 'error'), // debug, info, warning, error
        'bootstrap_log_to_file' => env('LOG_BOOTSTRAP', true),
        'bootstrap_log_file' => env('BOOTSTRAP_LOG_FILE', dirname(__DIR__) . '/storage/logs/bootstrap.log'),
        'provider_log_to_file' => env('LOG_PROVIDERS', true),
        'provider_log_file' => env('PROVIDER_LOG_FILE', dirname(__DIR__) . '/storage/logs/provider.log'),
        
        'required_extensions' => ['json', 'pdo_mysql', 'curl', 'mbstring', 'openssl'],
        'maintenance_bypass_paths' => ['/admin/', '/api/webhook.php', '/api/zenithpay-webhook.php'],
    ],

    // ── 2. Database Configuration ──────────────────────────────────────────
    'db' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'dbname' => env('DB_DATABASE', 'gemdata_api'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'strict_mode' => env('DB_STRICT_MODE', true),
    ],

    // ── 3. Session & Security Configuration ────────────────────────────────
    'session' => [
        'timeout_seconds' => (int) env('SESSION_TIMEOUT', 3600),
        'secure_cookie' => env('SESSION_SECURE_COOKIE', true),
        'http_only' => env('SESSION_HTTP_ONLY', true),
        'same_site' => env('SESSION_SAME_SITE', 'Lax'), // 'Strict' or 'Lax'
        'cookie_name' => env('SESSION_COOKIE_NAME', 'gemdata_session'),
    ],

    // ── 4. JWT & Auth Configuration ────────────────────────────────────────
    'auth' => [
        'jwt_secret' => env('JWT_SECRET', ''), // MUST be 32+ chars random string in production
        'jwt_expiration' => (int) env('JWT_EXPIRATION', 3600), // 1 hour
        'password_hash_algo' => PASSWORD_BCRYPT,
    ],

    // ── 5. Feature Flags ───────────────────────────────────────────────────
    'features' => [
        'enable_referrals' => env('FEATURE_REFERRALS', true),
        'enable_kyc' => env('FEATURE_KYC', true), // Required for production Fintech
        'enable_api_access' => env('FEATURE_API_ACCESS', true),
    ],

    // ── 6. CORS Configuration ──────────────────────────────────────────────
    'cors' => [
        'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept'],
        'max_age' => (int) env('CORS_MAX_AGE', 86400),
    ],

    // ── 7. File Upload Configuration ───────────────────────────────────────
    'uploads' => [
        'max_size_mb' => (int) env('UPLOAD_MAX_SIZE_MB', 5),
        'allowed_types' => ['image/jpeg', 'image/png', 'application/pdf'],
        'upload_dir' => env('UPLOAD_DIR', dirname(__DIR__) . '/storage/uploads'),
    ],

    // ── 8. API Providers Configuration ─────────────────────────────────────
    'providers' => [
        'mock_main' => [
            'label' => 'Mock VTU Provider',
            'driver' => 'mock',
            'base_url' => 'local',
            'api_key' => env('MOCK_API_KEY', ''),
            'api_secret' => env('MOCK_API_SECRET', ''),
            'enabled' => env('MOCK_ENABLED', false),
            'sandbox' => env('MOCK_SANDBOX', true),
        ],
        'albani' => [
            'label' => 'AlbaniAPI',
            'driver' => 'albani',
            'base_url' => env('ALBANI_BASE_URL', 'https://albanidata.com/api/v1'),
            'api_key' => env('ALBANI_API_KEY', ''),
            'api_secret' => env('ALBANI_API_SECRET', ''),
            'enabled' => env('ALBANI_ENABLED', false),
            'sandbox' => env('ALBANI_SANDBOX', false),
            'timeout_seconds' => (int) env('ALBANI_TIMEOUT', 20),
            'retry_count' => (int) env('ALBANI_RETRY_COUNT', 1),
            'balance_path' => '/wallet/balance',
            'balance_fallback_paths' => ['/wallet/balance'],
        ],
        'smeplug' => [
            'label' => 'SMEPlug',
            'driver' => 'smeplug',
            'base_url' => env('SMEPLUG_BASE_URL', 'https://smeplug.ng/api/v2'),
            'api_key' => env('SMEPLUG_API_KEY', ''),
            'api_secret' => env('SMEPLUG_API_SECRET', ''),
            'enabled' => env('SMEPLUG_ENABLED', false),
            'sandbox' => env('SMEPLUG_SANDBOX', false), // Must be false in production
        ],
        'vtpass' => [
            'label' => 'VTpass',
            'driver' => 'vtpass',
            'base_url' => env('VTPASS_BASE_URL', 'https://vtpass.com/api/v1'),
            'api_key' => env('VTPASS_API_KEY', ''),
            'api_secret' => env('VTPASS_API_SECRET', ''),
            'enabled' => env('VTPASS_ENABLED', false),
            'sandbox' => env('VTPASS_SANDBOX', false), // Must be false in production
        ],
        'clubkonnect' => [
            'label' => 'ClubKonnect',
            'driver' => 'clubkonnect',
            'base_url' => env('CLUBKONNECT_BASE_URL', 'https://www.clubkonnect.com/api/'),
            'api_key' => env('CLUBKONNECT_API_KEY', ''),
            'api_secret' => env('CLUBKONNECT_API_SECRET', ''),
            'enabled' => env('CLUBKONNECT_ENABLED', false),
            'sandbox' => env('CLUBKONNECT_SANDBOX', false), // Must be false in production
        ],
        'alrahuzdata' => [
            'label' => 'AlrahuzData',
            'driver' => 'alrahuzdata',
            'base_url' => env('ALRAHUZDATA_BASE_URL', 'https://alrahuzdata.com.ng/api/'),
            'api_key' => env('ALRAHUZDATA_API_KEY', ''),
            'api_secret' => env('ALRAHUZDATA_API_SECRET', ''),
            'enabled' => env('ALRAHUZDATA_ENABLED', false),
            'sandbox' => env('ALRAHUZDATA_SANDBOX', false), // Must be false in production
        ],
        'easyaccessapi' => [
            'label' => 'EasyAccessAPI',
            'driver' => 'easyaccessapi',
            'base_url' => env('EASYACCESS_BASE_URL', 'https://easyaccessapi.com.ng/api/'),
            'api_key' => env('EASYACCESS_API_KEY', ''),
            'api_secret' => env('EASYACCESS_API_SECRET', ''),
            'enabled' => env('EASYACCESS_ENABLED', false),
            'sandbox' => env('EASYACCESS_SANDBOX', false), // Must be false in production
        ],
    ],

    // ── 9. Webhooks Configuration ──────────────────────────────────────────
    'webhooks' => [
        'shared_secret' => env('WEBHOOK_SHARED_SECRET', ''), // Important: generate using `openssl rand -hex 32`
        'allowed_sources' => explode(',', env('WEBHOOK_ALLOWED_SOURCES', 'paystack,zenithpay,generic,albani,smeplug')),
    ],

    // ── 10. Payments Configuration ─────────────────────────────────────────
    'payments' => [
        'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'paystack'),
        'display_gateway_name' => env('PAYMENT_DISPLAY_NAME', 'Paystack'),
        'auto_verify_mock_funding' => env('PAYMENT_AUTO_VERIFY_MOCK', false),
        'auto_assign_dedicated_account' => env('PAYMENT_AUTO_ASSIGN_DVA', true),
        
        // Paystack
        'paystack_public_key' => env('PAYSTACK_PUBLIC_KEY', ''),
        'paystack_secret_key' => env('PAYSTACK_SECRET_KEY', ''),
        'paystack_base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        'dva_preferred_bank' => env('PAYSTACK_DVA_BANK', 'wema-bank'),
        'paystack_callback_url' => env('PAYSTACK_CALLBACK_URL', env('APP_PUBLIC_ORIGIN', 'https://gemdata.com.ng') . '/user/fund-wallet.php'),
        'paystack_webhook_url' => env('PAYSTACK_WEBHOOK_URL', env('APP_PUBLIC_ORIGIN', 'https://gemdata.com.ng') . '/api/webhook.php'),

        // ZenithPay
        'zenithpay_secret_key' => env('ZENITHPAY_SECRET_KEY', ''),
        'zenithpay_base_url' => env('ZENITHPAY_BASE_URL', 'https://zenithpay.ng'),
        'zenithpay_auto_assign' => env('ZENITHPAY_AUTO_ASSIGN', true),
        'zenithpay_webhook_url' => env('ZENITHPAY_WEBHOOK_URL', env('APP_PUBLIC_ORIGIN', 'https://gemdata.com.ng') . '/api/zenithpay-webhook.php'),
        'zenithpay_allowed_ips' => array_values(array_filter(array_map(
            static fn($ip): string => trim((string) $ip),
            explode(',', env('ZENITHPAY_ALLOWED_IPS', ''))
        ))), // Set exact ZenithPay dashboard IPs in production.
    ],

    // ── 11. Mail & SMS Configuration ───────────────────────────────────────
    'mail' => [
        'driver' => env('MAIL_DRIVER', 'smtp'),
        'host' => env('MAIL_HOST', 'localhost'),
        'port' => (int) env('MAIL_PORT', 465),
        'encryption' => env('MAIL_ENCRYPTION', 'ssl'),
        'username' => env('MAIL_USERNAME', ''),
        'password' => env('MAIL_PASSWORD', ''),
        'from_email' => env('MAIL_FROM_ADDRESS', 'no-reply@gemdata.com.ng'),
        'from_name' => env('MAIL_FROM_NAME', 'GemData'),
        'debug_display_reset_links' => env('MAIL_DEBUG_LINKS', false),
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'termii'), // termii, bulksmsnigeria, mock
        'api_key' => env('SMS_API_KEY', ''),
        'sender_id' => env('SMS_SENDER_ID', 'GemData'),
    ],

    // ── 12. PWA & Mobile Configuration ─────────────────────────────────────
    'pwa' => [
        'short_name' => env('PWA_SHORT_NAME', 'GemData'),
        'theme_color' => env('PWA_THEME_COLOR', '#0f172a'),
        'background_color' => env('PWA_BG_COLOR', '#f8fbff'),
        'display' => 'standalone',
        'orientation' => 'portrait',
        'offline_page' => '/offline.html',
    ],
    
    'mobile' => [
        'capacitor_app_id' => env('MOBILE_APP_ID', 'com.gemdata.app'),
        'capacitor_app_name' => env('MOBILE_APP_NAME', 'GemData'),
        'webview_origin' => env('MOBILE_WEBVIEW_ORIGIN', 'https://gemdata.com.ng'),
    ],
    
    'notifications' => [
        'web_push_enabled' => env('NOTIFICATIONS_WEB_PUSH', true), // Typically true for production user engagement
    ],
];

// ── Overrides Strategy (cPanel / Local) ──────────────────────────────────
// Load private overrides from one directory up (safely outside public_html)
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

// Load local overrides for local CLI/localhost workflows, or whenever the base
// environment is already non-production.
$localOverride = __DIR__ . '/config.local.php';
$requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
$isLocalHost = in_array($requestHost, ['localhost', '127.0.0.1', '::1'], true);
$allowLocalOverride = $config['app']['environment'] !== 'production' || PHP_SAPI === 'cli' || $isLocalHost;
if ($allowLocalOverride && is_file($localOverride)) {
    $localConfig = require $localOverride;
    if (is_array($localConfig)) {
        $config = array_replace_recursive($config, $localConfig);
        $config['__meta']['local_override_loaded'] = true;
    }
}

return $config;
