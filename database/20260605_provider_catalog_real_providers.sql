UPDATE provider_accounts
SET status = 'archived',
    archived_at = COALESCE(archived_at, NOW()),
    notes = 'Archived because this is not a registered production provider driver.'
WHERE code IN ('mock_main', 'smeplug', 'vtpass', 'clubkonnect', 'easyaccessapi')
   OR driver IN ('mock', 'smeplug', 'vtpass', 'clubkonnect', 'easyaccessapi');

INSERT IGNORE INTO provider_accounts (
    code, name, driver, status, priority_order, supports_fallback, low_balance_threshold,
    credentials_key, base_url, supported_services_json, notes
) VALUES
    ('albani', 'AlbaniAPI', 'albani', 'inactive', 1, 1, 5000.00, 'albani', 'https://albanidata.com/api/v1', '["airtime","data"]', 'Disabled by default until Albani credentials and plan mappings are configured.'),
    ('alrahuzdata', 'AlrahuzData', 'alrahuzdata', 'inactive', 2, 1, 5000.00, 'alrahuzdata', 'https://alrahuzdata.com.ng/api', '["airtime","data","cable_tv","electricity","exam_pin"]', 'Disabled by default until credentials and plan mappings are configured.'),
    ('abbpantami', 'AbbPantami', 'abbpantami', 'inactive', 3, 1, 5000.00, 'abbpantami', 'https://abbapantamiapi.com/api', '["airtime","data","cable_tv","electricity"]', 'Disabled by default until credentials and plan mappings are configured.'),
    ('cheapdatahub', 'CheapDataHub', 'cheapdatahub', 'inactive', 4, 1, 5000.00, 'cheapdatahub', 'https://www.cheapdatahub.ng/api/v1/resellers', '["airtime","data","cable_tv","electricity","exam_pin"]', 'Disabled by default until credentials and plan mappings are configured.');

UPDATE provider_accounts
SET priority_order = CASE code
        WHEN 'albani' THEN 1
        WHEN 'alrahuzdata' THEN 2
        WHEN 'abbpantami' THEN 3
        WHEN 'cheapdatahub' THEN 4
        ELSE priority_order
    END,
    credentials_key = CASE code
        WHEN 'albani' THEN 'albani'
        WHEN 'alrahuzdata' THEN 'alrahuzdata'
        WHEN 'abbpantami' THEN 'abbpantami'
        WHEN 'cheapdatahub' THEN 'cheapdatahub'
        ELSE credentials_key
    END
WHERE code IN ('albani', 'alrahuzdata', 'abbpantami', 'cheapdatahub');

INSERT IGNORE INTO schema_migrations (migration_key) VALUES ('20260605_provider_catalog_real_providers');
