-- =============================================================
-- GemData Phase 1: Ensure missing tables exist on production
-- Run this via cPanel phpMyAdmin on yyrigitd_gemdata database
-- All statements are idempotent (IF NOT EXISTS / IF NOT EXISTS)
-- =============================================================

-- 1. service_prices (used by PricingService and admin/services.php)
CREATE TABLE IF NOT EXISTS service_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    network_code VARCHAR(60) NULL,
    tier ENUM('USER','RESELLER','AGENT','API_RESELLER') NOT NULL DEFAULT 'USER',
    cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    selling_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    profit_margin DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_service_price (service_id, network_code, tier),
    CONSTRAINT fk_service_prices_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. fraud_events (used by FraudService and admin/alerts.php)
CREATE TABLE IF NOT EXISTS fraud_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    transaction_id INT NULL,
    event_type VARCHAR(80) NOT NULL,
    risk_level ENUM('low','medium','high') NOT NULL DEFAULT 'low',
    fingerprint VARCHAR(190) NULL,
    description VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fraud_events_user_created (user_id, created_at),
    CONSTRAINT fk_fraud_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_fraud_events_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. user_custom_prices (used by PricingService for user overrides)
CREATE TABLE IF NOT EXISTS user_custom_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_id INT NOT NULL,
    network_code VARCHAR(60) NULL,
    selling_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_custom_price (user_id, service_id, network_code),
    CONSTRAINT fk_user_custom_prices_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_custom_prices_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Seed default tier prices if table was just created and is empty
INSERT IGNORE INTO service_prices (service_id, network_code, tier, cost_price, selling_price, profit_margin)
SELECT id, NULL, 'USER', min_amount, min_amount, 0.00 FROM services
UNION ALL
SELECT id, NULL, 'RESELLER', min_amount, min_amount, 0.00 FROM services
UNION ALL
SELECT id, NULL, 'AGENT', min_amount, min_amount, 0.00 FROM services
UNION ALL
SELECT id, NULL, 'API_RESELLER', min_amount, min_amount, 0.00 FROM services;

-- 5. Extend services table for Phase 4 (service maintenance control)
-- These are safe to run even if columns already exist on newer MySQL
-- If your MySQL version errors on duplicate column, just skip these 2 lines.

-- Add maintenance_message column if it doesn't exist
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'maintenance_message');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE services ADD COLUMN maintenance_message VARCHAR(255) NULL AFTER is_enabled', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Ensure broadcasts table has type column
SET @col_exists2 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'broadcasts' AND COLUMN_NAME = 'type');
SET @sql2 = IF(@col_exists2 = 0, "ALTER TABLE broadcasts ADD COLUMN type ENUM('info','success','warning','maintenance','critical') NOT NULL DEFAULT 'info' AFTER target_scope", 'SELECT 1');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- 7. Record migration
INSERT IGNORE INTO schema_migrations (migration_key) VALUES ('20260513_phase1_missing_tables');
