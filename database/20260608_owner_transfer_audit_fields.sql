-- Adds destination and transfer audit fields for owner transfers.

SET @owner_bank_name_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'owner_withdrawals'
      AND COLUMN_NAME = 'bank_name'
);

SET @owner_bank_name_sql = IF(
    @owner_bank_name_exists = 0,
    'ALTER TABLE owner_withdrawals ADD COLUMN bank_name VARCHAR(120) NULL AFTER status',
    'SELECT 1'
);

PREPARE stmt FROM @owner_bank_name_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @owner_account_number_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'owner_withdrawals'
      AND COLUMN_NAME = 'account_number'
);

SET @owner_account_number_sql = IF(
    @owner_account_number_exists = 0,
    'ALTER TABLE owner_withdrawals ADD COLUMN account_number VARCHAR(40) NULL AFTER bank_name',
    'SELECT 1'
);

PREPARE stmt FROM @owner_account_number_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @owner_account_name_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'owner_withdrawals'
      AND COLUMN_NAME = 'account_name'
);

SET @owner_account_name_sql = IF(
    @owner_account_name_exists = 0,
    'ALTER TABLE owner_withdrawals ADD COLUMN account_name VARCHAR(160) NULL AFTER account_number',
    'SELECT 1'
);

PREPARE stmt FROM @owner_account_name_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @owner_transfer_reference_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'owner_withdrawals'
      AND COLUMN_NAME = 'transfer_reference'
);

SET @owner_transfer_reference_sql = IF(
    @owner_transfer_reference_exists = 0,
    'ALTER TABLE owner_withdrawals ADD COLUMN transfer_reference VARCHAR(120) NULL AFTER account_name',
    'SELECT 1'
);

PREPARE stmt FROM @owner_transfer_reference_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @owner_transfer_reference_index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'owner_withdrawals'
      AND INDEX_NAME = 'idx_owner_withdrawals_transfer_reference'
);

SET @owner_transfer_reference_index_sql = IF(
    @owner_transfer_reference_index_exists = 0,
    'ALTER TABLE owner_withdrawals ADD INDEX idx_owner_withdrawals_transfer_reference (transfer_reference)',
    'SELECT 1'
);

PREPARE stmt FROM @owner_transfer_reference_index_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (migration_key)
VALUES ('20260608_owner_transfer_audit_fields');
