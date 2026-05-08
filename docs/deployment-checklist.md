# GemData Deployment Checklist

## Configuration
- Create a private production config at `/home/<cpanel_user>/gemdata-config.php`.
- Use `includes/config.local.php` only for local development or non-cPanel overrides.
- Set `app.environment` correctly.
- Set `app.base_url` to `''` when the app is hosted at the domain root.
- Set `app.public_origin` to `https://gemdata.com.ng`.
- Replace local database credentials with cPanel credentials:
  - database: `yyrigitd_gemdata`
  - user: `yyrigitd_admin`
  - password: the password you create in cPanel
  - host: usually `localhost`
  - port: usually `3306`
- Set a strong `webhooks.shared_secret`.
- Disable `mail.debug_display_reset_links` in production.
- Keep provider credentials only in the local override file.
- Use restrictive permissions on `/home/<cpanel_user>/gemdata-config.php`, ideally `600`.

## Payments and recovery
- Set the live payment gateway identifier in `payments.default_gateway`.
- Set `payments.paystack_secret_key` and, if needed, `payments.dva_preferred_bank`.
- Decide whether `payments.auto_assign_dedicated_account` should be enabled in that environment.
- Make the payment callback endpoint publicly reachable.
- Configure a real mail transport for password reset delivery.

## Operations
- Apply the latest SQL migrations before release.
- Schedule `cron/process-pending.php` to run at a short interval, for example:
  - `*/2 * * * * /usr/local/bin/php /home/<cpanel_user>/public_html/cron/process-pending.php`
- Keep `cron/retry-failed.php` enabled only if automated retry policy is intended.
- If retry automation is enabled, schedule it explicitly:
  - `*/5 * * * * /usr/local/bin/php /home/<cpanel_user>/public_html/cron/retry-failed.php`
- Verify admin login, user login, wallet funding, and one service purchase.
- Register a new user and confirm the dedicated transfer account shows as assigned or pending on the wallet funding page.
- Check provider balances, webhook validation, and retry behavior.

## Safety
- Back up the database before deployment.
- Ensure PHP and webserver error logs are enabled.
- Keep cron and local-only tools inaccessible from public web routes.
- Keep files and folders on shared hosting at standard permissions:
  - directories `755`
  - public PHP files `644`
  - private config outside webroot `600` where supported
