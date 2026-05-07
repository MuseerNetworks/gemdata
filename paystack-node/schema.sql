CREATE DATABASE IF NOT EXISTS paystack_wallet_demo;
USE paystack_wallet_demo;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    amount_kobo BIGINT UNSIGNED NOT NULL,
    reference VARCHAR(120) NOT NULL UNIQUE,
    status ENUM('initialized', 'pending', 'success', 'failed') NOT NULL DEFAULT 'initialized',
    paystack_status VARCHAR(50) NULL,
    authorization_url TEXT NULL,
    access_code VARCHAR(120) NULL,
    gateway_response VARCHAR(255) NULL,
    paystack_transaction_id BIGINT UNSIGNED NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
    paid_at DATETIME NULL,
    verified_at DATETIME NULL,
    credited_at DATETIME NULL,
    credited_amount DECIMAL(14,2) NULL,
    channel ENUM('redirect', 'verify', 'webhook') NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_transactions_user_status (user_id, status),
    INDEX idx_transactions_reference_status (reference, status),
    CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT IGNORE INTO users (id, email, balance) VALUES
    (1, 'customer@example.com', 0.00);
