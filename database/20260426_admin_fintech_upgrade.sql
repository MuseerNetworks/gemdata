USE gemdata_api;

ALTER TABLE admins
    ADD COLUMN IF NOT EXISTS role_id INT NULL AFTER password_hash,
    ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active,
    ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(255) NULL AFTER two_factor_enabled,
    ADD COLUMN IF NOT EXISTS invited_by_admin_id INT NULL AFTER two_factor_secret;

CREATE TABLE IF NOT EXISTS admin_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admin_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permission_key VARCHAR(80) NOT NULL UNIQUE,
    label VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_role_permission (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES admin_permissions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS admin_invites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    role_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_by_admin_id INT NOT NULL,
    invite_ip VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_admin_invite_email_token (email, token_hash),
    CONSTRAINT fk_admin_invites_role FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_admin_invites_creator FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS admin_login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NULL,
    email VARCHAR(150) NOT NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    was_successful TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_login_logs_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS tier ENUM('USER','RESELLER','AGENT','API_RESELLER') NOT NULL DEFAULT 'USER' AFTER status,
    ADD COLUMN IF NOT EXISTS referral_code VARCHAR(60) NULL AFTER tier,
    ADD COLUMN IF NOT EXISTS referred_by_user_id INT NULL AFTER referral_code,
    ADD COLUMN IF NOT EXISTS security_lock_until DATETIME NULL AFTER referred_by_user_id;

CREATE UNIQUE INDEX IF NOT EXISTS uniq_users_referral_code ON users (referral_code);

ALTER TABLE transactions
    ADD COLUMN IF NOT EXISTS provider_account_id INT NULL AFTER provider_reference,
    ADD COLUMN IF NOT EXISTS provider_code VARCHAR(60) NULL AFTER provider_account_id,
    ADD COLUMN IF NOT EXISTS selling_price DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER amount,
    ADD COLUMN IF NOT EXISTS cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER selling_price,
    ADD COLUMN IF NOT EXISTS profit_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER cost_price,
    ADD COLUMN IF NOT EXISTS pricing_source VARCHAR(40) NOT NULL DEFAULT 'legacy' AFTER profit_amount,
    ADD COLUMN IF NOT EXISTS retry_count INT NOT NULL DEFAULT 0 AFTER pricing_source,
    ADD COLUMN IF NOT EXISTS max_retry_count INT NOT NULL DEFAULT 2 AFTER retry_count,
    ADD COLUMN IF NOT EXISTS is_retryable TINYINT(1) NOT NULL DEFAULT 0 AFTER max_retry_count,
    ADD COLUMN IF NOT EXISTS override_status TINYINT(1) NOT NULL DEFAULT 0 AFTER is_retryable,
    ADD COLUMN IF NOT EXISTS override_reason VARCHAR(255) NULL AFTER override_status,
    ADD COLUMN IF NOT EXISTS overridden_by_admin_id INT NULL AFTER override_reason,
    ADD COLUMN IF NOT EXISTS overridden_at DATETIME NULL AFTER overridden_by_admin_id;

CREATE TABLE IF NOT EXISTS transaction_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    actor_type ENUM('system','admin','user','api','provider') NOT NULL DEFAULT 'system',
    actor_id INT NULL,
    notes VARCHAR(255) NULL,
    meta_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_transaction_events_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS provider_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    driver VARCHAR(60) NOT NULL DEFAULT 'mock',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    priority_order INT NOT NULL DEFAULT 1,
    supports_fallback TINYINT(1) NOT NULL DEFAULT 1,
    low_balance_threshold DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    credentials_key VARCHAR(120) NULL,
    base_url VARCHAR(255) NULL,
    supported_services_json LONGTEXT NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS provider_balance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_account_id INT NOT NULL,
    balance_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance_currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
    source VARCHAR(40) NOT NULL DEFAULT 'manual',
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_provider_balance_logs_provider FOREIGN KEY (provider_account_id) REFERENCES provider_accounts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS service_networks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    network_code VARCHAR(60) NOT NULL,
    network_name VARCHAR(120) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_service_network (service_id, network_code),
    CONSTRAINT fk_service_networks_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);

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
);

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
);

CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(120) NOT NULL UNIQUE,
    setting_value LONGTEXT NULL,
    setting_group VARCHAR(60) NOT NULL DEFAULT 'general',
    updated_by_admin_id INT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_system_settings_admin FOREIGN KEY (updated_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS broadcasts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    message LONGTEXT NOT NULL,
    target_scope VARCHAR(40) NOT NULL DEFAULT 'all_users',
    channel VARCHAR(40) NOT NULL DEFAULT 'in_app',
    status ENUM('draft','sent') NOT NULL DEFAULT 'draft',
    created_by_admin_id INT NOT NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_broadcasts_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS webhook_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(60) NOT NULL,
    event_key VARCHAR(120) NULL,
    signature VARCHAR(255) NULL,
    payload_json LONGTEXT NOT NULL,
    processing_status ENUM('pending','processed','failed','duplicate') NOT NULL DEFAULT 'pending',
    linked_transaction_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    CONSTRAINT fk_webhook_events_transaction FOREIGN KEY (linked_transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS fraud_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    transaction_id INT NULL,
    event_type VARCHAR(80) NOT NULL,
    risk_level ENUM('low','medium','high') NOT NULL DEFAULT 'low',
    fingerprint VARCHAR(190) NULL,
    description VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fraud_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_fraud_events_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS referral_commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_user_id INT NOT NULL,
    referred_user_id INT NOT NULL,
    transaction_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    rate_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_referral_commissions_referrer FOREIGN KEY (referrer_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_referral_commissions_referred FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_referral_commissions_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
);

ALTER TABLE admins
    ADD CONSTRAINT fk_admins_role FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_admins_invited_by FOREIGN KEY (invited_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL;

ALTER TABLE users
    ADD CONSTRAINT fk_users_referred_by FOREIGN KEY (referred_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE transactions
    ADD CONSTRAINT fk_transactions_provider_account FOREIGN KEY (provider_account_id) REFERENCES provider_accounts(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_transactions_overridden_by FOREIGN KEY (overridden_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL;

INSERT IGNORE INTO admin_roles (id, slug, name, description) VALUES
    (1, 'super_admin', 'Super Admin', 'Full control over the platform'),
    (2, 'support', 'Support', 'User support and transaction assistance'),
    (3, 'finance', 'Finance', 'Wallet, profit, and settlement oversight');

INSERT IGNORE INTO admin_permissions (permission_key, label) VALUES
    ('dashboard.view', 'View dashboard'),
    ('users.view', 'View users'),
    ('users.manage', 'Manage users'),
    ('wallet.manage', 'Manage wallets'),
    ('transactions.view', 'View transactions'),
    ('transactions.manage', 'Retry and override transactions'),
    ('services.manage', 'Manage services and pricing'),
    ('providers.manage', 'Manage providers'),
    ('reports.view', 'View reports'),
    ('settings.manage', 'Manage settings'),
    ('roles.manage', 'Manage roles and invites'),
    ('alerts.manage', 'Manage alerts and broadcasts');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM admin_permissions;

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM admin_permissions WHERE permission_key IN (
    'dashboard.view',
    'users.view',
    'transactions.view',
    'transactions.manage',
    'alerts.manage'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM admin_permissions WHERE permission_key IN (
    'dashboard.view',
    'users.view',
    'wallet.manage',
    'transactions.view',
    'reports.view',
    'settings.manage'
);

INSERT IGNORE INTO provider_accounts (code, name, driver, status, priority_order, supports_fallback, low_balance_threshold, credentials_key, base_url, supported_services_json, notes)
VALUES ('mock_main', 'Mock VTU Provider', 'mock', 'active', 1, 1, 1000.00, 'mock_main', 'local', '["airtime","data","electricity","cable_tv","exam_pin","recharge_card","data_card","bulk_sms"]', 'Default safe mock provider');

INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_group) VALUES
    ('site_name', 'GemData API Platform', 'general'),
    ('site_logo', '', 'general'),
    ('maintenance_mode', '0', 'general'),
    ('maintenance_message', 'Platform is under scheduled maintenance.', 'general'),
    ('charge_default_percent', '0', 'billing'),
    ('referral_enabled', '0', 'referrals'),
    ('referral_default_rate', '0', 'referrals'),
    ('auto_retry_enabled', '0', 'automation'),
    ('low_balance_alert_threshold', '5000', 'alerts');

INSERT IGNORE INTO service_networks (service_id, network_code, network_name, is_enabled)
SELECT id, 'mtn', 'MTN', 1 FROM services WHERE slug IN ('airtime', 'data', 'recharge_card', 'data_card')
UNION ALL
SELECT id, 'airtel', 'Airtel', 1 FROM services WHERE slug IN ('airtime', 'data', 'recharge_card', 'data_card')
UNION ALL
SELECT id, 'glo', 'Glo', 1 FROM services WHERE slug IN ('airtime', 'data', 'recharge_card', 'data_card')
UNION ALL
SELECT id, '9mobile', '9mobile', 1 FROM services WHERE slug IN ('airtime', 'data', 'recharge_card', 'data_card');

INSERT IGNORE INTO service_prices (service_id, network_code, tier, cost_price, selling_price, profit_margin)
SELECT id, NULL, 'USER', min_amount, min_amount, 0.00 FROM services
UNION ALL
SELECT id, NULL, 'RESELLER', min_amount, min_amount, 0.00 FROM services
UNION ALL
SELECT id, NULL, 'AGENT', min_amount, min_amount, 0.00 FROM services
UNION ALL
SELECT id, NULL, 'API_RESELLER', min_amount, min_amount, 0.00 FROM services;
