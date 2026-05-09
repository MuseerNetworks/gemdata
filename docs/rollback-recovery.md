# GemData Rollback and Recovery

## Immediate rollback
- Enable maintenance mode.
- Restore the previous database backup from cPanel/phpMyAdmin.
- Use cPanel Git Version Control to deploy the last known-good commit.
- Re-run the smoke checklist before disabling maintenance mode.

## Transaction recovery
- Run `cron/reconcile.php` from cPanel Cron Jobs or manually via the server cron schedule window.
- Review failed and timeout-reconciled records in `admin/transactions.php`.
- Review `webhook_events` for duplicate or failed webhook processing.
- Review `wallet_transactions` by `idempotency_key` before any manual wallet adjustment.

## Manual intervention rules
- Do not re-credit wallets manually until `wallet_transactions`, `transaction_events`, and `webhook_events` agree on the current state.
- Use admin wallet adjustments only when automated reconciliation cannot repair the record.
- Log every manual intervention through the admin UI so `activity_logs` captures the action.
