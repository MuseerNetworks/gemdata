USE gemdata_api;

ALTER TABLE transactions
    ADD INDEX idx_transactions_status_created (status, created_at),
    ADD INDEX idx_transactions_user_created (user_id, created_at),
    ADD INDEX idx_transactions_provider_created (provider_code, created_at),
    ADD INDEX idx_transactions_channel_created (channel, created_at);

ALTER TABLE wallet_transactions
    ADD INDEX idx_wallet_transactions_user_created (user_id, created_at);

ALTER TABLE admin_login_logs
    ADD INDEX idx_admin_login_logs_email_created (email, created_at),
    ADD INDEX idx_admin_login_logs_ip_created (ip_address, created_at);

ALTER TABLE transaction_events
    ADD INDEX idx_transaction_events_tx_created (transaction_id, created_at);

ALTER TABLE fraud_events
    ADD INDEX idx_fraud_events_user_created (user_id, created_at);

ALTER TABLE webhook_events
    ADD INDEX idx_webhook_events_source_event (source, event_key);
