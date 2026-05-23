# GemData Reconciliation Operations

## Automated jobs
- `cron/process-pending.php` recovers stale processing locks and processes queued transactions.
- `cron/retry-failed.php` retries eligible failed transactions only when `auto_retry_enabled` is on.
- `cron/reconcile.php` repairs timeout-prone pending transactions and issues bounded automatic refunds when required.

## What gets reconciled
- Stuck `pending` transactions with stale `processing_started_at`
- Timeout-prone pending transactions older than the configured reconciliation window
- XixaPay webhook payloads logged for first-live review
- Partial funding states after XixaPay wallet crediting is enabled

## Admin review points
- `admin/transactions.php`
- `admin/providers.php`
- `admin/dashboard.php`

## Safety notes
- Reconciliation is idempotent and cron-safe.
- No long-running workers or daemons are required.
- Keep provider integrations disabled until each credential set has been tested in isolation.
