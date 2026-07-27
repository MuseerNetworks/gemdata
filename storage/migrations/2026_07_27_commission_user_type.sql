-- ============================================================
-- Migration: 2026_07_27_commission_user_type
--
-- Purpose:
--   Add a user_type column to the commissions table so that
--   independent commission rates can be configured per user
--   category (reseller | api) per service.
--
-- Design notes:
--   * user_type = 'reseller' — applies to Reseller users
--   * user_type = 'api'      — applies to API User accounts
--   * Rows with user_type IS NULL are legacy global-default
--     rows (user_id IS NULL). They are preserved for data
--     safety but are no longer used by the application after
--     this migration. A future explicit migration may clean
--     them up after verification.
--   * The existing UNIQUE KEY uniq_service_user_commission
--     (service_id, user_id) is kept unchanged. It governs
--     per-user overrides, which remain valid.
-- ============================================================

ALTER TABLE commissions
    ADD COLUMN user_type VARCHAR(20) NULL DEFAULT NULL
    AFTER user_id;

-- Index to support fast lookup of (service_id, user_type)
-- used by Commission::resolveRate().
ALTER TABLE commissions
    ADD INDEX idx_commissions_service_user_type (service_id, user_type);
