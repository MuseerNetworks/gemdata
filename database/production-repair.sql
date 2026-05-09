CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_key VARCHAR(120) NOT NULL UNIQUE,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

DROP PROCEDURE IF EXISTS add_column_if_missing;
DROP PROCEDURE IF EXISTS add_index_if_missing;

DELIMITER $$
CREATE PROCEDURE add_column_if_missing(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE add_index_if_missing(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND INDEX_NAME = p_index
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL add_column_if_missing('admins', 'role_id', 'INT NULL');
CALL add_column_if_missing('admins', 'two_factor_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL add_column_if_missing('admins', 'two_factor_secret', 'VARCHAR(255) NULL');
CALL add_column_if_missing('admins', 'invited_by_admin_id', 'INT NULL');

CALL add_column_if_missing('users', 'tier', 'ENUM(''USER'',''RESELLER'',''AGENT'',''API_RESELLER'') NOT NULL DEFAULT ''USER''');
CALL add_column_if_missing('users', 'referral_code', 'VARCHAR(60) NULL');
CALL add_column_if_missing('users', 'referred_by_user_id', 'INT NULL');
CALL add_column_if_missing('users', 'security_lock_until', 'DATETIME NULL');

CALL add_column_if_missing('transactions', 'idempotency_key', 'VARCHAR(120) NULL');
CALL add_column_if_missing('transactions', 'provider_account_id', 'INT NULL');
CALL add_column_if_missing('transactions', 'provider_code', 'VARCHAR(60) NULL');
CALL add_column_if_missing('transactions', 'selling_price', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00');
CALL add_column_if_missing('transactions', 'cost_price', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00');
CALL add_column_if_missing('transactions', 'profit_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00');
CALL add_column_if_missing('transactions', 'pricing_source', 'VARCHAR(40) NOT NULL DEFAULT ''legacy''');
CALL add_column_if_missing('transactions', 'retry_count', 'INT NOT NULL DEFAULT 0');
CALL add_column_if_missing('transactions', 'max_retry_count', 'INT NOT NULL DEFAULT 2');
CALL add_column_if_missing('transactions', 'is_retryable', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL add_column_if_missing('transactions', 'override_status', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL add_column_if_missing('transactions', 'override_reason', 'VARCHAR(255) NULL');
CALL add_column_if_missing('transactions', 'overridden_by_admin_id', 'INT NULL');
CALL add_column_if_missing('transactions', 'overridden_at', 'DATETIME NULL');
CALL add_column_if_missing('transactions', 'processing_started_at', 'DATETIME NULL');
CALL add_column_if_missing('transactions', 'processed_at', 'DATETIME NULL');
CALL add_column_if_missing('transactions', 'failure_code', 'VARCHAR(120) NULL');

CALL add_column_if_missing('wallet_transactions', 'source_type', 'ENUM(''purchase'',''funding'',''admin_adjustment'',''refund'',''legacy'') NOT NULL DEFAULT ''legacy''');
CALL add_column_if_missing('wallet_transactions', 'source_id', 'INT NULL');
CALL add_column_if_missing('wallet_transactions', 'idempotency_key', 'VARCHAR(120) NULL');
CALL add_column_if_missing('wallet_transactions', 'reason_code', 'VARCHAR(120) NULL');

CALL add_column_if_missing('wallet_funding_requests', 'idempotency_key', 'VARCHAR(120) NULL');
CALL add_column_if_missing('wallet_funding_requests', 'callback_session_token_hash', 'VARCHAR(255) NULL');

CALL add_index_if_missing('transactions', 'idx_transactions_pending_processor', 'INDEX idx_transactions_pending_processor (status, processing_started_at, id)');
CALL add_index_if_missing('transactions', 'idx_transactions_reference_status', 'INDEX idx_transactions_reference_status (reference, status)');
CALL add_index_if_missing('wallet_transactions', 'idx_wallet_transactions_source', 'INDEX idx_wallet_transactions_source (source_type, source_id)');
CALL add_index_if_missing('wallet_funding_requests', 'idx_wallet_funding_reference_status', 'INDEX idx_wallet_funding_reference_status (reference, status)');

INSERT IGNORE INTO schema_migrations (migration_key) VALUES
    ('production_repair_import');
