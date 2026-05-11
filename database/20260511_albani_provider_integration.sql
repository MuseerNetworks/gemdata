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

INSERT IGNORE INTO provider_accounts (
    code, name, driver, status, priority_order, supports_fallback, low_balance_threshold,
    credentials_key, base_url, supported_services_json, notes
) VALUES (
    'albani', 'AlbaniAPI', 'albani', 'inactive', 1, 1, 5000.00,
    'albani', 'https://albanidata.com/api/v1', '["airtime","data"]',
    'Disabled by default until Albani credentials and plan mappings are configured.'
);

INSERT IGNORE INTO schema_migrations (migration_key) VALUES ('20260511_albani_provider_integration');
