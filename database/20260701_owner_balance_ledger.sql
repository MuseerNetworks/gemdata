-- Owner capital/profit accounting balances and one-time opening reconciliation.

CREATE TABLE IF NOT EXISTS finance_opening_reconciliation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(80) NOT NULL UNIQUE,
    opening_capital DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    opening_profit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes VARCHAR(255) NULL,
    created_by_admin_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_finance_opening_admin_created (created_by_admin_id, created_at),
    CONSTRAINT fk_finance_opening_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS owner_balance_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(80) NOT NULL UNIQUE,
    balance_type ENUM('capital','profit') NOT NULL,
    entry_type ENUM('opening','capital_injection','transaction_success','owner_withdrawal','admin_reset') NOT NULL,
    direction ENUM('in','out') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    transaction_id INT NULL,
    owner_withdrawal_id INT NULL,
    business_cash_ledger_id INT NULL,
    opening_reconciliation_id INT NULL,
    idempotency_key VARCHAR(120) NULL,
    notes VARCHAR(255) NULL,
    created_by_admin_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_owner_balance_idempotency (idempotency_key),
    UNIQUE KEY uniq_owner_balance_transaction (transaction_id, balance_type, entry_type),
    UNIQUE KEY uniq_owner_balance_withdrawal (owner_withdrawal_id, balance_type, entry_type),
    INDEX idx_owner_balance_type_created (balance_type, created_at),
    INDEX idx_owner_balance_entry_created (entry_type, created_at),
    CONSTRAINT fk_owner_balance_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
    CONSTRAINT fk_owner_balance_withdrawal FOREIGN KEY (owner_withdrawal_id) REFERENCES owner_withdrawals(id) ON DELETE SET NULL,
    CONSTRAINT fk_owner_balance_business_cash FOREIGN KEY (business_cash_ledger_id) REFERENCES business_cash_ledger(id) ON DELETE SET NULL,
    CONSTRAINT fk_owner_balance_opening FOREIGN KEY (opening_reconciliation_id) REFERENCES finance_opening_reconciliation(id) ON DELETE SET NULL,
    CONSTRAINT fk_owner_balance_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

ALTER TABLE business_cash_ledger
    MODIFY entry_type ENUM(
        'user_funding_received',
        'provider_wallet_funded',
        'provider_wallet_recovered',
        'owner_capital_injected',
        'owner_withdrawal',
        'opening_capital',
        'opening_profit',
        'manual_adjustment'
    ) NOT NULL;

INSERT IGNORE INTO schema_migrations (migration_key)
VALUES ('20260701_owner_balance_ledger');
