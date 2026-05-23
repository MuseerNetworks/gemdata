-- Phase 2 provider safeguards: additive schema only.
-- Run after backing up production data.

ALTER TABLE provider_accounts
    MODIFY COLUMN status ENUM('active','inactive','maintenance','archived') NOT NULL DEFAULT 'inactive';

ALTER TABLE provider_accounts
    ADD COLUMN IF NOT EXISTS cheapest_routing_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER supports_fallback,
    ADD COLUMN IF NOT EXISTS sandbox_mode TINYINT(1) NOT NULL DEFAULT 0 AFTER cheapest_routing_enabled,
    ADD COLUMN IF NOT EXISTS auto_disable_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER sandbox_mode,
    ADD COLUMN IF NOT EXISTS failure_threshold INT NOT NULL DEFAULT 5 AFTER auto_disable_enabled,
    ADD COLUMN IF NOT EXISTS minimum_success_rate DECIMAL(5,2) NOT NULL DEFAULT 80.00 AFTER failure_threshold,
    ADD COLUMN IF NOT EXISTS health_score DECIMAL(5,2) NOT NULL DEFAULT 100.00 AFTER minimum_success_rate,
    ADD COLUMN IF NOT EXISTS current_balance DECIMAL(12,2) NULL AFTER low_balance_threshold,
    ADD COLUMN IF NOT EXISTS balance_refreshed_at DATETIME NULL AFTER current_balance,
    ADD COLUMN IF NOT EXISTS circuit_breaker_status ENUM('closed','open','half_open') NOT NULL DEFAULT 'closed' AFTER balance_refreshed_at,
    ADD COLUMN IF NOT EXISTS circuit_breaker_opened_at DATETIME NULL AFTER circuit_breaker_status,
    ADD COLUMN IF NOT EXISTS circuit_breaker_until DATETIME NULL AFTER circuit_breaker_opened_at,
    ADD COLUMN IF NOT EXISTS last_api_error VARCHAR(255) NULL AFTER circuit_breaker_until,
    ADD COLUMN IF NOT EXISTS last_successful_at DATETIME NULL AFTER last_api_error,
    ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL AFTER last_successful_at;

CREATE TABLE IF NOT EXISTS routing_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_slug VARCHAR(60) NULL,
    routing_mode ENUM('manual','priority','cheapest','cheapest_health') NOT NULL DEFAULT 'priority',
    manual_provider_account_id INT NULL,
    fallback_enabled TINYINT(1) NOT NULL DEFAULT 1,
    minimum_success_rate DECIMAL(5,2) NOT NULL DEFAULT 80.00,
    health_weight DECIMAL(5,2) NOT NULL DEFAULT 30.00,
    cost_weight DECIMAL(5,2) NOT NULL DEFAULT 70.00,
    updated_by_admin_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_routing_settings_service (service_slug),
    INDEX idx_routing_settings_mode (routing_mode),
    CONSTRAINT fk_routing_settings_provider FOREIGN KEY (manual_provider_account_id) REFERENCES provider_accounts(id) ON DELETE SET NULL,
    CONSTRAINT fk_routing_settings_admin FOREIGN KEY (updated_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS provider_transaction_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NULL,
    provider_account_id INT NULL,
    provider_code VARCHAR(60) NULL,
    routing_mode VARCHAR(40) NULL,
    attempt_number INT NOT NULL DEFAULT 1,
    status ENUM('pending','processing','successful','failed','skipped') NOT NULL DEFAULT 'pending',
    request_reference VARCHAR(120) NULL,
    provider_reference VARCHAR(120) NULL,
    response_time_ms INT NULL,
    error_message VARCHAR(255) NULL,
    meta_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_provider_attempts_transaction (transaction_id, attempt_number),
    INDEX idx_provider_attempts_provider_created (provider_account_id, created_at),
    CONSTRAINT fk_provider_attempts_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
    CONSTRAINT fk_provider_attempts_provider FOREIGN KEY (provider_account_id) REFERENCES provider_accounts(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS provider_api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_account_id INT NULL,
    transaction_id INT NULL,
    direction ENUM('request','response','webhook') NOT NULL,
    endpoint VARCHAR(255) NULL,
    http_status INT NULL,
    response_time_ms INT NULL,
    redacted_payload LONGTEXT NULL,
    error_message VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_provider_api_logs_provider_created (provider_account_id, created_at),
    INDEX idx_provider_api_logs_transaction (transaction_id),
    CONSTRAINT fk_provider_api_logs_provider FOREIGN KEY (provider_account_id) REFERENCES provider_accounts(id) ON DELETE SET NULL,
    CONSTRAINT fk_provider_api_logs_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS provider_health_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_account_id INT NOT NULL,
    status VARCHAR(40) NOT NULL,
    health_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    success_rate DECIMAL(5,2) NULL,
    balance_amount DECIMAL(12,2) NULL,
    response_time_ms INT NULL,
    error_message VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_provider_health_logs_provider_created (provider_account_id, created_at),
    CONSTRAINT fk_provider_health_logs_provider FOREIGN KEY (provider_account_id) REFERENCES provider_accounts(id) ON DELETE CASCADE
);

INSERT IGNORE INTO schema_migrations (migration_key) VALUES ('20260520_phase2_provider_safeguards');
