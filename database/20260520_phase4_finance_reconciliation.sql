-- Phase 4: Finance and reconciliation additive tables.
-- Safe to run multiple times. No destructive schema changes.

CREATE TABLE IF NOT EXISTS wallet_adjustment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wallet_transaction_id INT NULL,
    admin_id INT NOT NULL,
    user_id INT NOT NULL,
    adjustment_type ENUM('credit','debit','refund') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    balance_before DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance_after DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    reason VARCHAR(255) NOT NULL,
    idempotency_key VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wallet_adjustment_logs_user_created (user_id, created_at),
    INDEX idx_wallet_adjustment_logs_admin_created (admin_id, created_at),
    UNIQUE KEY uniq_wallet_adjustment_logs_idempotency (idempotency_key),
    CONSTRAINT fk_wallet_adjustment_logs_wallet_tx FOREIGN KEY (wallet_transaction_id) REFERENCES wallet_transactions(id) ON DELETE SET NULL,
    CONSTRAINT fk_wallet_adjustment_logs_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    CONSTRAINT fk_wallet_adjustment_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS refund_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NULL,
    wallet_transaction_id INT NULL,
    admin_id INT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    status ENUM('pending','completed','failed') NOT NULL DEFAULT 'completed',
    idempotency_key VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_refund_logs_user_created (user_id, created_at),
    INDEX idx_refund_logs_transaction (transaction_id),
    UNIQUE KEY uniq_refund_logs_idempotency (idempotency_key),
    CONSTRAINT fk_refund_logs_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
    CONSTRAINT fk_refund_logs_wallet_tx FOREIGN KEY (wallet_transaction_id) REFERENCES wallet_transactions(id) ON DELETE SET NULL,
    CONSTRAINT fk_refund_logs_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
    CONSTRAINT fk_refund_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS settlement_reconciliations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reconciliation_reference VARCHAR(80) NOT NULL UNIQUE,
    transaction_id INT NULL,
    provider_code VARCHAR(60) NULL,
    expected_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    actual_amount DECIMAL(12,2) NULL,
    status ENUM('pending','matched','mismatch','resolved') NOT NULL DEFAULT 'pending',
    notes VARCHAR(255) NULL,
    created_by_admin_id INT NULL,
    resolved_by_admin_id INT NULL,
    resolved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_settlement_reconciliations_status_created (status, created_at),
    INDEX idx_settlement_reconciliations_provider_created (provider_code, created_at),
    CONSTRAINT fk_settlement_reconciliations_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
    CONSTRAINT fk_settlement_reconciliations_created_by FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL,
    CONSTRAINT fk_settlement_reconciliations_resolved_by FOREIGN KEY (resolved_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS provider_expense_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_account_id INT NULL,
    transaction_id INT NULL,
    service_slug VARCHAR(80) NULL,
    provider_code VARCHAR(60) NULL,
    cost_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    selling_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    profit_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    source ENUM('provider_attempt','manual','report') NOT NULL DEFAULT 'report',
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_provider_expense_logs_provider_created (provider_account_id, created_at),
    INDEX idx_provider_expense_logs_transaction (transaction_id),
    CONSTRAINT fk_provider_expense_logs_provider FOREIGN KEY (provider_account_id) REFERENCES provider_accounts(id) ON DELETE SET NULL,
    CONSTRAINT fk_provider_expense_logs_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
);
