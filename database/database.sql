CREATE DATABASE IF NOT EXISTS gemdata_api;
USE gemdata_api;

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    force_password_change TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    is_api_user TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE wallet_transactions (
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
    UNIQUE KEY uniq_wallet_transactions_idempotency (idempotency_key),
    CONSTRAINT fk_wallet_tx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_wallet_tx_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE
);

CREATE TABLE wallet_funding_requests (
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

CREATE TABLE user_funding_accounts (
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

CREATE TABLE services (
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

CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_id INT NOT NULL,
    reference VARCHAR(80) NOT NULL UNIQUE,
    idempotency_key VARCHAR(120) NULL,
    provider_reference VARCHAR(80) NULL,
    channel ENUM('web', 'api') NOT NULL DEFAULT 'web',
    status ENUM('pending', 'successful', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    amount DECIMAL(12,2) NOT NULL,
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
    CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_transactions_service FOREIGN KEY (service_id) REFERENCES services(id)
);

CREATE TABLE api_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_by_admin_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_api_user_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_api_user_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

CREATE TABLE api_keys (
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

CREATE TABLE commissions (
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

CREATE TABLE commission_logs (
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

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message VARCHAR(255) NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') NOT NULL DEFAULT 'info',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE api_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key_id INT NOT NULL,
    window_key VARCHAR(60) NOT NULL,
    request_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_api_window (api_key_id, window_key),
    CONSTRAINT fk_rate_limit_api_key FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE CASCADE
);

CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_type ENUM('admin', 'user', 'api') NOT NULL,
    actor_id INT NOT NULL,
    action VARCHAR(120) NOT NULL,
    description VARCHAR(255) NOT NULL,
    meta_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_login_logs (
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

CREATE TABLE user_password_reset_tokens (
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

CREATE TABLE admin_saved_views (
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

INSERT INTO admins (full_name, email, password_hash, force_password_change)
VALUES ('GemData Super Admin', 'admin@gemdata.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

INSERT INTO services (slug, name, category, description, min_amount, max_amount) VALUES
('airtime', 'Airtime', 'telecom', 'Instant airtime purchase', 50.00, 50000.00),
('data', 'Data', 'telecom', 'Mobile data bundles', 100.00, 100000.00),
('electricity', 'Electricity', 'utility', 'Meter token purchase', 500.00, 200000.00),
('cable_tv', 'Cable TV', 'tv', 'Cable subscription renewal', 500.00, 100000.00),
('exam_pin', 'Exam PIN', 'education', 'Exam pin vending', 1000.00, 50000.00),
('recharge_card', 'Recharge Card', 'printing', 'Recharge card generation', 500.00, 100000.00),
('data_card', 'Data Card', 'printing', 'Data card generation', 500.00, 100000.00),
('bulk_sms', 'Bulk SMS', 'messaging', 'Bulk SMS dispatch', 500.00, 100000.00);

INSERT INTO commissions (service_id, user_id, rate_percent)
SELECT id, NULL, 0.00 FROM services;
