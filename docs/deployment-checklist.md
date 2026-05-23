# GemData Deployment Checklist

## Minimum manual checks
- Confirm [`.cpanel.yml`](C:/xampp/htdocs/gemdata/.cpanel.yml) points to the real cPanel username and `/home/<cpanel_user>/public_html/`.
- In cPanel, run `Git Version Control > Update from Remote`, then `Deploy HEAD Commit`.
- Open `https://gemdata.com.ng/`, `https://gemdata.com.ng/user/login.php`, and `https://gemdata.com.ng/admin/login.php`.
- If any of those fail, inspect `cPanel > Metrics > Errors`, the domain PHP error log, and `storage/logs/bootstrap.log` if present.
- If plain PHP still fails, rename `public_html/.htaccess` to `.htaccess.off` and check cPanel `Errors`.
- Only after the site loads cleanly, disable maintenance mode and continue with deeper smoke tests.

## Before deploy
- Confirm `.cpanel.yml` exists at repo root.
- Confirm the `.cpanel.yml` target path uses the real cPanel username and points to `/home/<cpanel_user>/public_html/`.
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
- Confirm the deployed `public_html/index.php` is the app entrypoint you expect.
- Confirm the deployed `public_html/.htaccess` matches the recovery-safe version in the repo.
- Confirm there is no accidental nested `public_html/gemdata/` deploy.
- Confirm old payment-provider demo folders are not copied into `public_html/`.

## Production config
- Set `app.environment = 'production'`.
- Set `app.public_origin = 'https://gemdata.com.ng'`.
- Keep `app.bootstrap_log_to_file = true` unless the host explicitly blocks file logging.
- Keep `app.bootstrap_log_file` on a writable path under `public_html/storage/logs/` or another host-approved writable directory.
- Keep `app.required_extensions` aligned with enabled production features.
- Set real cPanel database credentials.
- Set `payments.default_gateway = 'bank_transfer'`.
- Set `payments.xixapay_api_key`, `payments.xixapay_api_secret`, and `payments.xixapay_business_id`.
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
- Confirm `storage/logs/bootstrap.log` is either absent because nothing failed or contains only expected recovery diagnostics.

## Minimum success criteria
- Homepage returns `200`.
- User login returns `200`.
- Admin login returns `200`.
- No raw `500` appears in the browser.
- cPanel `Errors` and `storage/logs/bootstrap.log` show no new bootstrap failures.

## First-response flow for HTTP 500
- Temporarily replace `public_html/index.php` with `<?php echo "PHP WORKING";` to test whether plain PHP executes.
- If the plain index still fails, rename `public_html/.htaccess` to `.htaccess.off` and retry immediately.
- If it still fails after disabling `.htaccess`, inspect cPanel `Errors`, the domain's PHP error log, `.user.ini`, and any inherited parent `.htaccess`.
- Do not upload public diagnostic probes. Use cPanel error logs, PHP error logs, and a temporary private maintenance window for host-level debugging.
- Verify the domain is set to PHP 8.3 in `MultiPHP Manager` for the actual domain, not just the account default.
- Confirm there is no `auto_prepend_file` or `auto_append_file` forcing a fatal before `index.php`.
- Confirm permissions are sane: directories `755`, PHP files `644`, writable app directories `775` or the host-approved equivalent.
