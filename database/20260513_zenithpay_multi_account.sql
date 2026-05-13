-- ============================================================
-- ZenithPay Virtual Account — Multi-Provider Account Support
-- Allows each user to have one account per provider.
-- ============================================================

-- 1. Drop the single-column UNIQUE on user_id.
-- 2. Add composite UNIQUE on (user_id, provider) instead.
-- This allows Paystack + ZenithPay accounts per user.

ALTER TABLE user_funding_accounts
    DROP INDEX user_id,   -- drops the UNIQUE KEY that was auto-named 'user_id'
    ADD UNIQUE KEY uq_user_funding_accounts_user_provider (user_id, provider);

-- 3. Add bvn column (required by ZenithPay, optional for Paystack).
ALTER TABLE user_funding_accounts
    ADD COLUMN bvn VARCHAR(11) NULL AFTER provider,
    ADD COLUMN account_reference VARCHAR(120) NULL AFTER bvn;

-- 4. Normalise existing Paystack rows (safe — they already have provider='paystack').
UPDATE user_funding_accounts SET provider = 'paystack' WHERE provider = '' OR provider IS NULL;
