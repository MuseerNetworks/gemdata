# GemData Config Example

This file explains how GemData configuration is loaded and what must be set before production deployment. It is documentation only. Do not put real secrets in this file.

## Config Loading Order

GemData loads configuration in this order:

1. `includes/config.php`
   - Application defaults.
   - Safe for local development only.
   - Contains no production secrets.

2. `../gemdata-config.php`
   - Private production override.
   - Resolved from the project root as `dirname(__DIR__, 2) . '/gemdata-config.php'` inside `includes/config.php`.
   - On cPanel, this should normally be `/home/<cpanel_user>/gemdata-config.php`, outside `public_html`.
   - This is where production database credentials, payment keys, SMTP credentials, webhook secrets, and provider keys belong.

3. `includes/config.local.php`
   - Optional local development override.
   - Loaded only when `app.environment` is not `production`.
   - Must not exist on production.

## Required Production Groups

### `app`

Required production values:

- `environment`: must be `production`.
- `public_origin`: the public HTTPS origin, for example `https://gemdata.com.ng`.
- `base_url`: keep empty for root-domain deployment unless the app is deployed under a subdirectory.
- `force_https_in_production`: keep `true`.
- `timezone`: keep aligned with business operations, currently `Africa/Lagos`.
- `trusted_proxies`: configure only if the production host or CDN terminates TLS before PHP.
- `required_extensions`: include all enabled production features, at minimum `json`, `pdo_mysql`, and `curl`.
- `cache_dir`, `bootstrap_log_file`, `provider_log_file`: must point to writable, non-public operational storage where possible.

Production safety:

- Do not run production with `environment = local`.
- Do not expose raw PHP errors in production.
- Do not leave public diagnostics accessible.

### `db`

Required production values:

- `host`
- `port`
- `dbname`
- `username`
- `password`
- `charset`

Production safety:

- Use a dedicated database user with only the privileges GemData needs.
- Do not use the local `root` database user.
- Do not store database credentials in repo-tracked files.

### `security`

Required production values:

- `admin_2fa_enabled`: set to `true` before real-money operation.
- `email_verification_required_for_money_movement`: keep `true`.

Production safety:

- Every privileged admin must complete 2FA setup before production launch.
- Users should not be able to fund, spend, withdraw, or trigger provider transactions before account email verification is complete.

### `webhooks`

Required production values:

- `shared_secret`: long random production-only secret.
- `allowed_sources`: include only enabled webhook providers, currently `xixapay`.

Production safety:

- Rotate webhook secrets after any suspected leak.
- Reject unsigned, replayed, duplicate, or unknown-source webhook traffic.
- Keep webhook event IDs unique and idempotent in the database.

### `payments`

Required production values:

- `default_gateway`
- `display_gateway_name`
- `xixapay_api_key`
- `xixapay_api_secret`
- `xixapay_business_id`
- `xixapay_base_url`
- `xixapay_bank_codes`
- `xixapay_webhook_url`

Production safety:

- Keep `auto_verify_mock_funding` set to `false`.
- Do not enable wallet auto-crediting until webhook signature, replay protection, and idempotency tests pass.
- Verify duplicate webhook delivery cannot double-credit a wallet.

### `mail`

Required production values:

- `driver`: normally `smtp`.
- `from_email`
- `from_name`
- SMTP host, port, username, password, encryption, and auth options if supported by the active mail implementation.

Production safety:

- `debug_display_reset_links` must be `false` in production.
- Password reset and email verification links must not be displayed in the browser in production.

### `providers`

Provider entries usually include:

- `label`
- `driver`
- `base_url`
- `api_key`
- `api_secret` where required by the provider
- `enabled`
- `sandbox`
- timeout and retry settings where supported

Production safety:

- Keep all providers disabled until credentials, pricing, plan mapping, idempotency, and rollback behavior are verified.
- Enable one provider at a time.
- Keep sandbox mode on until live-provider tests are complete.

### `mobile`

Required production values:

- `webview_origin`: production origin for mobile webview/PWA behavior.
- `capacitor_app_id` and `capacitor_app_name` if mobile builds are used.

## Secret Handling Rules

- Never commit real credentials, API keys, TOTP setup secrets, test passwords, webhook secrets, SMTP passwords, database passwords, provider tokens, or private config files.
- Never paste production secrets into chat, tickets, screenshots, logs, or deployment reports.
- Store production config outside the web root.
- Restrict file permissions on the private config file to the hosting account.
- Rotate secrets immediately if they are exposed.

## Production Readiness Defaults

Before real-money launch:

- `app.environment = production`
- `app.force_https_in_production = true`
- `mail.debug_display_reset_links = false`
- `security.admin_2fa_enabled = true`
- `security.email_verification_required_for_money_movement = true`
- `payments.auto_verify_mock_funding = false`
- Mock providers disabled
- Live providers disabled until individually verified
- Public diagnostics removed or blocked
- Logs, SQL dumps, backups, and private config files not web-accessible
