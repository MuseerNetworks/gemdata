-- KatPay owner transfer payout metadata.

SET @owner_bank_code_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'owner_withdrawals'
      AND COLUMN_NAME = 'bank_code'
);

SET @owner_bank_code_sql = IF(
    @owner_bank_code_exists = 0,
    'ALTER TABLE owner_withdrawals ADD COLUMN bank_code VARCHAR(60) NULL AFTER bank_name',
    'SELECT 1'
);

PREPARE stmt FROM @owner_bank_code_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @owner_payout_provider_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'owner_withdrawals'
      AND COLUMN_NAME = 'payout_provider'
);

SET @owner_payout_provider_sql = IF(
    @owner_payout_provider_exists = 0,
    'ALTER TABLE owner_withdrawals ADD COLUMN payout_provider VARCHAR(40) NULL AFTER transfer_reference',
    'SELECT 1'
);

PREPARE stmt FROM @owner_payout_provider_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @owner_payout_status_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'owner_withdrawals'
      AND COLUMN_NAME = 'payout_status'
);

SET @owner_payout_status_sql = IF(
    @owner_payout_status_exists = 0,
    'ALTER TABLE owner_withdrawals ADD COLUMN payout_status ENUM(''not_requested'',''processing'',''successful'',''failed'') NOT NULL DEFAULT ''not_requested'' AFTER payout_provider',
    'SELECT 1'
);

PREPARE stmt FROM @owner_payout_status_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @owner_payout_reference_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'owner_withdrawals'
      AND COLUMN_NAME = 'payout_reference'
);

SET @owner_payout_reference_sql = IF(
    @owner_payout_reference_exists = 0,
    'ALTER TABLE owner_withdrawals ADD COLUMN payout_reference VARCHAR(120) NULL AFTER payout_status',
    'SELECT 1'
);

PREPARE stmt FROM @owner_payout_reference_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @owner_payout_response_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'owner_withdrawals'
      AND COLUMN_NAME = 'payout_response_json'
);

SET @owner_payout_response_sql = IF(
    @owner_payout_response_exists = 0,
    'ALTER TABLE owner_withdrawals ADD COLUMN payout_response_json TEXT NULL AFTER payout_reference',
    'SELECT 1'
);

PREPARE stmt FROM @owner_payout_response_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @owner_payout_requested_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'owner_withdrawals'
      AND COLUMN_NAME = 'payout_requested_at'
);

SET @owner_payout_requested_sql = IF(
    @owner_payout_requested_exists = 0,
    'ALTER TABLE owner_withdrawals ADD COLUMN payout_requested_at DATETIME NULL AFTER payout_response_json',
    'SELECT 1'
);

PREPARE stmt FROM @owner_payout_requested_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @owner_payout_confirmed_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'owner_withdrawals'
      AND COLUMN_NAME = 'payout_confirmed_at'
);

SET @owner_payout_confirmed_sql = IF(
    @owner_payout_confirmed_exists = 0,
    'ALTER TABLE owner_withdrawals ADD COLUMN payout_confirmed_at DATETIME NULL AFTER payout_requested_at',
    'SELECT 1'
);

PREPARE stmt FROM @owner_payout_confirmed_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @owner_payout_failure_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'owner_withdrawals'
      AND COLUMN_NAME = 'payout_failure_reason'
);

SET @owner_payout_failure_sql = IF(
    @owner_payout_failure_exists = 0,
    'ALTER TABLE owner_withdrawals ADD COLUMN payout_failure_reason VARCHAR(255) NULL AFTER payout_confirmed_at',
    'SELECT 1'
);

PREPARE stmt FROM @owner_payout_failure_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @owner_payout_reference_index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'owner_withdrawals'
      AND INDEX_NAME = 'idx_owner_withdrawals_payout_reference'
);

SET @owner_payout_reference_index_sql = IF(
    @owner_payout_reference_index_exists = 0,
    'ALTER TABLE owner_withdrawals ADD INDEX idx_owner_withdrawals_payout_reference (payout_provider, payout_reference)',
    'SELECT 1'
);

PREPARE stmt FROM @owner_payout_reference_index_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (migration_key)
VALUES ('20260609_katpay_owner_payouts');
