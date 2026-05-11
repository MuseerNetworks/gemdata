CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_key VARCHAR(120) NOT NULL UNIQUE,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

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

CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NULL,
    two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
    two_factor_secret VARCHAR(255) NULL,
    invited_by_admin_id INT NULL,
    force_password_change TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_admins_role FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE SET NULL,
    CONSTRAINT fk_admins_invited_by FOREIGN KEY (invited_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    tier ENUM('USER','RESELLER','AGENT','API_RESELLER') NOT NULL DEFAULT 'USER',
    referral_code VARCHAR(60) NULL,
    referred_by_user_id INT NULL,
    security_lock_until DATETIME NULL,
    is_api_user TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_users_referral_code (referral_code),
    CONSTRAINT fk_users_referred_by FOREIGN KEY (referred_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    category VARCHAR(60) NOT NULL,
    description VARCHAR(255) NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    min_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    max_amount DECIMAL(12,2) NOT NULL DEFAULT 999999.99,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS provider_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    driver VARCHAR(60) NOT NULL DEFAULT 'mock',
    status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
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

CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_id INT NOT NULL,
    reference VARCHAR(80) NOT NULL UNIQUE,
    idempotency_key VARCHAR(120) NULL,
    provider_reference VARCHAR(80) NULL,
    provider_account_id INT NULL,
    provider_code VARCHAR(60) NULL,
    channel ENUM('web', 'api') NOT NULL DEFAULT 'web',
    status ENUM('pending', 'successful', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    amount DECIMAL(12,2) NOT NULL,
    selling_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    profit_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    pricing_source VARCHAR(40) NOT NULL DEFAULT 'legacy',
    retry_count INT NOT NULL DEFAULT 0,
    max_retry_count INT NOT NULL DEFAULT 2,
    is_retryable TINYINT(1) NOT NULL DEFAULT 0,
    override_status TINYINT(1) NOT NULL DEFAULT 0,
    override_reason VARCHAR(255) NULL,
    overridden_by_admin_id INT NULL,
    overridden_at DATETIME NULL,
    commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    recipient VARCHAR(150) NOT NULL,
    customer_name VARCHAR(150) NULL,
    payload_json LONGTEXT NULL,
    response_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    processing_started_at DATETIME NULL,
    processed_at DATETIME NULL,
    failure_code VARCHAR(120) NULL,
    UNIQUE KEY uniq_transactions_idempotency (user_id, channel, idempotency_key),
    INDEX idx_transactions_reference_status (reference, status),
    INDEX idx_transactions_pending_processor (status, processing_started_at, id),
    INDEX idx_transactions_status_created (status, created_at),
    INDEX idx_transactions_user_created (user_id, created_at),
    INDEX idx_transactions_provider_created (provider_code, created_at),
    INDEX idx_transactions_channel_created (channel, created_at),
    CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_transactions_service FOREIGN KEY (service_id) REFERENCES services(id),
    CONSTRAINT fk_transactions_provider_account FOREIGN KEY (provider_account_id) REFERENCES provider_accounts(id) ON DELETE SET NULL,
    CONSTRAINT fk_transactions_overridden_by FOREIGN KEY (overridden_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    wallet_id INT NOT NULL,
    reference VARCHAR(80) NOT NULL UNIQUE,
    type ENUM('credit', 'debit', 'refund') NOT NULL,
    channel ENUM('web', 'api', 'admin', 'system') NOT NULL DEFAULT 'web',
    amount DECIMAL(12,2) NOT NULL,
    balance_before DECIMAL(12,2) NOT NULL,
    balance_after DECIMAL(12,2) NOT NULL,
    narration VARCHAR(255) NOT NULL,
    source_type ENUM('purchase','funding','admin_adjustment','refund','legacy') NOT NULL DEFAULT 'legacy',
    source_id INT NULL,
    idempotency_key VARCHAR(120) NULL,
    reason_code VARCHAR(120) NULL,
    meta_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wallet_transactions_source (source_type, source_id),
    INDEX idx_wallet_transactions_user_created (user_id, created_at),
    UNIQUE KEY uniq_wallet_transactions_idempotency (idempotency_key),
    CONSTRAINT fk_wallet_tx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_wallet_tx_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS wallet_funding_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reference VARCHAR(80) NOT NULL UNIQUE,
    provider VARCHAR(80) NOT NULL,
    provider_reference VARCHAR(120) NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(12) NOT NULL DEFAULT 'NGN',
    status ENUM('initiated', 'credited', 'failed') NOT NULL DEFAULT 'initiated',
    callback_token_hash VARCHAR(255) NOT NULL,
    idempotency_key VARCHAR(120) NULL,
    callback_session_token_hash VARCHAR(255) NULL,
    verified_at DATETIME NULL,
    credited_at DATETIME NULL,
    meta_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_wallet_funding_user_created (user_id, created_at),
    INDEX idx_wallet_funding_status_created (status, created_at),
    INDEX idx_wallet_funding_reference_status (reference, status),
    UNIQUE KEY uniq_wallet_funding_idempotency (user_id, idempotency_key),
    CONSTRAINT fk_wallet_funding_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_funding_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    provider VARCHAR(80) NOT NULL DEFAULT 'paystack',
    paystack_customer_id BIGINT NULL,
    paystack_customer_code VARCHAR(120) NULL,
    dedicated_account_id BIGINT NULL,
    dedicated_account_number VARCHAR(32) NULL,
    account_name VARCHAR(191) NULL,
    bank_name VARCHAR(120) NULL,
    bank_slug VARCHAR(120) NULL,
    status ENUM('pending', 'assigned', 'failed') NOT NULL DEFAULT 'pending',
    last_error_message VARCHAR(255) NULL,
    requested_at DATETIME NULL,
    assigned_at DATETIME NULL,
    meta_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_funding_accounts_status (status, updated_at),
    INDEX idx_user_funding_accounts_customer (paystack_customer_code),
    CONSTRAINT fk_user_funding_accounts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS api_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_by_admin_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_api_user_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_api_user_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_user_id INT NOT NULL,
    api_key VARCHAR(80) NOT NULL UNIQUE,
    secret_hash VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_api_key_api_user FOREIGN KEY (api_user_id) REFERENCES api_users(id) ON DELETE CASCADE
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

CREATE TABLE IF NOT EXISTS commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    user_id INT NULL,
    rate_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_service_user_commission (service_id, user_id),
    CONSTRAINT fk_commission_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    CONSTRAINT fk_commission_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS commission_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_id INT NOT NULL,
    service_id INT NOT NULL,
    rate_percent DECIMAL(5,2) NOT NULL,
    gross_amount DECIMAL(12,2) NOT NULL,
    commission_amount DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_commission_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_commission_log_tx FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    CONSTRAINT fk_commission_log_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message VARCHAR(255) NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') NOT NULL DEFAULT 'info',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS api_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key_id INT NOT NULL,
    window_key VARCHAR(60) NOT NULL,
    request_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_api_window (api_key_id, window_key),
    CONSTRAINT fk_rate_limit_api_key FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_type ENUM('admin', 'user', 'api', 'system') NOT NULL,
    actor_id INT NOT NULL,
    action VARCHAR(120) NOT NULL,
    description VARCHAR(255) NOT NULL,
    meta_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
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
    INDEX idx_admin_login_logs_email_created (email, created_at),
    INDEX idx_admin_login_logs_ip_created (ip_address, created_at),
    CONSTRAINT fk_admin_login_logs_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS user_login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(64) NOT NULL,
    user_agent VARCHAR(255) NULL,
    was_successful TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_login_logs_email_created (email, created_at),
    INDEX idx_user_login_logs_ip_created (ip_address, created_at),
    INDEX idx_user_login_logs_user_created (user_id, created_at),
    CONSTRAINT fk_user_login_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS user_password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_by_admin_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_password_reset_user (user_id, created_at),
    INDEX idx_user_password_reset_expires (expires_at),
    CONSTRAINT fk_user_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_password_reset_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
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

CREATE TABLE IF NOT EXISTS provider_service_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_account_id INT NOT NULL,
    service_id INT NOT NULL,
    network_code VARCHAR(60) NULL,
    local_plan_code VARCHAR(120) NOT NULL,
    local_plan_name VARCHAR(191) NOT NULL,
    provider_plan_id VARCHAR(120) NOT NULL,
    provider_plan_name VARCHAR(191) NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_provider_service_plan (provider_account_id, service_id, network_code, local_plan_code),
    INDEX idx_provider_service_plan_lookup (service_id, network_code, local_plan_code, is_enabled),
    CONSTRAINT fk_provider_service_plans_provider FOREIGN KEY (provider_account_id) REFERENCES provider_accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_provider_service_plans_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
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
    INDEX idx_webhook_events_source_event (source, event_key),
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
    INDEX idx_fraud_events_user_created (user_id, created_at),
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

CREATE TABLE IF NOT EXISTS transaction_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    actor_type ENUM('system','admin','user','api','provider') NOT NULL DEFAULT 'system',
    actor_id INT NULL,
    notes VARCHAR(255) NULL,
    meta_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transaction_events_tx_created (transaction_id, created_at),
    CONSTRAINT fk_transaction_events_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS admin_saved_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    page_key VARCHAR(50) NOT NULL,
    name VARCHAR(120) NOT NULL,
    filters_json LONGTEXT NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_admin_saved_views_owner (admin_id, page_key),
    CONSTRAINT fk_admin_saved_views_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
);

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
SELECT 2, id FROM admin_permissions WHERE permission_key IN ('dashboard.view','users.view','transactions.view','transactions.manage','alerts.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM admin_permissions WHERE permission_key IN ('dashboard.view','users.view','wallet.manage','transactions.view','reports.view','settings.manage');

INSERT IGNORE INTO provider_accounts (code, name, driver, status, priority_order, supports_fallback, low_balance_threshold, credentials_key, base_url, supported_services_json, notes) VALUES
    ('albani', 'AlbaniAPI', 'albani', 'inactive', 1, 1, 5000.00, 'albani', 'https://albanidata.com/api/v1', '["airtime","data"]', 'Disabled by default until Albani credentials and plan mappings are configured.'),
    ('smeplug', 'SMEPlug', 'smeplug', 'inactive', 1, 1, 5000.00, 'smeplug', '', '["airtime","data","cable_tv","electricity","exam_pin"]', 'Disabled by default until credentials are configured.'),
    ('vtpass', 'VTpass', 'vtpass', 'inactive', 2, 1, 5000.00, 'vtpass', '', '["airtime","data","cable_tv","electricity","exam_pin"]', 'Disabled by default until credentials are configured.'),
    ('clubkonnect', 'ClubKonnect', 'clubkonnect', 'inactive', 3, 1, 5000.00, 'clubkonnect', '', '["airtime","data","cable_tv","electricity","exam_pin"]', 'Disabled by default until credentials are configured.'),
    ('alrahuzdata', 'AlrahuzData', 'alrahuzdata', 'inactive', 4, 1, 5000.00, 'alrahuzdata', '', '["airtime","data","cable_tv","electricity","exam_pin"]', 'Disabled by default until credentials are configured.'),
    ('easyaccessapi', 'EasyAccessAPI', 'easyaccessapi', 'inactive', 5, 1, 5000.00, 'easyaccessapi', '', '["airtime","data","cable_tv","electricity","exam_pin"]', 'Disabled by default until credentials are configured.'),
    ('mock_main', 'Mock VTU Provider', 'mock', 'inactive', 99, 0, 0.00, 'mock_main', 'local', '["airtime","data","electricity","cable_tv","exam_pin","recharge_card","data_card","bulk_sms"]', 'Local-only mock provider.');

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

INSERT IGNORE INTO services (slug, name, category, description, min_amount, max_amount) VALUES
    ('airtime', 'Airtime', 'telecom', 'Instant airtime purchase', 50.00, 50000.00),
    ('data', 'Data', 'telecom', 'Mobile data bundles', 100.00, 100000.00),
    ('electricity', 'Electricity', 'utility', 'Meter token purchase', 500.00, 200000.00),
    ('cable_tv', 'Cable TV', 'tv', 'Cable subscription renewal', 500.00, 100000.00),
    ('exam_pin', 'Exam PIN', 'education', 'Exam pin vending', 1000.00, 50000.00),
    ('recharge_card', 'Recharge Card', 'printing', 'Recharge card generation', 500.00, 100000.00),
    ('data_card', 'Data Card', 'printing', 'Data card generation', 500.00, 100000.00),
    ('bulk_sms', 'Bulk SMS', 'messaging', 'Bulk SMS dispatch', 500.00, 100000.00);

INSERT IGNORE INTO commissions (service_id, user_id, rate_percent)
SELECT id, NULL, 0.00 FROM services;

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
