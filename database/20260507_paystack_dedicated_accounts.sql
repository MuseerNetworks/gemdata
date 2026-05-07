USE gemdata_api;

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
