# GemData Deployment Checklist

Use this checklist before every production deployment. GemData handles wallets and fintech-style transaction flows, so do not skip financial, webhook, or admin-security checks.

## 1. Pre-Deploy Safety

- Confirm the target branch and commit are approved for deployment.
- Confirm there are no accidental commits of `.env`, `includes/config.local.php`, SQL dumps, logs, backups, screenshots, or real credentials.
- Confirm production private config exists outside the web root at `/home/<cpanel_user>/gemdata-config.php`.
- Confirm `includes/config.local.php` is absent on production.
- Confirm public diagnostics are deleted or blocked, including `bootstrap_probe.php` and `emergency-index.php`.
- Confirm the web server blocks direct access to `.env`, logs, backups, SQL dumps, private config, and hidden files.
- Back up the production database before deploying.
- Enable maintenance mode before schema changes or provider/payment changes.

## 2. Runtime Requirements

- PHP version is compatible with the app, preferably PHP 8.2 or 8.3.
- Required PHP extensions are enabled: `json`, `pdo_mysql`, and `curl`.
- `storage/logs` and `storage/cache` or the configured private storage paths are writable by PHP.
- Production config sets `app.environment` to `production`.
- Production config sets `app.force_https_in_production` to `true`.
- Production config sets `app.public_origin` to the HTTPS production origin.
- Production config sets `mail.debug_display_reset_links` to `false`.
- Production config sets `payments.auto_verify_mock_funding` to `false`.

## 3. Database And Migrations

- Back up the live database before importing or altering schema.
- Apply migrations in order.
- Confirm the latest security hardening migrations have been applied.
- Confirm fintech-critical tables exist for users, admins, wallets, transactions, wallet transactions, provider plans, API users, API keys, webhooks, reports, roles, alerts, withdrawals, and activity logs.
- Confirm webhook event IDs and transaction references have unique idempotency protection.
- Confirm wallet and commission tables cannot create duplicate credits or negative balances through concurrent requests.
- Do not run destructive commands such as `DROP`, `TRUNCATE`, or manual data deletion during deployment.

## 4. Security Controls

- Admin 2FA is enabled in production config.
- Every privileged admin has completed 2FA setup before go-live.
- Email verification is required before money movement.
- User and admin logout use POST with CSRF.
- Admin invites, withdrawals, refunds, provider changes, settings changes, and service controls require CSRF.
- Admin manual refund or force-success actions are restricted to the correct privileged role and leave an audit trail.
- API authentication uses headers only.
- API invalid credential attempts are throttled.
- API rate limiting is enforced atomically.
- Public API errors do not expose raw provider, PDO, stack, or internal exception messages.
- CSP does not allow `unsafe-inline` or `unsafe-eval`.

## 5. Payments And Webhooks

- XixaPay API key, API secret, business ID, bank codes, and webhook URL are configured only in private config.
- `webhooks.shared_secret` is a long production-only random secret.
- XixaPay webhook rejects non-POST requests.
- XixaPay webhook rejects missing or invalid signatures.
- XixaPay webhook rejects replayed timestamps or duplicate event IDs.
- XixaPay webhook writes operational logs outside public web root.
- Valid webhook credits are idempotent.
- Duplicate webhook delivery cannot double-credit a wallet.
- Failed webhook processing can be reconciled from database records and logs.

## 6. Providers

- All providers remain disabled until credentials and live test references are verified.
- Enable one provider at a time.
- Confirm each enabled provider has base URL, credential, timeout, retry, and sandbox settings reviewed.
- Confirm provider request references are unique and idempotent.
- Confirm retry behavior cannot create duplicate provider purchases.
- Confirm provider pending, failed, successful, and reversed statuses map cleanly into GemData transaction states.
- Confirm provider failures do not debit users permanently without reconciliation.

## 7. Cron And Workers

- Configure cron using the production PHP binary.
- Confirm pending transaction processing runs without fatal errors.
- Confirm retry processing runs without fatal errors.
- Confirm reconciliation runs without fatal errors.
- Confirm cron logs are stored outside public access or protected by server rules.
- Confirm stale pending transactions, failed provider requests, and reconciliation exceptions are monitored.

Example schedule:

```text
*/2 * * * * /usr/local/bin/php /home/<cpanel_user>/public_html/cron/process-pending.php
*/5 * * * * /usr/local/bin/php /home/<cpanel_user>/public_html/cron/retry-failed.php
*/10 * * * * /usr/local/bin/php /home/<cpanel_user>/public_html/cron/reconcile.php
```

## 8. Pre-Go-Live Smoke Tests

- Run PHP lint across all PHP files.
- Run static route and link scan.
- Open homepage.
- Open user login.
- Open user register.
- Open forgot-password.
- Open admin login.
- Open API docs.
- Confirm protected user pages redirect when unauthenticated.
- Confirm protected admin pages redirect when unauthenticated.
- Login as Smart User and load dashboard, wallet, purchases, transactions, support, settings, and logout.
- Login as Reseller and load reseller dashboard, pricing, commission, withdrawals, reports, wallet, transactions, and logout.
- Login as API User and load API center, API keys, API docs, webhooks, request logs, billing, wallet, transactions, and logout.
- Login as Admin and load dashboard, users, transactions, providers, services, service control, wallet, withdrawals, reports, roles, invites, alerts, notifications, settings, API users, upgrade requests, and logout.
- Login as Super Admin if available and confirm privileged controls remain protected.

## 9. Financial Acceptance Tests

- Test wallet funding with invalid webhook signature.
- Test wallet funding with replayed webhook timestamp.
- Test wallet funding with duplicate webhook event ID.
- Test one valid wallet credit and verify the ledger entry.
- Submit duplicate purchase requests and confirm only one debit/provider action succeeds.
- Test refund idempotency.
- Test commission calculation for Smart User, Reseller, and API User pricing paths.
- Test commission withdrawal duplicate submission protection.
- Confirm no operation can create a negative wallet or commission balance.

## 10. UI And Mobile Safety

- Confirm dashboard sidebar and topbar load.
- Confirm mobile navigation loads and does not overlap critical actions.
- Confirm dark mode toggle loads.
- Confirm tables remain usable on mobile.
- Confirm empty states are clean and do not expose debug data.
- Confirm service cards route to real pages or intentionally disabled states.

## 11. Go-Live

- Confirm backups completed successfully.
- Confirm maintenance mode is disabled only after smoke tests pass.
- Confirm provider balances and gateway dashboard state before opening traffic.
- Monitor PHP error logs, application logs, webhook logs, provider logs, and database health.
- Monitor wallet credits, duplicate references, failed webhooks, pending provider transactions, and manual admin actions.
- Keep a named rollback commit, database backup, and previous private config copy available.

## 12. Rollback

- Re-enable maintenance mode.
- Restore the previous deployed code.
- Restore the previous private config if config caused the issue.
- Do not restore an old database over a newer database without reconciling wallet and transaction changes first.
- Disable live providers if provider behavior caused the incident.
- Preserve logs and webhook payload records for investigation.

## Final Deployment Gate

Production is ready only when:

- Critical and high security findings are fixed.
- PHP lint passes.
- Route and link scans show no missing deployment routes.
- Authenticated smoke tests pass for every available role.
- Webhook signature, replay, duplicate, and valid-credit tests pass.
- Wallet debit, refund, commission, and withdrawal idempotency tests pass.
- Public diagnostics, logs, backups, SQL dumps, and private configs are not web-accessible.
- Admin 2FA and email verification gates are active.
