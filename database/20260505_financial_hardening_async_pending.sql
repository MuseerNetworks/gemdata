USE gemdata_api;

ALTER TABLE wallet_transactions
    ADD COLUMN IF NOT EXISTS source_type ENUM('purchase','funding','admin_adjustment','refund','legacy') NOT NULL DEFAULT 'legacy' AFTER narration,
    ADD COLUMN IF NOT EXISTS source_id INT NULL AFTER source_type,
    ADD COLUMN IF NOT EXISTS idempotency_key VARCHAR(120) NULL AFTER source_id,
    ADD COLUMN IF NOT EXISTS reason_code VARCHAR(120) NULL AFTER idempotency_key,
    ADD INDEX IF NOT EXISTS idx_wallet_transactions_source (source_type, source_id),
    ADD UNIQUE INDEX IF NOT EXISTS uniq_wallet_transactions_idempotency (idempotency_key);

ALTER TABLE wallet_funding_requests
    ADD COLUMN IF NOT EXISTS idempotency_key VARCHAR(120) NULL AFTER callback_token_hash,
    ADD COLUMN IF NOT EXISTS callback_session_token_hash VARCHAR(255) NULL AFTER idempotency_key,
    ADD UNIQUE INDEX IF NOT EXISTS uniq_wallet_funding_idempotency (user_id, idempotency_key),
    ADD INDEX IF NOT EXISTS idx_wallet_funding_reference_status (reference, status);

ALTER TABLE transactions
    ADD COLUMN IF NOT EXISTS idempotency_key VARCHAR(120) NULL AFTER reference,
    ADD COLUMN IF NOT EXISTS processing_started_at DATETIME NULL AFTER updated_at,
    ADD COLUMN IF NOT EXISTS processed_at DATETIME NULL AFTER processing_started_at,
    ADD COLUMN IF NOT EXISTS failure_code VARCHAR(120) NULL AFTER processed_at,
    ADD UNIQUE INDEX IF NOT EXISTS uniq_transactions_idempotency (user_id, channel, idempotency_key),
    ADD INDEX IF NOT EXISTS idx_transactions_pending_processor (status, processing_started_at, id),
    ADD INDEX IF NOT EXISTS idx_transactions_reference_status (reference, status);

ALTER TABLE user_password_reset_tokens
    MODIFY COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

UPDATE wallet_transactions wt
SET wt.source_type = CASE
        WHEN wt.type = 'credit' AND wt.channel = 'admin' THEN 'admin_adjustment'
        WHEN wt.type = 'debit' AND wt.channel = 'admin' THEN 'admin_adjustment'
        WHEN wt.type = 'refund' THEN 'refund'
        ELSE 'legacy'
    END
WHERE wt.source_type = 'legacy' OR wt.source_type IS NULL;
