-- =============================================================================
-- Migration: Fix duplicate commissions rows and add UNIQUE constraint
-- Date: 2026-07-26
-- Backup: storage/backups/commissions_backup_20260726.sql
-- =============================================================================

-- Step 1: Verify state before migration
SELECT 'BEFORE: duplicate count per (service_id, user_id)' AS label;
SELECT service_id, user_id, COUNT(*) AS cnt
FROM commissions
GROUP BY service_id, user_id
HAVING COUNT(*) > 1;

-- Step 2: Identify the "winner" row per (service_id, user_id) group.
-- We keep the row with the HIGHEST id (most recently written), which reflects
-- the admin's last save intent.
-- The update_at column cannot be trusted because INSERT rows share timestamps,
-- so we use MAX(id) as the canonical "latest" row.
SELECT 'KEEPER rows (max id per group)' AS label;
SELECT service_id, user_id, MAX(id) AS keeper_id, MAX(rate_percent) AS rate
FROM commissions
GROUP BY service_id, user_id;

-- Step 3: Before deleting, snapshot the commission_logs table row count
-- to confirm we are not touching it.
SELECT 'commission_logs row count (must not change)' AS label;
SELECT COUNT(*) AS commission_logs_count FROM commission_logs;

-- Step 4: Delete all duplicate rows that are NOT the keeper.
-- We use a self-join to find rows whose id is NOT the maximum for their group.
DELETE c
FROM commissions c
INNER JOIN (
    SELECT service_id, user_id, MAX(id) AS keeper_id
    FROM commissions
    GROUP BY service_id, user_id
) AS keepers
ON  c.service_id = keepers.service_id
AND (c.user_id = keepers.user_id OR (c.user_id IS NULL AND keepers.user_id IS NULL))
WHERE c.id <> keepers.keeper_id;

-- Step 5: Verify — should be zero duplicates remaining.
SELECT 'AFTER DELETE: duplicate count (expect 0 rows)' AS label;
SELECT service_id, user_id, COUNT(*) AS cnt
FROM commissions
GROUP BY service_id, user_id
HAVING COUNT(*) > 1;

-- Step 6: Show the clean table.
SELECT 'CLEAN TABLE' AS label;
SELECT id, service_id, user_id, rate_percent, created_at, updated_at
FROM commissions
ORDER BY service_id, user_id;

-- Step 7: Add UNIQUE constraint.
-- If this fails, the table still has duplicates — fix Step 4 first.
ALTER TABLE commissions
    ADD CONSTRAINT uk_commission_service_user
    UNIQUE (service_id, user_id);

-- Step 8: Verify the constraint exists.
SELECT 'UNIQUE KEY verification' AS label;
SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'commissions'
  AND INDEX_NAME = 'uk_commission_service_user';

SELECT 'Migration complete.' AS result;
