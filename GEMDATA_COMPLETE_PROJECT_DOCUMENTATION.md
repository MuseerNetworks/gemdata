# GemData Complete Project Documentation

Generated from a read-only audit of `C:\xampp\htdocs\gemdata`.

This document explains what the GemData project currently does, how it is structured, which files and tables power each feature, what is complete, what is partial, what is missing, and what needs care before production use.

Sensitive values such as API keys, database passwords, webhook secrets, and generated API secrets are intentionally not included.

---

## 1. Executive Summary

GemData is a PHP/MySQL VTU and fintech operations platform for:

- User wallet funding and wallet-based purchases.
- Airtime, data, cable TV, electricity, exam PIN, bulk SMS, data card, and recharge card purchase flows.
- Smart User, Reseller, and API User dashboards.
- Admin management for users, transactions, wallets, services, providers, reports, roles, alerts, security, upgrades, withdrawals, and API users.
- Multi-provider routing scaffolding with priority, manual, cheapest, cheapest plus health weighting, fallback, provider health logs, provider attempts, and circuit-breaker fields.
- XixaPay virtual/funding account storage and display.
- API access with API keys, API secrets, rate limiting, IP whitelist support, usage records, request logs, and API purchase endpoints.

### Current Completion Status

| Area | Status | Notes |
|---|---|---|
| Core bootstrap/service container | Complete | Centralized in `includes/bootstrap.php`. |
| User authentication | Mostly complete | Login, registration, password reset, session auth, logout, login logs. |
| Admin authentication/RBAC | Mostly complete | Roles, permissions, invites, admin login logs, permission checks. |
| Wallet ledger safety | Strong foundation | Uses row locking, idempotency keys, before/after balances. |
| User dashboards | Mostly complete | Role-aware Smart, Reseller, API dashboard behavior exists. |
| Dedicated purchase pages | Mostly complete | Reusable form foundation in `includes/user-page-components.php`. |
| Provider routing | Partially implemented | Router, settings, attempts, health scoring exist, but depends on provider mappings and real integrations. |
| VTU provider integrations | Partial | Mock and Albani adapter exist; other providers are configured but not proven fully live. |
| XixaPay funding account flow | Partial | Account storage/display exists; webhook endpoint is currently logging-only. |
| Admin operations dashboard | Partial to strong scaffold | Many pages exist; some are polished, some still use older dark `surface-card` styles. |
| Transaction command center | Partial | Filters, provider attempts/logs, retry/refund/force-success controls exist. Needs full QA. |
| Finance/reconciliation | Partial | Wallet adjustment logs, provider balance logs, refund views, reconciliation queues exist. |
| Security/fraud center | Partial | Fraud events, duplicate/velocity/API abuse views exist. Advanced device/risk intelligence is not implemented. |
| API platform | Partial | API auth, key management, rate limits, request/usage logs exist. Billing and webhooks are scaffold-level. |
| CMS/marketing tools | Partial | Tables and admin forms exist; frontend integration appears minimal. |
| Reports/analytics | Partial | Revenue, profit, providers, top users, failures, refunds, API usage, queue readiness exist. Needs heavier pagination/export hardening. |

---

## 2. Project Architecture

### Folder Structure

| Path | Purpose |
|---|---|
| `admin/` | Admin web pages and admin actions. |
| `api/` | Public API endpoints and payment/webhook endpoints. |
| `assets/` | CSS, JavaScript, images, manifests, frontend assets. |
| `classes/` | OOP services, providers, auth, wallets, transaction, reporting, API logic. |
| `cron/` | Scheduled scripts such as pending transaction processing and maintenance tasks. |
| `database/` | Main schema and additive migration files. |
| `docs/` | Project/API documentation pages. |
| `includes/` | Bootstrap, config, helpers, auth helpers, views/layouts/components. |
| `storage/` | Runtime storage such as logs/cache if configured. |
| `user/` | User-facing pages, dashboards, purchase pages, account pages. |

### Important Entry Points

| File | Purpose |
|---|---|
| `index.php` | Public landing page. |
| `includes/bootstrap.php` | Main app bootstrap, service registration, session setup, config loading, security headers. |
| `includes/config.php` | Default app, DB, provider, payment, mail, PWA, and security configuration. |
| `includes/config.local.php` | Local override if present. Should not be used for production. |
| `.env` / `.env.example` | Environment variable source/reference. Secrets must be masked and never committed publicly. |
| `user/dashboard.php` | Main user dashboard. |
| `admin/dashboard.php` | Main admin dashboard. |
| `api/*.php` | API and webhook endpoints. |

### Bootstrap Flow

`includes/bootstrap.php` performs the main application setup:

1. Loads helpers and logo helpers.
2. Loads configuration from `includes/config.php`, optional private config, and local config in non-production.
3. Sets timezone and error display rules.
4. Enforces HTTPS in production.
5. Configures secure session cookie parameters and starts the session.
6. Sends security headers.
7. Registers an autoloader for `GemData\Classes`.
8. Instantiates and registers shared services in the app container:
   - `Database`
   - `SessionAuth`
   - `Wallet`
   - `PaymentGatewayService`
   - `XixaPay`
   - `TransactionService`
   - `ProviderManager`
   - `ProviderRouter`
   - `ProviderPlanService`
   - `PricingService`
   - `FraudService`
   - `ApiAuth`
   - `ApiHandler`
   - `ReportService`
   - `AdminOpsService`
   - `DashboardController`
   - `UpgradeRequestService`
   - Other supporting services.
9. Runs production safety checks.
10. Includes shared helpers from `includes/db.php`, `includes/auth.php`, `includes/api_auth.php`, and `includes/view.php`.

### Routing Model

GemData is currently a traditional PHP page app:

- User pages are direct files under `user/`.
- Admin pages are direct files under `admin/`.
- API endpoints are direct files under `api/`.
- Shared routing helpers such as `base_url()` and `current_page_key()` are used for links and active navigation.

There is no central MVC router yet.

---

## 3. Authentication System

### User Authentication

Main files/classes:

| File/Class | Purpose |
|---|---|
| `user/login.php` | User login page and POST handling. |
| `user/register.php` | User registration page and POST handling. |
| `user/forgot-password.php` | Starts password reset flow. |
| `user/reset-password.php` | Completes password reset. |
| `user/logout.php` | Logs user out. |
| `classes/SessionAuth.php` | Core user/admin login, session, logout, throttling, and current identity logic. |
| `classes/UserSecurityService.php` | Password reset token and user security helper logic. |

`SessionAuth`:

- Verifies passwords with `password_verify`.
- Regenerates session IDs on login.
- Stores user/admin identity in session.
- Updates login timestamps.
- Writes login logs.
- Checks active/inactive status.
- Destroys session and session cookie on logout.

### Admin Authentication

Main files/classes:

| File/Class | Purpose |
|---|---|
| `admin/login.php` | Admin login page. |
| `admin/logout.php` | Admin logout endpoint. |
| `admin/register.php` | Admin registration/first admin style flow if enabled. |
| `admin/change-password.php` | Admin password change flow. |
| `admin/invites.php` | Admin invite management. |
| `admin/accept-invite.php` | Invite acceptance. |
| `classes/AdminService.php` | Admin roles/invites/admin account helpers. |
| `classes/RoleMiddleware.php` | Permission access helpers. |
| `classes/SessionAuth.php` | Admin login, current admin, password confirmation. |

### Permissions

Admin pages call helpers such as:

- `require_permission('dashboard.view')`
- `require_permission('users.view')`
- `require_permission('transactions.view')`
- `require_permission('wallet.manage')`
- `require_permission('providers.manage')`
- `require_permission('roles.manage')`

Permissions are backed by:

- `admin_roles`
- `admin_permissions`
- `role_permissions`
- `admins.role_id`

### CSRF

Forms use:

- `csrf_token()`
- hidden `csrf_token`
- `verify_csrf()`

This is used heavily across admin and user POST actions.

### Current Authentication Gaps/Risks

| Item | Status |
|---|---|
| Password hashing | Implemented with PHP password hashing. |
| Session regeneration | Implemented on login. |
| Login throttling | Implemented through login log tables. |
| Admin password confirmation | Used for sensitive actions. |
| Admin 2FA | Not implemented yet. |
| Device fingerprinting | Not implemented. |
| Centralized policy audit | Partial. Permissions are present but should be tested page by page. |

---

## 4. User Dashboard System

### Main Files

| File/Class | Purpose |
|---|---|
| `user/dashboard.php` | Main user dashboard markup and role sections. |
| `classes/DashboardController.php` | Prepares dashboard data for wallet, funding account, services, transactions, stats, reseller/API summaries. |
| `classes/UserRoleManager.php` | Resolves Smart, Reseller, and API roles. |
| `includes/view.php` | User sidebar, topbar, bottom nav, icons, layout shell. |
| `includes/user-page-components.php` | Shared service purchase page components. |

### Role Detection

`UserRoleManager::roleFor()` returns:

- `smart`
- `reseller`
- `api`

It checks:

- `users.user_type`
- `users.is_api_user`
- `users.tier`

Role labels:

- Smart User
- Reseller
- API User

### Smart User Dashboard

Smart users see a simplified fintech dashboard:

- Wallet balance.
- Funding account panel.
- Quick services.
- Recent transactions.
- Upgrade banner to Reseller.
- Referrals/support/settings links.

Smart users should not see API tools.

### Reseller Dashboard

Reseller users receive business-oriented additions:

- Reseller identity badge.
- Business tools sections.
- Bulk purchase links.
- Customers/pricing/reports.
- Commission balance and commission/rate summary.
- Upgrade/request API access prompt.

Related files:

- `user/commission.php`
- `user/customers.php`
- `user/pricing.php`
- `classes/Commission.php`
- `classes/CommissionWallet.php`

### API User Dashboard

API users see developer/automation features:

- API key preview.
- API center links.
- Request logs/API logs.
- Webhooks/billing/docs links.
- API usage summary based on API-channel transactions.

Related files:

- `user/api-center.php`
- `user/api-dashboard.php`
- `user/api-docs.php`
- `user/api-keys.php`
- `user/api-logs.php`
- `user/request-logs.php`
- `user/webhooks.php`
- `user/billing.php`

### Funding Account Display

Dashboard pulls funding account data from:

- `DashboardController::dataFor()`
- `XixaPay::getForUser()`
- `user_funding_accounts`

Displayed account data:

- Bank name.
- Account number.
- Account name.
- Copy account number.
- Copy full details.

Important current behavior:

- Dashboard display does not require visible BVN/NIN entry.
- `XixaPay::ensureStaticAccountForUser()` still expects BVN/NIN internally if called directly, so the older provider-generation method still exists in code.
- Funding account availability depends on records in `user_funding_accounts`.

### Dark Mode

Dark mode support exists in shared UI assets and shell behavior, but full coverage needs verification across all pages:

- Smart dashboard.
- Reseller dashboard.
- API dashboard.
- Service pages.
- Admin pages.
- Auth pages.

Status: Partially implemented, needs visual QA.

---

## 5. Admin Dashboard System

Admin pages are under `admin/`. The admin shell and nav are generated by `includes/view.php`.

### Admin Overview Dashboard

File: `admin/dashboard.php`

Purpose:

- Executive operations overview.
- Wallet liability.
- Revenue/profit.
- Transaction status counts.
- Provider health.
- Pending queue.
- Recent failures.
- Top users.
- Alerts and trend chart.

Backend classes:

- `ReportService`
- `AdminOpsService`
- `SettingsService`

Tables used:

- `users`
- `wallets`
- `transactions`
- `wallet_transactions`
- `provider_accounts`
- `provider_balance_logs`
- `activity_logs`

Actions:

- Opens transaction queue.
- Opens provider manager.
- Opens reports.

Limitations:

- Some metrics are direct SQL summaries and not cached.
- Chart relies on CDN Chart.js.

### Users

File: `admin/users.php`

Purpose:

- Search, filter, view, and manage users.
- Save/load admin views.
- Bulk user actions.
- Toggle API access.
- Generate API credentials.
- Activate/deactivate users.
- Set tier.
- Create password reset tokens.

Permissions:

- `users.view`
- `users.manage` for sensitive actions.

Tables:

- `users`
- `wallets`
- `api_users`
- `api_keys`
- `commission_logs`
- `admin_saved_views`
- `activity_logs`

Sensitive actions:

- Deactivate user requires admin password.
- Toggle API requires admin password.
- Generate API credentials requires admin password.
- Reset password requires admin password.

Limitations:

- API secret is shown only in flash after generation, which is correct, but must be handled carefully by admins.
- UI still has some older dark styling.

### User Details

File: `admin/user-detail.php`

Purpose:

- Detailed view for one user.
- Usually links to wallet, transactions, status, API, and account details.

Needs verification:

- Exact actions and permission checks should be browser-tested.

### Transactions

File: `admin/transactions.php`

Purpose:

- Transaction command center.
- Filters by query, status, channel, service, provider, tier, failure code, provider attempt status, response time, date.
- Saved admin views.
- CSV export.
- Provider attempts and provider API logs if tables exist.
- Retry, cancellation/override, manual refund, force success.

Backend:

- `TransactionService`
- `AdminOpsService`

Tables:

- `transactions`
- `transaction_events`
- `provider_transaction_attempts`
- `provider_api_logs`
- `users`
- `services`
- `provider_accounts`
- `admin_saved_views`
- `wallet_transactions`
- `refund_logs`

Actions:

| Action | Requirement | Behavior |
|---|---|---|
| Save/load/delete view | Admin session | Saves filters in `admin_saved_views`. |
| Bulk acknowledge | `transactions.manage` | Adds transaction review events. |
| Retry | `transactions.manage` + admin password | Requeues eligible transaction. |
| Override/cancel | `transactions.manage` + admin password | Calls `TransactionService::overrideStatus()`. |
| Manual refund | `transactions.manage` + admin password | Calls `TransactionService::manualRefund()`. |
| Force success | Super Admin + admin password | Calls `TransactionService::forceSuccess()` with no wallet mutation. |

Limitations/Risks:

- Force success is powerful and must stay Super Admin only.
- Some future statuses are displayed but schema compatibility depends on migrations.
- Raw provider log display must remain redacted.

### Services

Files:

- `admin/services.php`
- `admin/service-control.php`

Purpose:

- Manage services, networks, prices, provider plan mappings, or availability depending on page.
- Enable/disable services.
- Put services into maintenance with a message.

Tables:

- `services`
- `service_networks`
- `service_prices`
- `user_custom_prices`
- `provider_service_plans`

Permissions:

- `services.manage`

Limitations:

- Plan/catalog coverage depends on seeded provider/service data.
- Some service admin UI may still need consistency polish.

### Providers

File: `admin/providers.php`

Purpose:

- Add/update provider accounts.
- Set provider code/name/driver/status.
- Set priority order.
- Store credential reference.
- Set base URL.
- Set supported services.
- Set low-balance threshold.
- Set minimum success rate.
- Set failure threshold.
- Set health score.
- Enable fallback.
- Enable cheapest routing.
- Enable sandbox mode.
- Enable auto-disable.
- Test provider connection.
- Activate/inactivate/maintenance/archive provider.
- Reset circuit breaker.
- Configure routing settings by service or global default.

Backend:

- `ProviderManager`
- `ProviderRouter`
- `ActivityLogger`

Tables:

- `provider_accounts`
- `routing_settings`
- `provider_balance_logs`
- `provider_transaction_attempts`
- `provider_health_logs`

Important:

- Provider archive is a status, not a hard delete.
- Provider credentials are referenced/masked rather than exposed.
- Users do not see provider choices.

Limitations:

- Live provider routing depends on correct provider mappings in `provider_service_plans`.
- Provider balance refresh is manual/logged unless adapter supports automated balance checks.

### Wallet / Finance

File: `admin/wallet.php`

Purpose:

- Finance operations page.
- User wallet selection.
- Manual credit/debit.
- Provider balance tracking.
- Wallet adjustment logs.
- Refund logs.
- Funding logs.
- Provider balance logs.
- Provider expenses.
- Reconciliation queue.

Backend:

- `Wallet`
- `ProviderManager`
- `ActivityLogger`

Tables:

- `users`
- `wallets`
- `wallet_transactions`
- `wallet_adjustment_logs`
- `refund_logs`
- `wallet_funding_requests`
- `provider_balance_logs`
- `provider_expense_logs`
- `transactions`

Sensitive actions:

- Manual wallet adjustment requires admin password, CSRF, positive amount, reason, and idempotency key.
- Wallet mutation uses `Wallet::credit()` or `Wallet::debit()`.

Limitations:

- Reconciliation is surfaced, but full automated settlement reconciliation is still partial.

### Withdrawals

File: `admin/withdrawals.php`

Purpose:

- Manage withdrawal requests, likely reseller commission withdrawals.

Backend:

- `WithdrawalService`

Tables:

- `withdrawal_requests`
- `commission_wallets`
- `commission_wallet_transactions`

Needs verification:

- Exact approve/reject behavior should be tested in browser.

### Reports

File: `admin/reports.php`

Purpose:

- Daily and monthly revenue/profit.
- Provider performance.
- Top users.
- Top services.
- Failure breakdown.
- Refund report.
- User growth.
- API usage.
- Provider response times.
- Queue readiness.
- CSV export.

Backend:

- `ReportService`

Tables:

- `transactions`
- `wallet_transactions`
- `users`
- `services`
- `provider_transaction_attempts`
- `api_usage_records`
- `webhook_dead_letters`
- `activity_logs`

Limitations:

- Report queries are mostly direct summaries.
- Heavy reports should be paginated/lazy-loaded more deeply for production scale.

### Roles and Invites

Files:

- `admin/roles.php`
- `admin/invites.php`
- `admin/accept-invite.php`

Purpose:

- Assign admin roles.
- View permission matrix.
- Invite new admins.

Backend:

- `AdminService`

Tables:

- `admins`
- `admin_roles`
- `admin_permissions`
- `role_permissions`
- `admin_invites`

Limitations:

- Role assignment currently appears direct. More guardrails may be needed to prevent accidental loss of Super Admin access.

### Upgrade Requests

File: `admin/upgrades.php`

Purpose:

- Review Smart User to Reseller and Reseller to API User requests.
- Approve, reject, or request more info.

Backend:

- `UpgradeRequestService`

Tables:

- `upgrade_requests`
- `users`
- `reseller_profiles`
- `api_user_profiles`
- `api_users`
- `commission_wallets`

Actions:

- Approve updates user role/tier and creates related profile/wallet/API records.
- Reject changes request status.
- Needs info changes request status.

### API Users

File: `admin/api-users.php`

Purpose:

- Activate API access.
- Deactivate API access.
- Revoke API keys.
- Regenerate API key/secret.
- Update rate limits/monthly limits/billing status.
- Add IP whitelist.
- Save webhook config.
- View request logs, usage metering, webhook configs.

Backend:

- Direct database access plus `ActivityLogger`.

Tables:

- `users`
- `api_users`
- `api_keys`
- `api_request_logs`
- `api_usage_records`
- `api_webhook_configs`
- `api_ip_whitelists`
- `wallets`
- `transactions`

Limitations:

- Billing is mostly status/records scaffold; not full invoicing.
- Webhook config storage exists; delivery/retry worker is not fully implemented.

### Security Center

File: `admin/security.php`

Purpose:

- Fraud event review.
- Duplicate transaction patterns.
- Velocity checks.
- API abuse monitor.
- Webhook failure monitor.
- Admin activity log viewer.
- Suspend users from fraud events.

Backend:

- `FraudService`
- `ActivityLogger`
- direct DB queries.

Tables:

- `fraud_events`
- `transactions`
- `api_rate_limits`
- `api_keys`
- `api_users`
- `users`
- `webhook_dead_letters`
- `webhook_events`
- `activity_logs`

Limitations:

- No advanced device fingerprinting.
- Fraud checks are rules-based and basic.
- Suspend action requires reason and CSRF, but admin password confirmation may be advisable.

### Alerts / Notifications / CMS

Files:

- `admin/alerts.php`
- `admin/notifications.php`

Purpose:

- Broadcasts.
- Announcements.
- Homepage banners.
- Promo codes.
- Campaign drafts.
- Referral settings.
- User notices.
- Fraud event summaries.

Tables:

- `broadcasts`
- `announcements`
- `homepage_banners`
- `promo_codes`
- `campaign_drafts`
- `referral_settings`
- `user_notices`
- `fraud_events`

Limitations:

- CMS backend exists, but landing page/frontend consumption should be verified.
- Campaign delivery workers are not fully implemented.

### Settings

File: `admin/settings.php`

Purpose:

- Manage system settings such as maintenance or platform settings.

Backend:

- `SettingsService`
- `MaintenanceService`

Tables:

- `system_settings`

Needs verification:

- Exact setting keys and permission boundaries.

---

## 6. Wallet and Payments

### Wallet Model

Main class: `classes/Wallet.php`

Tables:

- `wallets`
- `wallet_transactions`
- `wallet_adjustment_logs`
- `refund_logs`

Wallet safety features:

- Ensures a wallet row exists per user.
- Uses database transactions.
- Locks wallet row with `FOR UPDATE`.
- Stores balance before and after.
- Supports idempotency keys.
- Prevents debit if insufficient balance.
- Records source type/source ID/meta/reason.

Methods:

| Method | Purpose |
|---|---|
| `ensure()` | Create/load wallet row. |
| `balance()` | Get current balance. |
| `credit()` | Add funds safely. |
| `debit()` | Remove funds safely. |
| `refund()` | Credit refund safely. |

### Funding Requests

Class: `PaymentGatewayService`

Tables:

- `wallet_funding_requests`
- `user_funding_accounts`
- `wallet_transactions`

Modes:

- In production, wallet funding is intended to use dedicated transfer accounts.
- Non-production/mock funding request flow can create funding requests and verify mock callbacks.

Important current status:

- `api/payment-callback.php` is disabled with HTTP 410.
- `api/xixapay.php` currently logs incoming XixaPay webhook payloads to `xixapay_test_log.txt` and returns success.
- `PaymentGatewayService::reconcileIncomingFunding()` contains logic to credit wallets from bank transfer webhooks, but the live webhook endpoint is not yet wired to call it.

### XixaPay Virtual/Funding Account

Class: `XixaPay`

Table:

- `user_funding_accounts`

Capabilities:

- Get funding account for a user.
- Create/upsert XixaPay static account record.
- Store account number, bank, account name, status, error, metadata.
- Check XixaPay config.

Important limitation:

- `ensureStaticAccountForUser()` still requires BVN/NIN inputs if called. The visible dashboard flow no longer asks for BVN/NIN, but backend account creation still needs a verified automated creation strategy or pre-created records.

---

## 7. VTU Services

### Shared Purchase Architecture

Main files/classes:

| File/Class | Purpose |
|---|---|
| `includes/user-page-components.php` | Renders service purchase pages/forms. |
| `user/service-action.php` | Web purchase AJAX/POST endpoint. |
| `classes/TransactionService.php` | Validates, creates, debits, processes purchase transactions. |
| `classes/ProviderManager.php` | Sends purchase to selected provider. |
| `classes/ProviderRouter.php` | Chooses provider based on routing settings. |
| `classes/PricingService.php` | Resolves service amount/pricing. |
| `classes/ProviderPlanService.php` | Loads provider plan catalog. |

### Web Purchase Flow

1. User opens a service page such as `user/buy-data.php`.
2. The page uses `render_service_shortcut_page()` and `render_purchase_form()`.
3. User submits to `user/service-action.php`.
4. `service-action.php`:
   - Requires authenticated user.
   - Verifies CSRF.
   - Verifies transaction PIN if the `transaction_pin_hash` column exists.
   - Applies service-specific checks.
   - Calls `TransactionService::purchase()`.
5. `TransactionService::purchase()`:
   - Validates service and network.
   - Validates amount.
   - Normalizes recipient/phone.
   - Checks idempotency.
   - Resolves pricing.
   - Runs fraud checks.
   - Creates a pending transaction.
   - Debits wallet with idempotency.
   - Logs transaction event.
   - Returns transaction info.
6. Pending transactions are processed by provider logic/cron.

### Airtime

User fields:

- Network.
- Phone number.
- Amount.
- Security PIN.

Files:

- `user/buy-airtime.php`
- `user/service-action.php`
- `api/buy-airtime.php`

Tables:

- `services`
- `service_networks`
- `service_prices`
- `transactions`
- `wallet_transactions`
- `provider_service_plans`

### Data

User fields:

- Network.
- Data plan.
- Recipient phone.
- Security PIN.

Behavior:

- Data plans are pulled through provider plan catalog when available.
- Plan price is mapped into amount.

Files:

- `user/buy-data.php`
- `api/buy-data.php`
- `ProviderPlanService`

### Cable TV

User fields:

- Provider: DSTV/GOTV/StarTimes fallback options.
- IUC/Smartcard number.
- Verify button.
- Subscription package.
- Security PIN.

Files:

- `user/cable-tv.php`
- `user/service-verify.php`
- `user/service-action.php`
- `api/cable-tv.php`

Current verification status:

- `user/service-verify.php` returns a clean fallback message: verification temporarily unavailable.
- No real customer-name validation is currently implemented.
- Purchase can continue with user confirmation fallback.

### Electricity

User fields:

- Distribution company.
- Meter type.
- Meter number.
- Amount.
- Security PIN.

Files:

- `user/electricity.php`
- `api/pay-electricity.php`

Current limitation:

- Real meter validation needs verification. The structure exists, but no fully confirmed validation adapter was observed in this audit.

### Exam PIN

User fields:

- Provider dropdown: WAEC, NECO, NABTEB, JAMB fallback.
- Package/exam type dropdown.
- Quantity.
- Security PIN.

Files:

- `user/exam-pin.php`
- `api/exam-pin.php`

Current limitation:

- Actual purchase requires active provider/admin plans for real package/amount.

### Bulk SMS

User fields:

- Sender ID.
- Recipients.
- Message.
- Amount.
- Security PIN.

Files:

- `user/bulk-sms.php`
- `api/bulk-sms.php`

### Recharge Card and Data Card

Files:

- `user/recharge-card.php`
- `user/data-card.php`
- `api/recharge-card.php`
- `api/data-card.php`

Status:

- Form/page endpoints exist.
- Actual provider fulfillment depends on service/provider mappings.

---

## 8. Provider System

### Main Classes

| Class | Purpose |
|---|---|
| `ProviderManager` | Provider list, purchasing, balance logs, status updates, circuit-breaker updates. |
| `ProviderRouter` | Selects provider based on routing mode, health, cost, balance, and fallback. |
| `ProviderPlanService` | Service plan/provider catalog. |
| `ProviderInterface` | Common adapter contract. |
| `MockVtuProvider` | Mock/test provider. |
| `Providers/Adapters/*` | Provider-specific adapters such as Albani. |

### Provider Tables

- `provider_accounts`
- `provider_service_plans`
- `routing_settings`
- `provider_transaction_attempts`
- `provider_api_logs`
- `provider_health_logs`
- `provider_balance_logs`

### Routing Modes

Implemented in `ProviderRouter`:

- `manual`
- `priority`
- `cheapest`
- `cheapest_health`

Default:

- Priority routing with fallback when no custom setting is found.

### Provider Safeguards

Current fields/features include:

- Provider status: active/inactive/maintenance/archived.
- Priority order.
- Fallback support.
- Cheapest routing toggle.
- Sandbox mode.
- Failure threshold.
- Circuit-breaker status.
- Circuit opened/cooldown timestamps.
- Current balance.
- Balance refreshed timestamp.
- Health score.
- Minimum success rate.
- Low balance threshold.

### Circuit Breaker

`ProviderManager` tracks provider outcomes and can:

- Increment failure counts.
- Open circuit after repeated failures.
- Recover expired circuit breakers.
- Reset circuit from admin page.

Status: Implemented as backend scaffolding; needs full provider-failure QA.

### Provider Complexity Visibility

Users do not select providers and should not see provider names, fallback attempts, routing modes, or provider logs. This is correctly designed as admin/backend-only.

---

## 9. Multi-Provider Routing System

### What Exists

- Provider accounts.
- Provider plan mappings.
- Routing settings by service/global.
- Manual/priority/cheapest/cheapest-health routing.
- Provider attempts table.
- API logs table.
- Health logs table.
- Balance logs.
- Circuit-breaker columns and controls.
- Fallback loop in `ProviderManager::purchase()`.

### What Is Partial

- Real provider adapters beyond mock/Albani need verification.
- Provider plan code mapping must be populated for each service.
- Provider cost price and selling price must be kept accurate for cheapest routing.
- Provider balance refresh is not fully automated for every provider.
- Queue-worker compatibility exists in schema/logs, but a robust worker system is not fully present.

### What Is Needed For Full Smart Routing

- Verified provider credentials and adapters.
- Seeded provider plan mappings for all services.
- Automated provider balance refresh.
- Automated health checks.
- Provider response normalization.
- Real worker/queue orchestration for retries and dead letters.
- End-to-end tests for fallback, refund, and idempotency.

---

## 10. Transaction System

### Main Class

`classes/TransactionService.php`

### Tables

- `transactions`
- `transaction_events`
- `wallet_transactions`
- `provider_transaction_attempts`
- `provider_api_logs`
- `fraud_events`
- `refund_logs`

### Lifecycle

Typical lifecycle:

1. Request received.
2. Payload validated.
3. Idempotency key checked.
4. Price resolved.
5. Fraud checks performed.
6. Transaction inserted as `pending`.
7. Wallet debited.
8. Transaction event logged.
9. Provider processing runs.
10. Provider result updates transaction:
    - `successful`
    - `pending`
    - `failed`
    - `refunded`
11. Refund is applied when required.

### Statuses

Observed/used statuses include:

- `pending`
- `processing`
- `successful`
- `failed`
- `refunded`
- `reversed`
- `disputed`

Schema compatibility for every future status should be confirmed before production use.

### Duplicate Prevention

Used mechanisms:

- Idempotency key for API/web.
- Wallet transaction idempotency.
- Fraud fingerprint for duplicate patterns.
- Pending/processing locks in transaction processing.

### Admin Controls

From `admin/transactions.php`:

- Retry failed/pending eligible transactions.
- Manual refund.
- Force success.
- Override/cancel.
- View transaction events.
- View provider attempts/logs when tables exist.
- Export CSV.

### Risks

- Force success must remain tightly restricted.
- Refund and retry paths need repeated idempotency tests.
- Provider fallback failure needs end-to-end test with real adapters.

---

## 11. API User System

### API Endpoints

| Endpoint | Purpose |
|---|---|
| `api/balance.php` | API wallet balance. |
| `api/transactions.php` | API transaction history. |
| `api/buy-airtime.php` | API airtime purchase. |
| `api/buy-data.php` | API data purchase. |
| `api/cable-tv.php` | API cable purchase. |
| `api/pay-electricity.php` | API electricity purchase. |
| `api/exam-pin.php` | API exam PIN purchase. |
| `api/bulk-sms.php` | API bulk SMS purchase. |
| `api/data-card.php` | API data card purchase. |
| `api/recharge-card.php` | API recharge card purchase. |
| `api/service-status.php` | Service status endpoint. |
| `api/notifications.php` | Notifications endpoint. |
| `api/create-xixapay-account.php` | XixaPay account endpoint. Needs careful review because visible dashboard no longer asks BVN/NIN. |

### API Auth

Class: `ApiAuth`

Flow:

1. Reads `X-API-KEY` and `X-API-SECRET` headers or POST fields.
2. Loads matching `api_keys`, `api_users`, and `users`.
3. Ensures API key/user/account are active.
4. Verifies secret with `password_verify`.
5. Applies IP whitelist if configured.
6. Applies rate limiter.
7. Updates `last_used_at`.
8. Logs request if `api_request_logs` exists.
9. Records usage if `api_usage_records` exists.

### API Handler

Class: `ApiHandler`

Methods:

- `handlePurchase($serviceSlug)`
- `balance()`
- `transactions()`

API purchases call the same `TransactionService::purchase()` as web purchases, with channel `api`.

### API Admin Controls

File: `admin/api-users.php`

Admin can:

- Activate/deactivate API users.
- Revoke/regenerate keys.
- Set rate limit.
- Set monthly limit.
- Set billing status.
- Add IP whitelist.
- Save webhook URL.
- View logs and usage.

### API Limitations

- API billing is scaffold-level.
- Webhook delivery/retry to API users is not fully implemented.
- API docs should be verified against actual payloads.
- API secrets are only shown once on generation, which is correct.

---

## 12. Reseller System

### Role

Resellers are detected through:

- `users.user_type = reseller`
- or `users.tier = RESELLER/AGENT`

### Features

- Reseller dashboard sections.
- Commission wallet.
- Commission rates.
- Reports/commission page.
- Customers page.
- Pricing page.
- Bulk SMS/bulk purchase navigation.
- Request API access.

Main files/classes:

- `user/commission.php`
- `user/customers.php`
- `user/pricing.php`
- `classes/Commission.php`
- `classes/CommissionWallet.php`
- `classes/UserRoleManager.php`

Tables:

- `commission_wallets`
- `commission_wallet_transactions`
- `commissions`
- `commission_logs`
- `withdrawal_requests`
- `upgrade_requests`

Missing/Partial:

- Saved customers/beneficiaries appear more UI/scaffold-oriented unless a dedicated customer table exists beyond recent-recipient logic.
- Reseller profit analytics are partial.
- Bulk airtime/data modules need verification.

---

## 13. Upgrade Request System

Main class: `UpgradeRequestService`

Files:

- `user/upgrade-request.php`
- `admin/upgrades.php`

Supported paths:

- Smart User to Reseller.
- Reseller to API User.

User request flow:

1. User opens upgrade request page.
2. System determines next role.
3. User submits required details.
4. `upgrade_requests` row is created.

Admin flow:

1. Admin opens `admin/upgrades.php`.
2. Admin filters pending/approved/rejected/needs info.
3. Admin approves/rejects/requests more info.
4. Approval updates `users.user_type`, `users.tier`, and related profile/API/commission wallet rows.

Tables:

- `upgrade_requests`
- `users`
- `reseller_profiles`
- `api_user_profiles`
- `api_users`
- `commission_wallets`

Limitations:

- Business verification/KYC checks are not deep.
- Approval should be audited and tested with all role transitions.

---

## 14. Reports and Analytics

Main class: `ReportService`

File: `admin/reports.php`

Reports currently include:

- Overview totals.
- Daily revenue/profit series.
- Monthly summary.
- Provider performance.
- Top users.
- Activity logs.
- Top services.
- Failed breakdown.
- Refund report.
- User growth.
- API usage.
- Provider response times.
- Queue readiness.

CSV export:

- Supported for selected datasets.

Limitations:

- Heavy datasets are mostly limited by `LIMIT` but not fully paginated in all sections.
- Exports may become expensive with large data.
- Real-time metrics are not implemented.
- Queue monitoring is readiness/scaffold, not full worker observability.

---

## 15. Security Review

### Implemented Protections

- PDO prepared statements.
- CSRF tokens for forms.
- Password hashing and verification.
- Session regeneration on login.
- Secure session cookie configuration.
- Security headers in bootstrap.
- Login attempt throttling.
- Admin permission checks.
- Admin password confirmation for many sensitive actions.
- Wallet row locking.
- Wallet idempotency keys.
- API secret hashing.
- API key masking in UI.
- Provider credential references/masking.
- Activity logs.
- Fraud events.
- Rate limiting for API keys.

### Partial/Missing Protections

| Area | Status |
|---|---|
| Admin 2FA | Not implemented. |
| Device fingerprinting | Not implemented. |
| Webhook signature verification | Needs completion for live XixaPay and API webhooks. |
| Webhook dead-letter processing | Schema exists; full worker not implemented. |
| Provider API log redaction | Must be verified for every adapter. |
| Force-success safeguards | Implemented at UI/service level, but should be tested thoroughly. |
| Secrets management | Config supports private overrides; production deployment must verify no secrets leak. |
| Rate limiting beyond API | Partial. User/admin login throttling exists. |

### Biggest Security Risks

1. `api/xixapay.php` is logging-only and does not verify/process live funding webhooks yet.
2. Provider routing/fallback/refund paths need end-to-end production-like QA.
3. Some admin operations pages are powerful and should be tested with every admin role.
4. No admin 2FA yet.
5. CMS/campaign features exist but delivery/approval guardrails are still basic.

---

## 16. Database Documentation

Main schema: `database/database.sql`

Additive migrations:

- `20260426_admin_fintech_upgrade.sql`
- `20260426_admin_hardening_indexes.sql`
- `20260426_admin_saved_views.sql`
- `20260426_user_security_upgrade.sql`
- `20260503_wallet_funding_requests.sql`
- `20260505_financial_hardening_async_pending.sql`
- `20260507_paystack_dedicated_accounts.sql`
- `20260511_albani_provider_integration.sql`
- `20260513_phase1_missing_tables.sql`
- `20260513_zenithpay_multi_account.sql`
- `20260515_reseller_api_refactor.sql`
- `20260518_xixapay_virtual_accounts.sql`
- `20260519_role_dashboard_v2.sql`
- `20260520_phase2_provider_safeguards.sql`
- `20260520_phase4_finance_reconciliation.sql`
- `20260520_phases5_8_admin_operations.sql`

### Important Tables

| Table | Purpose | Important Columns/Notes |
|---|---|---|
| `schema_migrations` | Tracks migrations. | migration/version metadata. |
| `users` | User accounts. | role/tier/status/API flags, profile fields. |
| `admins` | Admin accounts. | role_id, password hash, status. |
| `admin_roles` | Admin role definitions. | super_admin, finance, support, operations, security roles. |
| `admin_permissions` | Permission definitions. | permission_key, label. |
| `role_permissions` | Role-permission mapping. | role_id, permission_id. |
| `admin_invites` | Admin invitation records. | token hash, role, status. |
| `admin_login_logs` | Admin login attempts. | email, IP, success, time. |
| `user_login_logs` | User login attempts. | email, IP, success, time. |
| `user_password_reset_tokens` | Password reset tokens. | token hash, expiry, used status. |
| `wallets` | Current wallet balance per user. | user_id, balance. |
| `wallet_transactions` | Wallet ledger. | type, amount, before/after balance, idempotency key, source. |
| `wallet_adjustment_logs` | Admin wallet changes. | admin_id, user_id, amount, before/after, reason. |
| `refund_logs` | Refund audit records. | transaction/user/admin/reason/status. |
| `wallet_funding_requests` | Wallet funding attempts. | reference, provider, provider_reference, amount, status, idempotency. |
| `user_funding_accounts` | XixaPay/virtual accounts. | account number, bank, account name, provider, status. |
| `services` | Service catalog. | slug, name, enabled, category, maintenance message. |
| `service_networks` | Networks/providers per service. | service_id, network_code, network_name, enabled. |
| `service_prices` | Tier pricing. | service, tier, price/min/max as designed. |
| `user_custom_prices` | Per-user price overrides. | user_id, service_id, price/rate. |
| `provider_accounts` | Provider config and safeguards. | code, driver, status, priority, credentials key, health, circuit status, balance. |
| `provider_service_plans` | Provider plan mapping. | service slug, network, local plan, provider code, amount, status. |
| `routing_settings` | Routing policy. | service scope, mode, manual provider, fallback, weights. |
| `provider_transaction_attempts` | Provider attempt logs. | transaction_id, provider_code, status, response time, error. |
| `provider_api_logs` | Provider raw/API logs. | request/response payloads, redaction required. |
| `provider_health_logs` | Health snapshots. | status, response time, balance, errors. |
| `provider_balance_logs` | Balance tracking. | provider, amount, source, notes. |
| `transactions` | Purchase records. | reference, user, service, amount, status, provider code/ref, pricing, idempotency. |
| `transaction_events` | Transaction timeline. | transaction_id, event type, actor, notes, meta. |
| `fraud_events` | Fraud/risk events. | user, transaction, event type, risk, review status. |
| `api_users` | API account records. | user_id, status, limits, billing status. |
| `api_keys` | API key records. | api_key, secret_hash, status, last_used_at. |
| `api_rate_limits` | API rate limiting windows. | api_key_id, window, request_count. |
| `api_request_logs` | API request logs. | user/key, method, endpoint, IP, status, error. |
| `api_ip_whitelists` | API IP allow-list. | api_user_id, IP, status. |
| `api_usage_records` | API usage metering. | api_user_id, date, request count, volume. |
| `api_billing_records` | API billing records. | Scaffold for billing. |
| `api_webhook_configs` | API user webhook configs. | URL, secret preview, status. |
| `commissions` | Commission rate config. | service/user rate percent. |
| `commission_logs` | Commission earning logs. | user/transaction/amount. |
| `commission_wallets` | Reseller commission balances. | user_id, balance. |
| `commission_wallet_transactions` | Commission wallet ledger. | type, amount, references. |
| `withdrawal_requests` | Commission/withdrawal requests. | user, amount, status, review. |
| `upgrade_requests` | User role upgrade requests. | from_type, to_type, status, business/API details. |
| `reseller_profiles` | Reseller profile data. | business fields. |
| `api_user_profiles` | API profile data. | developer/business fields. |
| `notifications` | User notifications. | title, message, type, read status. |
| `broadcasts` | Admin broadcasts. | title, message, target scope, channel. |
| `webhook_events` | Webhook event log. | source, event key, status. |
| `webhook_dead_letters` | Failed webhook/dead-letter records. | status, retry count, next retry, error. |
| `announcements` | CMS announcement records. | title, body, audience, status. |
| `homepage_banners` | CMS homepage banner records. | title, subtitle, CTA, status. |
| `promo_codes` | Promo code records. | code, type, value, status. |
| `campaign_drafts` | Campaign draft records. | title, channel, audience, schedule. |
| `referral_settings` | Referral config values. | key/value. |
| `user_notices` | User-targeted notices. | user_id nullable, title, message. |
| `activity_logs` | Audit/activity trail. | actor type/id, action, description, metadata. |
| `admin_saved_views` | Saved filters. | admin_id, page key, filters JSON. |
| `system_settings` | App/system settings. | setting key/value. |

---

## 17. File/Class Documentation

### Core Classes

| File/Class | Purpose |
|---|---|
| `classes/Database.php` | PDO wrapper, query/first/execute, transaction helpers, safe queries, schema checks. |
| `classes/SessionAuth.php` | User/admin auth, sessions, login logs, password confirmation. |
| `classes/Wallet.php` | Wallet balances and ledger mutations with idempotency and locks. |
| `classes/TransactionService.php` | Purchase validation, transaction creation, wallet debit/refund, provider processing, retry/refund/force success. |
| `classes/ProviderManager.php` | Provider account management, purchase attempts, provider health/balance/circuit breaker. |
| `classes/ProviderRouter.php` | Provider selection/routing modes. |
| `classes/ProviderPlanService.php` | Provider service plan catalog. |
| `classes/PricingService.php` | User/tier pricing resolution. |
| `classes/FraudService.php` | Duplicate and rapid request fraud event detection. |
| `classes/ApiAuth.php` | API credential verification, rate limiting, request logging, usage logging. |
| `classes/ApiHandler.php` | API purchases, balance, transaction history. |
| `classes/DashboardController.php` | User dashboard data composition. |
| `classes/UserRoleManager.php` | User role detection/labels/access level. |
| `classes/UpgradeRequestService.php` | Upgrade request creation and admin approval/rejection. |
| `classes/ReportService.php` | Admin reports and analytics queries. |
| `classes/AdminOpsService.php` | Saved admin views, bulk actions, dashboard operations summary. |
| `classes/PaymentGatewayService.php` | Funding request and webhook reconciliation logic. |
| `classes/XixaPay.php` | XixaPay static account generation/storage. |
| `classes/Commission.php` | Reseller commission logic. |
| `classes/CommissionWallet.php` | Commission wallet operations. |
| `classes/WithdrawalService.php` | Withdrawal request/review logic. |
| `classes/ActivityLogger.php` | Activity/audit logging. |
| `classes/SettingsService.php` | System settings storage/access. |
| `classes/MaintenanceService.php` | Maintenance mode behavior. |

### Important Includes

| File | Purpose |
|---|---|
| `includes/bootstrap.php` | Main app initialization. |
| `includes/config.php` | Default config. |
| `includes/helpers.php` | General helpers. |
| `includes/auth.php` | Auth helper functions such as `require_user`/`require_permission`. |
| `includes/view.php` | Layout shell, nav, icons, header/footer. |
| `includes/user-page-components.php` | Shared user service purchase page components. |
| `includes/db.php` | DB helper shortcuts. |
| `includes/api_auth.php` | API auth helper wiring. |

### User Pages

| File | Purpose |
|---|---|
| `user/dashboard.php` | Main role dashboard. |
| `user/fund-wallet.php` | Wallet funding page. |
| `user/transactions.php` | User transaction history. |
| `user/buy-airtime.php` | Airtime purchase page. |
| `user/buy-data.php` | Data purchase page. |
| `user/cable-tv.php` | Cable TV purchase page. |
| `user/electricity.php` | Electricity purchase page. |
| `user/exam-pin.php` | Exam PIN purchase page. |
| `user/bulk-sms.php` | Bulk SMS purchase page. |
| `user/data-card.php` | Data card purchase page. |
| `user/recharge-card.php` | Recharge card purchase page. |
| `user/service-action.php` | Shared web purchase endpoint. |
| `user/service-verify.php` | Cable verification/fallback endpoint. |
| `user/upgrade-request.php` | Role upgrade request page. |
| `user/referrals.php` | Referral page. |
| `user/support.php` | Support page. |
| `user/settings.php` / `user/profile.php` | User settings/profile. |
| `user/commission.php` | Reseller reports/commission page. |
| `user/pricing.php` | Reseller pricing page. |
| `user/customers.php` | Reseller customers page. |
| `user/api-*.php` | API user pages. |

### Admin Pages

See Section 5 for page-by-page purpose and actions.

---

## 18. Admin Operation Manual

### Login

1. Open `admin/login.php`.
2. Enter admin email and password.
3. The system checks admin status, password, and login throttling.
4. If login succeeds, admin is redirected to the admin dashboard.

### Manage Users

1. Open `admin/users.php`.
2. Use filters/search to find users.
3. Use saved views for repeated filters.
4. Actions available depending on permission:
   - Activate/deactivate user.
   - Set tier.
   - Toggle API access.
   - Generate API credentials.
   - Create password reset token.
   - Bulk activate/deactivate/set tier.
5. Dangerous actions require admin password.

### View User Details

1. Open a user from `admin/users.php` or another linked admin page.
2. Review profile, wallet, transaction, API, and account records where available.
3. Use linked wallet/transaction pages for changes rather than editing database directly.

### Credit/Debit Wallet

1. Open `admin/wallet.php`.
2. Select a user wallet.
3. Choose credit or debit.
4. Enter amount.
5. Enter reason.
6. Confirm admin password.
7. Submit.
8. System uses `Wallet` service and writes ledger/audit records.

Danger:

- Never adjust wallet without a reason.
- Never bypass `Wallet` service.

### Check Transactions

1. Open `admin/transactions.php`.
2. Filter by reference, user, service, status, provider, date, etc.
3. Expand/review events/provider attempts/logs if shown.
4. Export CSV when needed.

### Refund Failed Transactions

1. Open `admin/transactions.php`.
2. Find eligible failed/pending transaction.
3. Use manual refund action.
4. Enter required reason and admin password.
5. Confirm.
6. Verify wallet transaction and transaction event are recorded.

Danger:

- Do not refund outside the provided admin action.
- Recheck if transaction is already refunded.

### Retry Transactions

1. Open failed/pending transaction.
2. Confirm it is not already refunded.
3. Use retry action.
4. Enter admin password.
5. System requeues/attempts via `TransactionService`.

### Force Success

Only Super Admin should do this.

1. Confirm provider delivered value manually.
2. Open transaction.
3. Use force success.
4. Enter admin password and reason.
5. Confirm risk warning.
6. System changes transaction status without wallet mutation.

Danger:

- Force success must not be used to credit/debit wallets.
- It must be reserved for verified external fulfillment.

### Manage Services and Plans

1. Open `admin/services.php` for service/price/plan management.
2. Open `admin/service-control.php` for availability.
3. Enable/disable services as needed.
4. Use maintenance message for temporary downtime.
5. Configure plans/network/prices/provider mappings.

### Manage Providers

1. Open `admin/providers.php`.
2. Add or edit provider account.
3. Set driver, status, priority, credentials key, supported services.
4. Configure fallback, cheapest routing, sandbox mode, success threshold, circuit breaker.
5. Test connection.
6. Use maintenance/archive instead of deleting.
7. Configure routing mode globally or per service.

Danger:

- Do not expose provider credentials.
- Do not activate live provider without plan mapping and test transactions.

### Monitor Provider Health

1. Review provider health cards on `admin/dashboard.php`.
2. Open `admin/providers.php` for detailed status.
3. Check provider balance logs in `admin/wallet.php`.
4. Reset circuit breaker only after confirming provider is healthy.

### Manage Upgrade Requests

1. Open `admin/upgrades.php`.
2. Filter pending requests.
3. Review submitted business/API details.
4. Approve, reject, or request more information.
5. Approval updates user role and related records.

### Manage API Users

1. Open `admin/api-users.php`.
2. Activate eligible user.
3. Regenerate key/secret if required.
4. Copy one-time secret immediately.
5. Set rate/monthly limits.
6. Add IP whitelist where required.
7. Save webhook URL.
8. Monitor request logs and usage records.

Danger:

- Raw API secret cannot be recovered after generation.
- Do not display or store raw secrets outside secure admin handoff.

### Check Reports

1. Open `admin/reports.php`.
2. Review revenue, profit, providers, users, failures, refunds, API usage, queue readiness.
3. Export CSV only for necessary datasets.
4. For large data, prefer date filters/pagination improvements before production scale.

### Manage Roles/Permissions

1. Open `admin/roles.php`.
2. Review permission matrix.
3. Assign roles to admins carefully.
4. Use `admin/invites.php` to invite new admins.

Danger:

- Avoid removing all Super Admin access.
- Finance/security/provider permissions should be assigned only to trusted admins.

### Handle Fraud/Suspicious Activity

1. Open `admin/security.php`.
2. Review fraud events.
3. Check duplicate and velocity patterns.
4. Review API abuse and webhook failures.
5. Mark event reviewing/confirmed/dismissed with reason.
6. Suspend user when necessary.

Danger:

- Suspension should be reasoned and auditable.
- More advanced fraud checks are not implemented yet.

### Use Settings

1. Open `admin/settings.php`.
2. Update settings only if you understand their impact.
3. Use maintenance mode for operational downtime.

---

## 19. Current Problems, Bugs, and Technical Debt

### Broken or Incomplete Areas

| Area | Issue |
|---|---|
| XixaPay webhook | `api/xixapay.php` logs only; live credit reconciliation is not wired. |
| Cable TV verification | Real customer validation not implemented; fallback message only. |
| Electricity verification | Needs verification; real meter validation not confirmed. |
| Multi-provider routing | Core scaffolding exists but depends on mappings, adapters, credentials, and testing. |
| Provider adapters | Some configured providers may not have complete live adapters. |
| CMS/marketing | Admin creation exists; frontend consumption/delivery is partial. |
| API webhooks | Config exists; delivery/retry/dead-letter worker not fully implemented. |
| Reports scaling | Many direct queries; full pagination/lazy-loading not everywhere. |
| Dark mode | Partially implemented; needs page-by-page visual QA. |
| Admin UI consistency | Some pages still use older dark `surface-card` and Bootstrap-like classes. |
| Queue workers | Pending transaction cron exists, but enterprise queue monitoring/worker model is partial. |

### Backend Risks

- Powerful admin actions require strict permission testing.
- Wallet safety depends on all mutations going through `Wallet`.
- Provider failure/refund/idempotency paths must be stress-tested.
- Webhook verification and replay protection need completion before live funding automation.
- Local config defaults are unsafe for production unless private production config is enforced.

### UI/UX Risks

- Some admin pages mix newer GemData light UI with older dark admin card styles.
- Tables may overflow on mobile on dense admin pages.
- Modal/drawer z-index should be tested browser-side.
- Some pages use CDN scripts, which may affect offline or intranet installs.

---

## 20. Recommended Roadmap

### Immediate Fixes

1. Wire live XixaPay webhook verification and reconciliation safely.
2. Add webhook signature verification and replay protection.
3. Run full QA on wallet debit/refund/idempotency.
4. Verify every admin role permission.
5. Seed/validate provider service plans for every live service.
6. Confirm cable/electricity real validation support.
7. Finish admin UI consistency on pages still using older dark `surface-card` styling.

### Short-Term Improvements

1. Add automated tests for `Wallet`, `TransactionService`, `ProviderRouter`, and `ApiAuth`.
2. Add an admin 2FA flow.
3. Add provider balance auto-refresh jobs.
4. Improve API docs with exact payload examples.
5. Add better reseller saved customers/beneficiaries tables if missing.
6. Add stronger audit screens for wallet and transaction actions.

### Medium-Term Fintech Improvements

1. Implement a queue worker for transaction processing, provider retries, and webhook delivery.
2. Add dead-letter retry UI and worker.
3. Add reconciliation jobs for provider timeouts.
4. Add provider profitability dashboards using true cost price.
5. Expand fraud rules with IP/user-agent history.
6. Add role-specific approval workflows for finance/security/provider changes.

### Long-Term Scaling Improvements

1. Move to a central router/controller structure or framework.
2. Add service-level automated tests and CI.
3. Add centralized observability: logs, traces, metrics, alerts.
4. Introduce background job queue with retry/backoff.
5. Add full ledger accounting model for platform liability, provider expenses, revenue, refunds, and settlements.
6. Add multi-environment provider sandbox/live separation with strict deployment controls.

---

## 21. Final Notes

GemData currently has a substantial foundation: wallet safety, role-aware dashboards, admin RBAC, service purchases, API access, provider-routing scaffolding, finance/security/reporting pages, and CMS scaffolds. The most important next step is not adding more surface area, but hardening the live money/provider paths:

- Funding webhook verification.
- Provider purchase fulfillment.
- Refund/idempotency safety.
- Admin permission boundaries.
- Automated tests.
- Production-safe secrets/configuration.

Provider complexity is correctly designed to stay hidden from users. Admins should manage provider routing, health, fallback, and costs in the operations dashboard while users see only normal GemData services.
