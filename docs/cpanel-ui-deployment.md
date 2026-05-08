# GemData cPanel UI Deployment

## 1. Upload Files
- Open `cPanel > File Manager`.
- Go to `public_html`.
- Upload the contents of this project so these paths exist in `public_html`:
  - `index.php`
  - `admin/`
  - `api/`
  - `assets/`
  - `classes/`
  - `cron/`
  - `database/`
  - `docs/`
  - `includes/`
  - `user/`
  - `offline.html`
  - `manifest.json`
  - `service-worker.js`
- Do not place production secrets inside `public_html/includes/config.local.php`.

## 2. Create Private Production Config
- In `File Manager`, go to your home directory: `/home/<cpanel_user>/`.
- Create a new file named `gemdata-config.php`.
- Copy the contents of [cpanel-production-config.example.php](/C:/xampp/htdocs/gemdata/docs/cpanel-production-config.example.php) into that file.
- Replace these values:
  - `password` in `db`
  - `webhooks.shared_secret`
  - `payments.paystack_secret_key`
  - SMTP credentials if you are configuring real mail delivery
- Keep:
  - `app.environment = 'production'`
  - `app.base_url = ''`
  - `app.public_origin = 'https://gemdata.com.ng'`
  - `payments.default_gateway = 'paystack'`
  - `payments.auto_assign_dedicated_account = true`

## 3. Set File Permissions
- In `File Manager`, confirm:
  - directories are `755`
  - public PHP files are `644`
  - `/home/<cpanel_user>/gemdata-config.php` is `600` if cPanel permits it

## 4. Create Database and User
- Open `cPanel > MySQL Databases`.
- Confirm or create:
  - database: `yyrigitd_gemdata`
  - user: `yyrigitd_admin`
- Add `yyrigitd_admin` to `yyrigitd_gemdata`.
- Grant `ALL PRIVILEGES`.

## 5. Import Database
- Open `cPanel > phpMyAdmin`.
- Select `yyrigitd_gemdata`.
- Import files in this order:
  1. `database/database.sql`
  2. `database/20260426_admin_fintech_upgrade.sql`
  3. `database/20260426_admin_hardening_indexes.sql`
  4. `database/20260426_admin_saved_views.sql`
  5. `database/20260426_user_security_upgrade.sql`
  6. `database/20260503_wallet_funding_requests.sql`
  7. `database/20260505_financial_hardening_async_pending.sql`
  8. `database/20260507_paystack_dedicated_accounts.sql`

## 6. Add Cron Job
- Open `cPanel > Cron Jobs`.
- Add this cron only:

```cron
*/2 * * * * /usr/local/bin/php /home/<cpanel_user>/public_html/cron/process-pending.php
```

- Do not add `cron/retry-failed.php` yet.

## 7. Verify Production
- Visit:
  - `https://gemdata.com.ng/`
  - `https://gemdata.com.ng/user/login.php`
  - `https://gemdata.com.ng/admin/login.php`
  - `https://gemdata.com.ng/user/fund-wallet.php`
- Confirm:
  - no `HTTP 500`
  - no `root@localhost` DB error in logs
  - links do not include `/gemdata`
  - `https://gemdata.com.ng/offline.html` loads
  - wallet funding uses Paystack
  - dedicated account assignment works after Paystack setup is valid

## 8. Logging and Safety
- Enable PHP error logging in cPanel if available.
- Check `Errors` or raw PHP logs after first load.
- Ensure `/home/<cpanel_user>/gemdata-config.php` is outside `public_html` and not web-accessible.
