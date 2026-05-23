# GemData cPanel UI Deployment

## Fast path
- Check [`.cpanel.yml`](C:/xampp/htdocs/gemdata/.cpanel.yml) and confirm the cPanel username/path is correct.
- In `cPanel > Git Version Control`, click `Update from Remote`, then `Deploy HEAD Commit`.
- Open `https://gemdata.com.ng/`, `https://gemdata.com.ng/user/login.php`, and `https://gemdata.com.ng/admin/login.php`.
- If any page throws `500`, rename `public_html/.htaccess` to `.htaccess.off`, then reload.
- If the site still fails, inspect `cPanel > Metrics > Errors` and open `fallback_test.php` or `emergency-index.php`.

## 1. Prepare production config
- Create `/home/<cpanel_user>/gemdata-config.php`
- Copy from [cpanel-production-config.example.php](/C:/xampp/htdocs/gemdata/docs/cpanel-production-config.example.php)
- Store all secrets only in that file
- Keep provider `enabled => false` until tested
- Set `app.bootstrap_log_file` to a writable path such as `/home/<cpanel_user>/public_html/storage/logs/bootstrap.log`

## 2. Enable maintenance mode
- Log in to admin and enable maintenance mode before database repair/import

## 3. Deploy from Git
- Open `cPanel > Git Version Control`
- Click `Update from Remote`
- Confirm the latest commit is present
- Click `Deploy HEAD Commit`
- Confirm `.cpanel.yml` deploys into `/home/<cpanel_user>/public_html/`
- Confirm the `.cpanel.yml` username/path matches the live cPanel account before deploying
- Confirm `public_html/index.php` and `public_html/.htaccess` were overwritten with the latest repo versions
- Confirm there is no duplicate `public_html/gemdata/` directory left from an older deployment

## 4. Database import
- Open `cPanel > phpMyAdmin`
- For a fresh install, import `database/database.sql`
- For an existing or partially migrated database, import `database/production-repair.sql`

## 5. Cron
- Add:
```cron
*/2 * * * * /usr/local/bin/php /home/<cpanel_user>/public_html/cron/process-pending.php
*/5 * * * * /usr/local/bin/php /home/<cpanel_user>/public_html/cron/retry-failed.php
*/10 * * * * /usr/local/bin/php /home/<cpanel_user>/public_html/cron/reconcile.php
```

## 6. Verify
- Review [smoke-test-checklist.md](/C:/xampp/htdocs/gemdata/docs/smoke-test-checklist.md)
- Confirm the funding page shows bank-transfer-only production messaging
- Confirm `api/xixapay.php` logs XixaPay webhook traffic without exposing secrets
- Confirm `storage/logs/bootstrap.log` shows no ongoing bootstrap failures

## 7. Exit maintenance mode
- Disable maintenance mode only after database import, cron setup, and smoke checks pass

## 8. If the site returns HTTP 500
- Replace `public_html/index.php` temporarily with plain `<?php echo "PHP WORKING";` and reload the site.
- If that still returns `500`, rename `public_html/.htaccess` to `.htaccess.off` and test again.
- If plain PHP still fails, inspect `cPanel > Metrics > Errors`, the latest `error_log`, and any `.user.ini` in `public_html` or parent directories.
- Upload [emergency-index.php](/C:/xampp/htdocs/gemdata/emergency-index.php) or [fallback_test.php](/C:/xampp/htdocs/gemdata/fallback_test.php) and open it directly.
- Verify `MultiPHP Manager` shows PHP 8.3 for `gemdata.com.ng`.
- Verify there is no inherited `auto_prepend_file`, `auto_append_file`, or unsupported handler directive from cPanel or a parent folder.
- Check permissions: directories `755`, PHP files `644`, writable app paths `775` or the host-approved equivalent.
