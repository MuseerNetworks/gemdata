<?php
/**
 * GemData — PRODUCTION CONFIG TEMPLATE
 *
 * INSTRUCTIONS FOR DEPLOYMENT:
 *   1. Copy this file to: /home/yyrigitd/gemdata-config.php (OUTSIDE public_html)
 *   2. Set the correct values below (API keys, DB credentials, etc.)
 *   3. Point config.php to load from that path OR rename/copy to:
 *      /home/yyrigitd/public_html/includes/config.local.php
 *
 * SECURITY: This file contains secrets. Never commit it to git.
 *           Ensure it is NOT publicly accessible via browser.
 *           Test with: curl -I https://gemdata.com.ng/includes/config.local.php
 *           Expected: 403 Forbidden
 */

declare(strict_types=1);

return [
    'app' => [
        'environment'              => 'production',
        // On production, the app is at the domain root — base_url must be empty string.
        'base_url'                 => '',
        'public_origin'            => 'https://gemdata.com.ng',
        'force_https_in_production'=> true,
        'trusted_proxies'          => ['127.0.0.1'],
    ],

    'providers' => [
        // ── Albani Provider ───────────────────────────────────────────────
        // Set 'enabled' => false if not yet contracted. The admin dashboard
        // will still load — disabled providers are caught gracefully.
        'albani' => [
            'label'      => 'Albani',
            'driver'     => 'albani',
            'enabled'    => false,          // ← set true when you have live credentials
            'base_url'   => 'https://api.albanidata.com',
            'api_key'    => '',             // ← fill in
            'api_secret' => '',             // ← fill in
        ],
        'smeplug' => [
            'label'      => 'SMEPlug',
            'driver'     => 'smeplug',
            'enabled'    => false,          // ← set true when ready
            'base_url'   => 'https://smeplug.ng/api/v2',
            'api_key'    => '',             // ← fill in
            'api_secret' => '',             // ← fill in
        ],
    ],

    'webhooks' => [
        'shared_secret'   => '',           // ← generate: openssl rand -hex 32
        'allowed_sources' => ['generic', 'albani', 'smeplug'],
    ],

    'payments' => [
        'default_gateway'               => 'paystack',
        'display_gateway_name'          => 'Paystack',
        'auto_assign_dedicated_account' => true,

        // ── Paystack LIVE Keys ──────────────────────────────────────────
        // Get from: https://dashboard.paystack.com/#/settings/developers
        // Switch to "Live" tab — copy the LIVE keys, not test keys.
        'paystack_public_key'  => 'pk_live_REPLACE_WITH_LIVE_PUBLIC_KEY',
        'paystack_secret_key'  => 'sk_live_REPLACE_WITH_LIVE_SECRET_KEY',
        // ----------------------------------------------------------------

        'paystack_base_url'  => 'https://api.paystack.co',
        'dva_preferred_bank' => 'wema-bank',  // ← recommended for Nigerian VTU platforms

        // ── Paystack Dashboard URLs (set these in your Paystack dashboard) ─
        // Dashboard → Settings → API Keys & Webhooks → Live
        'paystack_callback_url' => 'https://gemdata.com.ng/user/fund-wallet.php',
        'paystack_webhook_url'  => 'https://gemdata.com.ng/api/webhook.php',
        // ----------------------------------------------------------------

        // ── ZenithPay Virtual Account (LIVE) ─────────────────────────────
        // Sign up: https://zenithpay.ng/register
        // Get your Bearer token from: Dashboard → Settings → API Keys
        'zenithpay_secret_key'  => 'REPLACE_WITH_YOUR_ZENITHPAY_LIVE_TOKEN',
        'zenithpay_base_url'    => 'https://zenithpay.ng',
        'zenithpay_auto_assign' => true,   // auto-assign VA when user registers
        // Webhook URL — enter this in ZenithPay Dashboard → Settings → Webhook URL:
        'zenithpay_webhook_url' => 'https://gemdata.com.ng/api/zenithpay-webhook.php',
        // Allowed IPs — copy from ZenithPay Dashboard → Settings → Zenithpay Public IP
        // These IPs are the ONLY sources your server will accept ZenithPay webhooks from.
        'zenithpay_allowed_ips' => [
            // Paste each ZenithPay IP on its own line here, e.g.:
            // '41.58.x.x',
            // '102.89.x.x',
        ],
        // ----------------------------------------------------------------
    ],

    'mail' => [
        'driver'                    => 'smtp',
        'host'                      => '',       // ← your cPanel SMTP host
        'port'                      => 465,
        'encryption'                => 'ssl',
        'username'                  => '',       // ← e.g. no-reply@gemdata.com.ng
        'password'                  => '',       // ← email account password
        'from_email'                => 'no-reply@gemdata.com.ng',
        'from_name'                 => 'GemData',
        'debug_display_reset_links' => false,
    ],

    'mobile' => [
        'webview_origin' => 'https://gemdata.com.ng',
    ],
];
