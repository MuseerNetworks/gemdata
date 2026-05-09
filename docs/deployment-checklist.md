# GemData Deployment Checklist

## Before deploy
- Confirm `.cpanel.yml` exists at repo root.
- Confirm production secrets exist only in `/home/<cpanel_user>/gemdata-config.php`.
- Confirm `includes/config.local.php` is absent on production.
- Back up the live database before every release.
- Enable maintenance mode from Admin Settings before any schema import or repair.

## Database
- Import `database/database.sql` for fresh installs only.
- For existing or partially migrated databases, import `database/production-repair.sql` first.
- Verify `schema_migrations`, `provider_accounts`, `webhook_events`, `transaction_events`, and `system_settings` exist after import.
- Keep all provider rows inactive until credentials are configured and tested.

## cPanel
- Use `Git Version Control > Update from Remote`.
- Use `Deploy HEAD Commit` only after the repo is clean and the latest changes are present.
- Confirm deployed files land in `/home/<cpanel_user>/public_html/`.
- Confirm `paystack-node/` is not copied into `public_html/`.

## Production config
- Set `app.environment = 'production'`.
- Set `app.public_origin = 'https://gemdata.com.ng'`.
- Set real cPanel database credentials.
- Set `payments.default_gateway = 'bank_transfer'`.
- Set `payments.paystack_secret_key`.
- Set `webhooks.shared_secret`.
- Keep every provider `enabled => false` until live verification is complete.

## Cron
- `*/2 * * * * /usr/local/bin/php /home/<cpanel_user>/public_html/cron/process-pending.php`
- `*/5 * * * * /usr/local/bin/php /home/<cpanel_user>/public_html/cron/retry-failed.php`
- `*/10 * * * * /usr/local/bin/php /home/<cpanel_user>/public_html/cron/reconcile.php`

## After deploy
- Disable maintenance mode only after schema import and smoke checks pass.
- Confirm admin login, user login, dashboard, provider page, wallet page, and webhook endpoint return clean responses.
- Confirm PHP logs show no exposed secrets and no bootstrap/config failures.
