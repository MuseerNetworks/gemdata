# GemData cPanel UI Deployment

## 1. Prepare production config
- Create `/home/<cpanel_user>/gemdata-config.php`
- Copy from [cpanel-production-config.example.php](/C:/xampp/htdocs/gemdata/docs/cpanel-production-config.example.php)
- Store all secrets only in that file
- Keep provider `enabled => false` until tested

## 2. Enable maintenance mode
- Log in to admin and enable maintenance mode before database repair/import

## 3. Deploy from Git
- Open `cPanel > Git Version Control`
- Click `Update from Remote`
- Confirm the latest commit is present
- Click `Deploy HEAD Commit`
- Confirm `.cpanel.yml` deploys into `/home/<cpanel_user>/public_html/`

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
- Confirm `api/webhook.php` accepts Paystack webhook traffic without exposing secrets

## 7. Exit maintenance mode
- Disable maintenance mode only after database import, cron setup, and smoke checks pass
