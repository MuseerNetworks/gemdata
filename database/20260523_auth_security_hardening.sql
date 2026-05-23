-- GemData admin 2FA and email verification hardening.
-- Safe, non-destructive migration.

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'email_verified_at'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL AFTER status',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'provider_transaction_attempts'
      AND INDEX_NAME = 'uniq_provider_request_reference'
);
SET @sql := IF(
    @idx_exists = 0,
    'ALTER TABLE provider_transaction_attempts ADD UNIQUE KEY uniq_provider_request_reference (provider_account_id, request_reference)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (migration_key) VALUES ('20260523_auth_security_hardening');
