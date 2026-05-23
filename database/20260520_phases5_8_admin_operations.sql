-- Phases 5-8: Security, API platform, reports readiness, and CMS operations.
-- Additive only. Safe to run multiple times.

INSERT IGNORE INTO admin_permissions (permission_key, label) VALUES
    ('security.manage', 'Manage security and fraud center'),
    ('api.manage', 'Manage API platform'),
    ('cms.manage', 'Manage CMS and operations tools');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM admin_permissions
WHERE permission_key IN ('security.manage', 'api.manage', 'cms.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM admin_permissions
WHERE permission_key IN ('security.manage', 'alerts.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM admin_permissions
WHERE permission_key IN ('reports.view');

ALTER TABLE fraud_events
    ADD COLUMN IF NOT EXISTS review_status ENUM('open','reviewing','dismissed','confirmed') NOT NULL DEFAULT 'open' AFTER description,
    ADD COLUMN IF NOT EXISTS reviewed_by_admin_id INT NULL AFTER review_status,
    ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL AFTER reviewed_by_admin_id,
    ADD COLUMN IF NOT EXISTS admin_notes VARCHAR(255) NULL AFTER reviewed_at;

CREATE TABLE IF NOT EXISTS api_request_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_user_id INT NULL,
    api_key_id INT NULL,
    user_id INT NULL,
    method VARCHAR(12) NOT NULL DEFAULT 'GET',
    endpoint VARCHAR(255) NOT NULL,
    ip_address VARCHAR(64) NULL,
    status_code INT NULL,
    request_status ENUM('accepted','rejected','failed') NOT NULL DEFAULT 'accepted',
    error_message VARCHAR(255) NULL,
    response_time_ms INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_api_request_logs_key_created (api_key_id, created_at),
    INDEX idx_api_request_logs_user_created (user_id, created_at),
    CONSTRAINT fk_api_request_logs_api_user FOREIGN KEY (api_user_id) REFERENCES api_users(id) ON DELETE SET NULL,
    CONSTRAINT fk_api_request_logs_api_key FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE SET NULL,
    CONSTRAINT fk_api_request_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS api_ip_whitelists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_user_id INT NOT NULL,
    ip_address VARCHAR(64) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    notes VARCHAR(255) NULL,
    created_by_admin_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_api_ip_whitelist (api_user_id, ip_address),
    CONSTRAINT fk_api_ip_whitelist_api_user FOREIGN KEY (api_user_id) REFERENCES api_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_api_ip_whitelist_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

ALTER TABLE api_users
    ADD COLUMN IF NOT EXISTS rate_limit_per_minute INT NOT NULL DEFAULT 60 AFTER status,
    ADD COLUMN IF NOT EXISTS monthly_limit INT NOT NULL DEFAULT 0 AFTER rate_limit_per_minute,
    ADD COLUMN IF NOT EXISTS billing_status ENUM('active','paused') NOT NULL DEFAULT 'active' AFTER monthly_limit;

CREATE TABLE IF NOT EXISTS api_usage_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_user_id INT NOT NULL,
    usage_date DATE NOT NULL,
    request_count INT NOT NULL DEFAULT 0,
    transaction_count INT NOT NULL DEFAULT 0,
    volume_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_api_usage_day (api_user_id, usage_date),
    CONSTRAINT fk_api_usage_records_api_user FOREIGN KEY (api_user_id) REFERENCES api_users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS api_billing_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_user_id INT NOT NULL,
    billing_period VARCHAR(20) NOT NULL,
    usage_volume DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    charges DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('open','paid','waived') NOT NULL DEFAULT 'open',
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_api_billing_period (api_user_id, billing_period),
    CONSTRAINT fk_api_billing_records_api_user FOREIGN KEY (api_user_id) REFERENCES api_users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS api_webhook_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_user_id INT NOT NULL,
    webhook_url VARCHAR(255) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    secret_preview VARCHAR(40) NULL,
    last_success_at DATETIME NULL,
    last_failure_at DATETIME NULL,
    created_by_admin_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_api_webhook_configs_user_status (api_user_id, status),
    CONSTRAINT fk_api_webhook_configs_api_user FOREIGN KEY (api_user_id) REFERENCES api_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_api_webhook_configs_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS webhook_dead_letters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_event_id INT NULL,
    source VARCHAR(80) NOT NULL,
    target_url VARCHAR(255) NULL,
    retry_count INT NOT NULL DEFAULT 0,
    next_retry_at DATETIME NULL,
    last_error VARCHAR(255) NULL,
    status ENUM('pending','retrying','dead','resolved') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_webhook_dead_letters_status_retry (status, next_retry_at),
    CONSTRAINT fk_webhook_dead_letters_event FOREIGN KEY (webhook_event_id) REFERENCES webhook_events(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    body LONGTEXT NOT NULL,
    audience VARCHAR(40) NOT NULL DEFAULT 'all_users',
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    created_by_admin_id INT NOT NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_announcements_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS homepage_banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    subtitle VARCHAR(255) NULL,
    cta_label VARCHAR(80) NULL,
    cta_url VARCHAR(255) NULL,
    status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
    created_by_admin_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_homepage_banners_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS promo_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(60) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    created_by_admin_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_promo_codes_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS campaign_drafts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    channel ENUM('push','sms','email','in_app') NOT NULL DEFAULT 'in_app',
    audience VARCHAR(80) NOT NULL DEFAULT 'all_users',
    message LONGTEXT NOT NULL,
    status ENUM('draft','scheduled','sent','archived') NOT NULL DEFAULT 'draft',
    scheduled_at DATETIME NULL,
    created_by_admin_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_campaign_drafts_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS referral_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(80) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    updated_by_admin_id INT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_referral_settings_admin FOREIGN KEY (updated_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS user_notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    title VARCHAR(150) NOT NULL,
    message LONGTEXT NOT NULL,
    status ENUM('draft','active','archived') NOT NULL DEFAULT 'active',
    created_by_admin_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_notices_user_status (user_id, status),
    CONSTRAINT fk_user_notices_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_notices_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE CASCADE
);
