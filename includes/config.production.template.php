<?php
/**
 * ============================================================
 * GemData — PRODUCTION CONFIG TEMPLATE  (v2 — May 2026)
 * ============================================================
 *
 * HOW TO USE:
 *   1. Copy this file to OUTSIDE public_html:
 *        /home/yyrigitd/gemdata-config.php
 *      OR rename/copy to:
 *        /home/yyrigitd/public_html/includes/config.local.php
 *
 *   2. Fill in EVERY value marked  ← FILL IN
 *
 *   3. Verify the file is NOT accessible via browser:
 *        curl -I https://gemdata.com.ng/includes/config.local.php
 *        Expected: 403 Forbidden
 *
 * SECURITY: Contains secrets. NEVER commit to Git.
 *           Store only outside public_html where possible.
 * ============================================================
 */

declare(strict_types=1);

return [

    // ══════════════════════════════════════════════════════
    // 1. APPLICATION
    // ══════════════════════════════════════════════════════
    'app' => [
        'environment'               => 'production',
        'base_url'                  => '',                          // leave empty — site is at domain root
        'public_origin'             => 'https://gemdata.com.ng',
        'force_https_in_production' => true,
        'trusted_proxies'           => ['127.0.0.1'],              // cPanel/LiteSpeed reverse proxy

        // Session security
        'session_timeout_seconds'   => 1800,                       // 30 min idle timeout
        'admin_login_attempt_limit' => 5,                          // max failed admin logins
        'admin_login_attempt_window_minutes' => 15,
        'user_login_attempt_limit'  => 10,
        'user_login_attempt_window_minutes'  => 15,

        // Logging & cache
        'bootstrap_log_to_file'     => true,
        'bootstrap_log_file'        => '/home/yyrigitd/logs/bootstrap.log',  // ← FILL IN (outside public_html)
        'cache_dir'                 => '/home/yyrigitd/cache',               // ← FILL IN (outside public_html)

        // PHP extensions required at boot
        'required_extensions'       => ['json', 'pdo_mysql', 'curl', 'mbstring', 'openssl'],

        // Rate limiting (API calls per minute per key)
        'rate_limit_per_minute'     => 60,
    ],

    // ══════════════════════════════════════════════════════
    // 2. DATABASE
    // ══════════════════════════════════════════════════════
    'db' => [
        'host'     => 'localhost',                  // always localhost on cPanel
        'port'     => '3306',
        'dbname'   => 'yyrigitd_gemdata',           // ← FILL IN  (cPanel DB name)
        'username' => 'yyrigitd_admin',             // ← FILL IN  (cPanel DB user)
        'password' => '',                           // ← FILL IN  (cPanel DB password)
        'charset'  => 'utf8mb4',
    ],

    // ══════════════════════════════════════════════════════
    // 3. VTU PROVIDERS
    // ══════════════════════════════════════════════════════
    'providers' => [

        // ── Albani (LIVE) ──────────────────────────────────
        'albani' => [
            'label'      => 'Albani',
            'driver'     => 'albani',
            'enabled'    => true,                   // ← set false to disable without code change
            'base_url'   => 'https://api.albanidata.com',
            'api_key'    => '',                     // ← FILL IN
            'api_secret' => '',                     // ← FILL IN
            'sandbox'    => false,
        ],

        // ── SMEPlug ────────────────────────────────────────
        'smeplug' => [
            'label'      => 'SMEPlug',
            'driver'     => 'smeplug',
            'enabled'    => false,                  // ← enable when contracted
            'base_url'   => 'https://smeplug.ng/api/v2',
            'api_key'    => '',                     // ← FILL IN
            'api_secret' => '',                     // ← FILL IN
            'sandbox'    => false,
        ],

        // ── ClubKonnect ────────────────────────────────────
        'clubkonnect' => [
            'label'      => 'ClubKonnect',
            'driver'     => 'clubkonnect',
            'enabled'    => false,
            'base_url'   => 'https://www.clubkonnect.com/api',
            'api_key'    => '',                     // ← FILL IN
            'api_secret' => '',                     // ← FILL IN
            'sandbox'    => false,
        ],

        // ── VTpass ────────────────────────────────────────
        'vtpass' => [
            'label'      => 'VTpass',
            'driver'     => 'vtpass',
            'enabled'    => false,
            'base_url'   => 'https://api-service.vtpass.com/api',
            'api_key'    => '',                     // ← FILL IN
            'api_secret' => '',                     // ← FILL IN
            'sandbox'    => false,
        ],

        // ── Mock (DISABLE IN PRODUCTION) ─────────────────
        // 'mock_main' => [
        //     'label'   => 'Mock VTU Provider',
        //     'driver'  => 'mock',
        //     'enabled' => false,
        //     'sandbox' => true,
        // ],
    ],

    // ══════════════════════════════════════════════════════
    // 4. WEBHOOKS
    // ══════════════════════════════════════════════════════
    'webhooks' => [
        // Generate: openssl rand -hex 32
        'shared_secret'   => '',                    // FILL IN for internal webhook tooling
        'allowed_sources' => ['xixapay', 'albani'],
    ],

    // ══════════════════════════════════════════════════════
    // 5. PAYMENTS
    // ══════════════════════════════════════════════════════
    'payments' => [
        'default_gateway'      => 'xixapay',
        'display_gateway_name' => 'XixaPay',
        'active_funding_provider' => 'katpay',
        'multi_provider_funding' => false,

        // XixaPay Virtual Account (LIVE)
        'xixapay_api_key'     => '',                    // FILL IN
        'xixapay_api_secret'  => '',                    // FILL IN
        'xixapay_business_id' => '',                    // FILL IN
        'xixapay_base_url'    => 'https://api.xixapay.com',
        'xixapay_bank_codes'  => ['20867'],
        'xixapay_webhook_url' => 'https://gemdata.com.ng/api/xixapay.php',
        'katpay_enabled'     => false,
        'katpay_api_key'     => '',                    // FILL IN
        'katpay_secret_key'  => '',                    // FILL IN
        'katpay_base_url'    => 'https://api.katpay.co/v1',
        'katpay_merchant_id' => '',                    // FILL IN
        'katpay_bank_codes'  => ['PALMPAY', 'OPAY'],
    ],

    // ══════════════════════════════════════════════════════
    // 6. MAIL (cPanel SMTP)
    // ══════════════════════════════════════════════════════
    'mail' => [
        'driver'                    => 'smtp',
        'host'                      => 'mail.gemdata.com.ng',  // ← FILL IN (your cPanel mail host)
        'port'                      => 465,
        'encryption'                => 'ssl',                  // 'ssl' for port 465, 'tls' for 587
        'username'                  => 'no-reply@gemdata.com.ng',  // ← FILL IN
        'password'                  => '',                     // ← FILL IN (email account password)
        'from_email'                => 'no-reply@gemdata.com.ng',
        'from_name'                 => 'GemData',
        'debug_display_reset_links' => false,                  // MUST be false in production
    ],

    // ══════════════════════════════════════════════════════
    // 7. BUSINESS / PRICING TIERS
    // ══════════════════════════════════════════════════════
    // These control which DB price tier maps to which user type.
    // Do NOT change these unless you rename the tiers in the DB too.
    'pricing' => [
        'smart_tier'    => 'SMART',          // default users after registration
        'reseller_tier' => 'RESELLER',       // resellers
        'api_tier'      => 'API_RESELLER',   // B2B API users (wholesale)
    ],

    // ══════════════════════════════════════════════════════
    // 8. RESELLER & COMMISSION SETTINGS
    // ══════════════════════════════════════════════════════
    // These are defaults. Actual rates live in the commissions DB table.
    // Admin sets per-service rates in admin panel.
    'commission' => [
        'default_rate_percent'    => 3.0,    // fallback if no DB rate found
        'minimum_withdrawal'      => 500.0,  // minimum ₦ to request withdrawal
        'withdrawal_notify_email' => 'admin@gemdata.com.ng',  // ← FILL IN — admin gets notified
    ],

    // ══════════════════════════════════════════════════════
    // 9. FEATURE FLAGS (DB-driven via system_settings)
    // ══════════════════════════════════════════════════════
    // These are initial seed defaults only.
    // Runtime flags live in system_settings DB table.
    // To toggle: UPDATE system_settings SET setting_value='1' WHERE setting_key='reseller_enabled';
    'feature_flags' => [
        'reseller_enabled'   => true,
        'commission_enabled' => true,
        'withdrawal_enabled' => true,
        'api_enabled'        => true,
        'maintenance_mode'   => false,
    ],

    // ══════════════════════════════════════════════════════
    // 10. SECURITY
    // ══════════════════════════════════════════════════════
    'security' => [
        // Content Security Policy — tighten as needed
        'csp_report_only' => false,

        'admin_2fa_enabled' => true,
        'email_verification_required_for_money_movement' => true,

        // Allowed file upload types (if any upload features added)
        'allowed_upload_types' => ['image/jpeg', 'image/png', 'application/pdf'],
        'max_upload_size_mb'   => 5,
    ],

    // ══════════════════════════════════════════════════════
    // 11. CRON JOBS (reference — set these in cPanel Cron)
    // ══════════════════════════════════════════════════════
    // Every 5 min:  php /home/yyrigitd/public_html/cron/process-pending.php
    // Every 10 min: php /home/yyrigitd/public_html/cron/reconcile.php
    // Every 30 min: php /home/yyrigitd/public_html/cron/retry-failed.php

    // ══════════════════════════════════════════════════════
    // 12. MOBILE / PWA
    // ══════════════════════════════════════════════════════
    'mobile' => [
        'webview_origin' => 'https://gemdata.com.ng',
    ],
];
