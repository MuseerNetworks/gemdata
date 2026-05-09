# GemData Reconciliation Operations

## Automated jobs
- `cron/process-pending.php` recovers stale processing locks and processes queued transactions.
- `cron/retry-failed.php` retries eligible failed transactions only when `auto_retry_enabled` is on.
- `cron/reconcile.php` repairs timeout-prone pending transactions and issues bounded automatic refunds when required.

## What gets reconciled
- Stuck `pending` transactions with stale `processing_started_at`
- Timeout-prone pending transactions older than the configured reconciliation window
- Duplicate Paystack webhook events
- Partial funding states where webhook intake was recorded but wallet credit has not completed

## Admin review points
- `admin/transactions.php`
- `admin/providers.php`
- `admin/dashboard.php`

## Safety notes
- Reconciliation is idempotent and cron-safe.
- No long-running workers or daemons are required.
- Keep provider integrations disabled until each credential set has been tested in isolation.
