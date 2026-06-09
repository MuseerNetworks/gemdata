-- Minimal GemData finance ledgers with provider wallet recovery.

CREATE TABLE IF NOT EXISTS owner_withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(80) NOT NULL UNIQUE,
    withdrawal_type ENUM('profit','capital_return') NOT NULL DEFAULT 'profit',
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('pending','approved','paid','rejected') NOT NULL DEFAULT 'pending',
    bank_name VARCHAR(120) NULL,
    account_number VARCHAR(40) NULL,
    account_name VARCHAR(160) NULL,
    transfer_reference VARCHAR(120) NULL,
    requested_by_admin_id INT NOT NULL,
    reviewed_by_admin_id INT NULL,
    paid_by_admin_id INT NULL,
    notes VARCHAR(255) NULL,
    rejection_reason VARCHAR(255) NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    paid_at DATETIME NULL,
    INDEX idx_owner_withdrawals_type_status (withdrawal_type, status),
    INDEX idx_owner_withdrawals_status_created (status, requested_at),
    INDEX idx_owner_withdrawals_transfer_reference (transfer_reference),
    CONSTRAINT fk_owner_withdrawals_requested_by FOREIGN KEY (requested_by_admin_id) REFERENCES admins(id) ON DELETE RESTRICT,
    CONSTRAINT fk_owner_withdrawals_reviewed_by FOREIGN KEY (reviewed_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL,
    CONSTRAINT fk_owner_withdrawals_paid_by FOREIGN KEY (paid_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS business_cash_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(80) NOT NULL UNIQUE,
    entry_type ENUM(
        'user_funding_received',
        'provider_wallet_funded',
        'provider_wallet_recovered',
        'owner_capital_injected',
        'owner_withdrawal',
        'manual_adjustment'
    ) NOT NULL,
    direction ENUM('in','out') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    source_table VARCHAR(80) NULL,
    source_id INT NULL,
    provider_account_id INT NULL,
    owner_withdrawal_id INT NULL,
    idempotency_key VARCHAR(120) NULL,
    notes VARCHAR(255) NULL,
    created_by_admin_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_business_cash_source (source_table, source_id, entry_type),
    UNIQUE KEY uniq_business_cash_idempotency (idempotency_key),
    INDEX idx_business_cash_type_created (entry_type, created_at),
    INDEX idx_business_cash_provider (provider_account_id),
    CONSTRAINT fk_business_cash_provider FOREIGN KEY (provider_account_id) REFERENCES provider_accounts(id) ON DELETE SET NULL,
    CONSTRAINT fk_business_cash_owner_withdrawal FOREIGN KEY (owner_withdrawal_id) REFERENCES owner_withdrawals(id) ON DELETE SET NULL,
    CONSTRAINT fk_business_cash_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS provider_wallet_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(80) NOT NULL UNIQUE,
    provider_account_id INT NOT NULL,
    provider_code VARCHAR(60) NOT NULL,
    entry_type ENUM(
        'funding',
        'transaction_cost',
        'recovery',
        'manual_adjustment'
    ) NOT NULL,
    direction ENUM('in','out') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    balance_before DECIMAL(12,2) NULL,
    balance_after DECIMAL(12,2) NULL,
    transaction_id INT NULL,
    business_cash_ledger_id INT NULL,
    idempotency_key VARCHAR(120) NULL,
    notes VARCHAR(255) NULL,
    created_by_admin_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_provider_wallet_transaction_cost (transaction_id, entry_type),
    UNIQUE KEY uniq_provider_wallet_idempotency (idempotency_key),
    INDEX idx_provider_wallet_provider_created (provider_account_id, created_at),
    INDEX idx_provider_wallet_type_created (entry_type, created_at),
    CONSTRAINT fk_provider_wallet_provider FOREIGN KEY (provider_account_id) REFERENCES provider_accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_provider_wallet_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
    CONSTRAINT fk_provider_wallet_business_cash FOREIGN KEY (business_cash_ledger_id) REFERENCES business_cash_ledger(id) ON DELETE SET NULL,
    CONSTRAINT fk_provider_wallet_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

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
VALUES ('20260608_minimal_finance_ledgers');
