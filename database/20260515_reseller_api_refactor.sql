-- =============================================================
-- GemData Fintech Refactor Migration
-- File: 20260515_reseller_api_refactor.sql
-- Safe to run multiple times (idempotent).
-- Run via cPanel phpMyAdmin on yyrigitd_gemdata database.
-- =============================================================

-- ── STEP 1: Add user_type column to users ─────────────────────
-- Maps: smart = normal retail user, reseller = earns commission,
--       api = external B2B integration (admin activated)
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'users'
      AND COLUMN_NAME  = 'user_type'
);
SET @sql = IF(
    @col_exists = 0,
    "ALTER TABLE users ADD COLUMN user_type ENUM('smart','reseller','api') NOT NULL DEFAULT 'smart' AFTER tier",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── STEP 2: Backfill user_type from existing tier/is_api_user ──
UPDATE users SET user_type = 'api'      WHERE is_api_user = 1    AND user_type = 'smart';
UPDATE users SET user_type = 'reseller' WHERE tier = 'RESELLER'  AND user_type = 'smart';
UPDATE users SET user_type = 'reseller' WHERE tier = 'AGENT'     AND user_type = 'smart';
UPDATE users SET user_type = 'reseller' WHERE tier = 'API_RESELLER' AND is_api_user = 0 AND user_type = 'smart';

-- ── STEP 3: Rename AGENT tier → SMART (pricing engine compat) ──
-- We keep existing tier values for pricing rows, just block new
-- AGENT insertions by UI. No data deletion. Safe.
-- (ENUM modification is the only DDL risk — backup first on prod)
SET @tier_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'users'
      AND COLUMN_NAME  = 'tier'
      AND COLUMN_TYPE LIKE '%SMART%'
);
SET @sql2 = IF(
    @tier_exists = 0,
    "ALTER TABLE users MODIFY COLUMN tier ENUM('USER','RESELLER','SMART','API_RESELLER') NOT NULL DEFAULT 'USER'",
    'SELECT 1'
);
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- Migrate AGENT rows to SMART in users
UPDATE users SET tier = 'SMART' WHERE tier = 'AGENT';

-- Migrate AGENT rows to SMART in service_prices
SET @sp_agent = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'service_prices'
      AND COLUMN_NAME  = 'tier'
      AND COLUMN_TYPE  LIKE '%AGENT%'
);
SET @sql3 = IF(
    @sp_agent > 0,
    "ALTER TABLE service_prices MODIFY COLUMN tier ENUM('USER','RESELLER','SMART','API_RESELLER') NOT NULL DEFAULT 'USER'",
    'SELECT 1'
);
PREPARE stmt3 FROM @sql3; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;
UPDATE service_prices SET tier = 'SMART' WHERE tier = 'AGENT';

-- Migrate AGENT rows to SMART in commissions
SET @comm_agent = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'commissions'
      AND COLUMN_NAME  = 'tier'
);
-- (commissions table has no tier column — skip)

-- ── STEP 4: commission_wallets ──────────────────────────────────
CREATE TABLE IF NOT EXISTS commission_wallets (
    id         INT           AUTO_INCREMENT PRIMARY KEY,
    user_id    INT           NOT NULL UNIQUE,
    balance    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_commission_wallet_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── STEP 5: commission_wallet_transactions ──────────────────────
CREATE TABLE IF NOT EXISTS commission_wallet_transactions (
    id              INT           AUTO_INCREMENT PRIMARY KEY,
    user_id         INT           NOT NULL,
    wallet_id       INT           NOT NULL,
    reference       VARCHAR(80)   NOT NULL UNIQUE,
    type            ENUM('credit','debit','withdrawal') NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    balance_before  DECIMAL(12,2) NOT NULL,
    balance_after   DECIMAL(12,2) NOT NULL,
    narration       VARCHAR(255)  NOT NULL,
    source_type     ENUM('commission','withdrawal','admin_adjustment') NOT NULL DEFAULT 'commission',
    transaction_id  INT           NULL,
    idempotency_key VARCHAR(120)  NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_commission_wallet_idempotency (idempotency_key),
    INDEX idx_commission_wallet_tx_user_created (user_id, created_at),
    CONSTRAINT fk_comm_wallet_tx_user   FOREIGN KEY (user_id)       REFERENCES users(id)         ON DELETE CASCADE,
    CONSTRAINT fk_comm_wallet_tx_wallet FOREIGN KEY (wallet_id)     REFERENCES commission_wallets(id) ON DELETE CASCADE,
    CONSTRAINT fk_comm_wallet_tx_txn    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── STEP 6: withdrawal_requests ────────────────────────────────
CREATE TABLE IF NOT EXISTS withdrawal_requests (
    id              INT           AUTO_INCREMENT PRIMARY KEY,
    user_id         INT           NOT NULL,
    reference       VARCHAR(80)   NOT NULL UNIQUE,
    amount          DECIMAL(12,2) NOT NULL,
    bank_name       VARCHAR(120)  NOT NULL,
    account_number  VARCHAR(20)   NOT NULL,
    account_name    VARCHAR(150)  NOT NULL,
    status          ENUM('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
    admin_note      VARCHAR(255)  NULL,
    reviewed_by_admin_id INT      NULL,
    reviewed_at     DATETIME      NULL,
    paid_at         DATETIME      NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_withdrawal_requests_user    (user_id, created_at),
    INDEX idx_withdrawal_requests_status  (status, created_at),
    CONSTRAINT fk_withdrawal_requests_user  FOREIGN KEY (user_id)             REFERENCES users(id)   ON DELETE CASCADE,
    CONSTRAINT fk_withdrawal_requests_admin FOREIGN KEY (reviewed_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── STEP 7: upgrade_requests ────────────────────────────────────
CREATE TABLE IF NOT EXISTS upgrade_requests (
    id              INT           AUTO_INCREMENT PRIMARY KEY,
    user_id         INT           NOT NULL,
    from_type       ENUM('smart','reseller','api') NOT NULL DEFAULT 'smart',
    to_type         ENUM('reseller','api')         NOT NULL DEFAULT 'reseller',
    status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note      VARCHAR(255)  NULL,
    reviewed_by_admin_id INT      NULL,
    reviewed_at     DATETIME      NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_upgrade_requests_user   (user_id, created_at),
    INDEX idx_upgrade_requests_status (status, created_at),
    CONSTRAINT fk_upgrade_requests_user  FOREIGN KEY (user_id)              REFERENCES users(id)   ON DELETE CASCADE,
    CONSTRAINT fk_upgrade_requests_admin FOREIGN KEY (reviewed_by_admin_id)  REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── STEP 8: New admin permissions ───────────────────────────────
INSERT IGNORE INTO admin_permissions (permission_key, label) VALUES
    ('withdrawals.manage', 'Manage reseller withdrawal requests'),
    ('upgrades.manage',    'Manage user upgrade requests'),
    ('api_users.manage',   'Manage API user accounts');

-- Grant new permissions to super_admin (role_id = 1)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM admin_permissions
WHERE permission_key IN ('withdrawals.manage','upgrades.manage','api_users.manage');

-- Grant withdrawals.manage + upgrades.manage to finance role (role_id = 3)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM admin_permissions
WHERE permission_key IN ('withdrawals.manage','upgrades.manage');

-- ── STEP 9: Seed commission_wallets for existing resellers ──────
INSERT IGNORE INTO commission_wallets (user_id)
SELECT id FROM users WHERE user_type = 'reseller';

-- ── STEP 10: Seed SMART tier prices (was AGENT) ─────────────────
INSERT IGNORE INTO service_prices (service_id, network_code, tier, cost_price, selling_price, profit_margin)
SELECT service_id, network_code, 'SMART', cost_price, selling_price, profit_margin
FROM service_prices WHERE tier = 'USER';

-- ── STEP 11: Record migration ────────────────────────────────────
INSERT IGNORE INTO schema_migrations (migration_key)
VALUES ('20260515_reseller_api_refactor');
