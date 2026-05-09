# Migration Import Order

## Fresh database
1. Import `database/database.sql`

## Existing or partially migrated production database
1. Enable maintenance mode
2. Import `database/production-repair.sql`
3. Review `webhook_events`, `provider_accounts`, `transactions`, and `wallet_funding_requests`
4. Disable maintenance mode only after smoke tests pass

## Notes
- Do not import the old dated migration files into production going forward.
- `production-repair.sql` is designed to skip columns and indexes that already exist.
- Always back up the database first.
