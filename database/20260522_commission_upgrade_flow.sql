-- GemData commission withdrawal and upgrade agreement additive support
-- Safe migration: no destructive operations.

INSERT INTO system_settings (setting_key, setting_value, setting_group)
SELECT 'commission_min_withdrawal', '500', 'commission'
WHERE NOT EXISTS (
    SELECT 1 FROM system_settings WHERE setting_key = 'commission_min_withdrawal'
);

SET @schema_name := DATABASE();

SET @add_agreement_accepted_at := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE upgrade_requests ADD COLUMN agreement_accepted_at DATETIME NULL AFTER admin_note',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'upgrade_requests'
      AND COLUMN_NAME = 'agreement_accepted_at'
);
PREPARE stmt FROM @add_agreement_accepted_at;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_agreement_type := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE upgrade_requests ADD COLUMN agreement_type VARCHAR(80) NULL AFTER agreement_accepted_at',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'upgrade_requests'
      AND COLUMN_NAME = 'agreement_type'
);
PREPARE stmt FROM @add_agreement_type;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_agreement_ip := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE upgrade_requests ADD COLUMN agreement_ip VARCHAR(64) NULL AFTER agreement_type',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'upgrade_requests'
      AND COLUMN_NAME = 'agreement_ip'
);
PREPARE stmt FROM @add_agreement_ip;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
