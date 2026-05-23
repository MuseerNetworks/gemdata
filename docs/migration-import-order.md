# Migration Import Order

## Fresh database
1. Import `database/database.sql`
2. Import `database/20260515_reseller_api_refactor.sql`
3. Import `database/20260515_seed_commission_and_flags.sql`
4. Import `database/20260518_xixapay_virtual_accounts.sql`
5. Import `database/20260519_role_dashboard_v2.sql`
6. Import `database/20260520_phase2_provider_safeguards.sql`
7. Import `database/20260520_phase4_finance_reconciliation.sql`
8. Import `database/20260520_phases5_8_admin_operations.sql`
9. Import `database/20260522_commission_upgrade_flow.sql`
10. Import `database/20260522_predeployment_security_hardening.sql`
11. Import `database/20260523_auth_security_hardening.sql`

## Existing or partially migrated production database
1. Enable maintenance mode
2. Import `database/production-repair.sql`
3. Review `webhook_events`, `provider_accounts`, `transactions`, and `wallet_funding_requests`
4. Disable maintenance mode only after smoke tests pass

## Notes
- Do not import older pre-20260515 dated migrations into a fresh production database.
- `20260520_phase2_provider_safeguards.sql` and `20260520_phases5_8_admin_operations.sql` avoid `ADD COLUMN IF NOT EXISTS` for cPanel MySQL/MariaDB compatibility.
- `production-repair.sql` is designed to skip columns and indexes that already exist.
- Always back up the database first.
