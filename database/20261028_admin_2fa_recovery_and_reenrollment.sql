-- Admin TOTP QR/recovery-code upgrade. Run once during deployment.
CREATE TABLE IF NOT EXISTS admin_two_factor_recovery_codes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_two_factor_recovery_codes_active (admin_id, used_at),
    CONSTRAINT fk_admin_two_factor_recovery_codes_admin
        FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
);

-- Force every existing admin to enroll a new authenticator after this release.
DELETE FROM admin_two_factor_recovery_codes
WHERE NOT EXISTS (
    SELECT 1 FROM schema_migrations WHERE migration_key = '20261028_admin_2fa_recovery_and_reenrollment'
);
UPDATE admins
SET two_factor_secret = NULL,
    two_factor_enabled = 0
WHERE NOT EXISTS (
    SELECT 1 FROM schema_migrations WHERE migration_key = '20261028_admin_2fa_recovery_and_reenrollment'
);

INSERT IGNORE INTO schema_migrations (migration_key)
VALUES ('20261028_admin_2fa_recovery_and_reenrollment');
