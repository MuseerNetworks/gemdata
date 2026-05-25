-- User wallet PIN security support.
-- Safe/idempotent: only adds users.transaction_pin_hash when missing.

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'transaction_pin_hash'
);

SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE users ADD COLUMN transaction_pin_hash VARCHAR(255) NULL AFTER password_hash',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (migration_key) VALUES ('20260525_user_wallet_pin_security');
