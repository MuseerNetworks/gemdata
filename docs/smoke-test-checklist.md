# GemData Smoke Test Checklist

## Public entry
- `https://gemdata.com.ng/` loads without HTTP 500
- `https://gemdata.com.ng/offline.html` loads
- No broken `/gemdata` path assumptions appear in navigation

## Auth
- Admin login works
- User registration works
- User login works
- Forgot-password flow does not expose debug reset links in production

## Funding
- User wallet page shows dedicated transfer account state correctly
- No mock funding button appears in production
- The XixaPay logging webhook returns success without exposing secrets
- Webhook payload handling records operational review data under protected application logs, not public web-root files

## Transactions
- One transaction can be queued successfully
- `cron/process-pending.php` runs without fatal errors
- `cron/reconcile.php` runs without fatal errors
- Failed transactions can be reviewed safely from the admin panel

## Providers
- Provider health section loads
- Provider connection test returns a safe status message
- Disabled providers do not process live traffic
