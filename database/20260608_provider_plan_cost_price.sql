-- Safe, non-destructive migration.
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'provider_service_plans'
      AND COLUMN_NAME = 'provider_cost_price'
);

SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE provider_service_plans ADD COLUMN provider_cost_price DECIMAL(12,2) NULL AFTER amount',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (migration_key)
VALUES ('20260608_provider_plan_cost_price');
