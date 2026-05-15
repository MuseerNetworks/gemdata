-- =============================================================
-- GemData: Commission Rates + Feature Flags Seed
-- File: 20260515_seed_commission_and_flags.sql
-- Safe to run multiple times (INSERT IGNORE / ON DUPLICATE KEY).
-- Run via cPanel phpMyAdmin on yyrigitd_gemdata database.
-- =============================================================

-- ── STEP 1: Set 3% global commission for ALL services ─────────
-- user_id IS NULL = applies to ALL resellers (global default)
-- If a service already has a 0% rate, update it to 3%.
-- If a service has NO row yet, insert one.
--
-- This uses the unique key (service_id, user_id) to safely upsert.

INSERT INTO commissions (service_id, user_id, rate_percent)
SELECT id, NULL, 3.00 FROM services
ON DUPLICATE KEY UPDATE rate_percent = 3.00;

-- ── STEP 2: Feature Flags ─────────────────────────────────────
-- Upserts flags so existing values are updated, not duplicated.

INSERT INTO system_settings (setting_key, setting_value, setting_group)
VALUES
    ('reseller_enabled',   '1', 'features'),
    ('commission_enabled', '1', 'features'),
    ('withdrawal_enabled', '1', 'features'),
    ('api_enabled',        '1', 'features')
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    setting_group = VALUES(setting_group);

-- ── STEP 3: Record migration ───────────────────────────────────
INSERT IGNORE INTO schema_migrations (migration_key)
VALUES ('20260515_seed_commission_and_flags');

-- ── VERIFY ────────────────────────────────────────────────────
-- Run these SELECT statements to confirm results:
--
-- SELECT s.name, c.rate_percent
--   FROM services s
--   JOIN commissions c ON c.service_id = s.id AND c.user_id IS NULL
--   ORDER BY s.name;
--
-- SELECT setting_key, setting_value FROM system_settings
--  WHERE setting_key IN ('reseller_enabled','commission_enabled','withdrawal_enabled','api_enabled');
