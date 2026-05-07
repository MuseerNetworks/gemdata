# GemData Deployment Checklist

## Configuration
- Create `includes/config.local.php` for each environment.
- Set `app.environment` correctly.
- Replace local database credentials.
- Set a strong `webhooks.shared_secret`.
- Disable `mail.debug_display_reset_links` in production.
- Keep provider credentials only in the local override file.

## Payments and recovery
- Set the live payment gateway identifier in `payments.default_gateway`.
- Set `payments.paystack_secret_key` and, if needed, `payments.dva_preferred_bank`.
- Decide whether `payments.auto_assign_dedicated_account` should be enabled in that environment.
- Make the payment callback endpoint publicly reachable.
- Configure a real mail transport for password reset delivery.

## Operations
- Apply the latest SQL migrations before release.
- Schedule `cron/process-pending.php` to run continuously or at a short interval so pending purchases can finalize.
- Keep `cron/retry-failed.php` enabled only if automated retry policy is intended.
- Verify admin login, user login, wallet funding, and one service purchase.
- Register a new user and confirm the dedicated transfer account shows as assigned or pending on the wallet funding page.
- Check provider balances, webhook validation, and retry behavior.

## Safety
- Back up the database before deployment.
- Ensure PHP and webserver error logs are enabled.
- Keep cron and local-only tools inaccessible from public web routes.
