-- Add idempotency guards for manual finance ledger actions.

SET @business_cash_idempotency_col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'business_cash_ledger'
      AND COLUMN_NAME = 'idempotency_key'
);

SET @business_cash_idempotency_col_sql = IF(
    @business_cash_idempotency_col_exists = 0,
    'ALTER TABLE business_cash_ledger ADD COLUMN idempotency_key VARCHAR(120) NULL AFTER owner_withdrawal_id',
    'SELECT 1'
);

PREPARE stmt FROM @business_cash_idempotency_col_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @business_cash_idempotency_idx_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'business_cash_ledger'
      AND INDEX_NAME = 'uniq_business_cash_idempotency'
);

SET @business_cash_idempotency_idx_sql = IF(
    @business_cash_idempotency_idx_exists = 0,
    'ALTER TABLE business_cash_ledger ADD UNIQUE KEY uniq_business_cash_idempotency (idempotency_key)',
    'SELECT 1'
);

PREPARE stmt FROM @business_cash_idempotency_idx_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @provider_wallet_idempotency_col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'provider_wallet_ledger'
      AND COLUMN_NAME = 'idempotency_key'
);

SET @provider_wallet_idempotency_col_sql = IF(
    @provider_wallet_idempotency_col_exists = 0,
    'ALTER TABLE provider_wallet_ledger ADD COLUMN idempotency_key VARCHAR(120) NULL AFTER business_cash_ledger_id',
    'SELECT 1'
);

PREPARE stmt FROM @provider_wallet_idempotency_col_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @provider_wallet_idempotency_idx_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'provider_wallet_ledger'
      AND INDEX_NAME = 'uniq_provider_wallet_idempotency'
);

SET @provider_wallet_idempotency_idx_sql = IF(
    @provider_wallet_idempotency_idx_exists = 0,
    'ALTER TABLE provider_wallet_ledger ADD UNIQUE KEY uniq_provider_wallet_idempotency (idempotency_key)',
    'SELECT 1'
);

PREPARE stmt FROM @provider_wallet_idempotency_idx_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (migration_key)
VALUES ('20260608_finance_ledger_idempotency');
