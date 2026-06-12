-- Display-only validity label for admin-managed provider plan catalog rows.

SET @provider_plan_validity_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'provider_service_plans'
      AND COLUMN_NAME = 'validity_label'
);

SET @provider_plan_validity_sql = IF(
    @provider_plan_validity_exists = 0,
    'ALTER TABLE provider_service_plans ADD COLUMN validity_label VARCHAR(80) NULL AFTER local_plan_name',
    'SELECT 1'
);

PREPARE stmt FROM @provider_plan_validity_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (migration_key)
VALUES ('20260610_provider_plan_validity_label');
