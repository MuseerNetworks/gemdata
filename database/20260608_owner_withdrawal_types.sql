-- Adds separate owner profit withdrawal and capital return handling.

SET @owner_withdrawal_type_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'owner_withdrawals'
      AND COLUMN_NAME = 'withdrawal_type'
);

SET @owner_withdrawal_type_sql = IF(
    @owner_withdrawal_type_exists = 0,
    'ALTER TABLE owner_withdrawals ADD COLUMN withdrawal_type ENUM(''profit'',''capital_return'') NOT NULL DEFAULT ''profit'' AFTER reference',
    'SELECT 1'
);

PREPARE stmt FROM @owner_withdrawal_type_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @owner_withdrawal_type_index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'owner_withdrawals'
      AND INDEX_NAME = 'idx_owner_withdrawals_type_status'
);

SET @owner_withdrawal_type_index_sql = IF(
    @owner_withdrawal_type_index_exists = 0,
    'ALTER TABLE owner_withdrawals ADD INDEX idx_owner_withdrawals_type_status (withdrawal_type, status)',
    'SELECT 1'
);

PREPARE stmt FROM @owner_withdrawal_type_index_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE business_cash_ledger
    MODIFY entry_type ENUM(
        'user_funding_received',
        'provider_wallet_funded',
        'provider_wallet_recovered',
        'owner_capital_injected',
        'owner_withdrawal',
        'manual_adjustment'
    ) NOT NULL;

INSERT IGNORE INTO schema_migrations (migration_key)
VALUES ('20260608_owner_withdrawal_types');
