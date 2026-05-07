USE gemdata_api;

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
    verified_at DATETIME NULL,
    credited_at DATETIME NULL,
    meta_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_wallet_funding_user_created (user_id, created_at),
    INDEX idx_wallet_funding_status_created (status, created_at),
    CONSTRAINT fk_wallet_funding_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
