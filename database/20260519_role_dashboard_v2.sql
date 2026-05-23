-- =============================================================
-- GemData Role Dashboard V2 Migration
-- Non-destructive/idempotent: adds role dashboard profile/request storage only.
-- Does not drop payment tables or historical Paystack/Zenith rows.
-- =============================================================

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'user_type'
);
SET @sql = IF(
    @col_exists = 0,
    "ALTER TABLE users ADD COLUMN user_type ENUM('smart','reseller','api') NOT NULL DEFAULT 'smart' AFTER tier",
    "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE users SET user_type = 'api' WHERE is_api_user = 1 AND user_type <> 'api';
UPDATE users SET user_type = 'reseller' WHERE tier IN ('RESELLER','AGENT') AND is_api_user = 0 AND user_type = 'smart';
UPDATE users SET user_type = 'api' WHERE tier = 'API_RESELLER' AND user_type <> 'api';

CREATE TABLE IF NOT EXISTS user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_key ENUM('smart','reseller','api') NOT NULL UNIQUE,
    label VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO user_roles (role_key, label, description, sort_order) VALUES
    ('smart', 'Smart User', 'Default user account for everyday purchases and wallet funding.', 10),
    ('reseller', 'Reseller', 'Business account with reseller pricing, reports, and customer growth tools.', 20),
    ('api', 'API User', 'Developer account with API keys, documentation, logs, and automation tools.', 30)
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    description = VALUES(description),
    sort_order = VALUES(sort_order),
    updated_at = NOW();

CREATE TABLE IF NOT EXISTS upgrade_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    from_type ENUM('smart','reseller','api') NOT NULL DEFAULT 'smart',
    to_type ENUM('reseller','api') NOT NULL DEFAULT 'reseller',
    status ENUM('pending','approved','rejected','needs_info') NOT NULL DEFAULT 'pending',
    business_name VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    reason TEXT NULL,
    website_url VARCHAR(255) NULL,
    admin_note VARCHAR(255) NULL,
    reviewed_by_admin_id INT NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_upgrade_requests_user (user_id, created_at),
    INDEX idx_upgrade_requests_status (status, created_at),
    CONSTRAINT fk_upgrade_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_upgrade_requests_admin FOREIGN KEY (reviewed_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @upgrade_status_needs_info = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'upgrade_requests'
      AND COLUMN_NAME = 'status'
      AND COLUMN_TYPE LIKE '%needs_info%'
);
SET @sql = IF(
    @upgrade_status_needs_info = 0,
    "ALTER TABLE upgrade_requests MODIFY COLUMN status ENUM('pending','approved','rejected','needs_info') NOT NULL DEFAULT 'pending'",
    "SELECT 1"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'upgrade_requests' AND COLUMN_NAME = 'business_name');
SET @sql = IF(@col_exists = 0, "ALTER TABLE upgrade_requests ADD COLUMN business_name VARCHAR(150) NULL AFTER status", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'upgrade_requests' AND COLUMN_NAME = 'phone');
SET @sql = IF(@col_exists = 0, "ALTER TABLE upgrade_requests ADD COLUMN phone VARCHAR(30) NULL AFTER business_name", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'upgrade_requests' AND COLUMN_NAME = 'reason');
SET @sql = IF(@col_exists = 0, "ALTER TABLE upgrade_requests ADD COLUMN reason TEXT NULL AFTER phone", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'upgrade_requests' AND COLUMN_NAME = 'website_url');
SET @sql = IF(@col_exists = 0, "ALTER TABLE upgrade_requests ADD COLUMN website_url VARCHAR(255) NULL AFTER reason", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS reseller_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    business_name VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    status ENUM('pending','active','suspended') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reseller_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_user_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    website_url VARCHAR(255) NULL,
    webhook_url VARCHAR(255) NULL,
    callback_url VARCHAR(255) NULL,
    ip_whitelist_json JSON NULL,
    status ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_api_user_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO admin_permissions (permission_key, label) VALUES
    ('upgrades.manage', 'Manage user upgrade requests');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM admin_permissions WHERE permission_key = 'upgrades.manage';

-- Related payment tables intentionally retained for review:
-- user_funding_accounts
-- wallet_funding_requests
-- webhook_events
