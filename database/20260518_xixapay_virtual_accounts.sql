-- XixaPay Virtual Account Support
-- Non-destructive migration. It keeps historical payment rows/columns intact.

DELIMITER $$

CREATE PROCEDURE add_xixapay_column_if_missing(
    IN table_name_value VARCHAR(64),
    IN column_name_value VARCHAR(64),
    IN column_definition_value TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = table_name_value
          AND COLUMN_NAME = column_name_value
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', table_name_value, '` ADD COLUMN ', column_definition_value);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE add_xixapay_index_if_missing(
    IN table_name_value VARCHAR(64),
    IN index_name_value VARCHAR(64),
    IN index_definition_value TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = table_name_value
          AND INDEX_NAME = index_name_value
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', table_name_value, '` ADD ', index_definition_value);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE drop_xixapay_user_unique_if_present()
BEGIN
    IF EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'user_funding_accounts'
          AND INDEX_NAME = 'user_id'
          AND NON_UNIQUE = 0
    ) THEN
        ALTER TABLE user_funding_accounts DROP INDEX user_id;
    END IF;
END$$

DELIMITER ;

CALL add_xixapay_column_if_missing('user_funding_accounts', 'account_reference', 'account_reference VARCHAR(120) NULL AFTER provider');
CALL drop_xixapay_user_unique_if_present();
CALL add_xixapay_index_if_missing('user_funding_accounts', 'uq_user_funding_accounts_user_provider', 'UNIQUE KEY uq_user_funding_accounts_user_provider (user_id, provider)');
CALL add_xixapay_index_if_missing('user_funding_accounts', 'idx_user_funding_accounts_number', 'INDEX idx_user_funding_accounts_number (dedicated_account_number)');

DROP PROCEDURE IF EXISTS add_xixapay_column_if_missing;
DROP PROCEDURE IF EXISTS add_xixapay_index_if_missing;
DROP PROCEDURE IF EXISTS drop_xixapay_user_unique_if_present;

INSERT IGNORE INTO schema_migrations (migration_key) VALUES ('20260518_xixapay_virtual_accounts');
