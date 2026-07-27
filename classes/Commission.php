<?php

declare(strict_types=1);

namespace GemData\Classes;

class Commission
{
    /** User type slug for Reseller accounts. */
    public const TYPE_RESELLER = 'reseller';

    /** User type slug for API User accounts. */
    public const TYPE_API = 'api';

    /** Accepted user types for commission configuration. */
    public const CONFIGURABLE_TYPES = [self::TYPE_RESELLER, self::TYPE_API];

    public function __construct(
        private Database          $db,
        private ?CommissionWallet $commissionWallet = null
    ) {
    }

    /**
     * Resolve the applicable commission rate for a user on a given service.
     *
     * Resolution order:
     *   1. User-specific override  (user_id = $userId, any user_type)
     *   2. User-type default row   (user_id IS NULL, user_type = $userType)
     *
     * Legacy rows with user_type IS NULL (old global defaults) are intentionally
     * skipped — they predate the per-type model and are preserved for data safety
     * but must not influence commission calculations going forward.
     *
     * @param int    $userId   The purchasing user's ID.
     * @param int    $serviceId The service being purchased.
     * @param string $userType  'reseller' or 'api' — determines which type-default
     *                          row is used when no user-specific override exists.
     */
    public function resolveRate(int $userId, int $serviceId, string $userType = ''): float
    {
        // 1. User-specific override (highest priority — unchanged behaviour)
        $specific = $this->db->first(
            'SELECT rate_percent FROM commissions WHERE user_id = :user_id AND service_id = :service_id ORDER BY id DESC LIMIT 1',
            ['user_id' => $userId, 'service_id' => $serviceId]
        );
        if ($specific) {
            return (float) $specific['rate_percent'];
        }

        // 2. User-type default row — only consult if a valid type is provided.
        //    Rows with user_type IS NULL are legacy and are deliberately excluded.
        if (in_array($userType, self::CONFIGURABLE_TYPES, true)) {
            $default = $this->db->first(
                'SELECT rate_percent FROM commissions
                 WHERE user_id IS NULL AND service_id = :service_id AND user_type = :user_type
                 ORDER BY id DESC LIMIT 1',
                ['service_id' => $serviceId, 'user_type' => $userType]
            );
            if ($default) {
                return (float) $default['rate_percent'];
            }
        }

        return 0.0;
    }

    public function log(int $userId, int $transactionId, int $serviceId, float $rate, float $amount, float $commissionAmount): void
    {
        $this->db->execute(
            'INSERT INTO commission_logs (user_id, transaction_id, service_id, rate_percent, gross_amount, commission_amount)
             VALUES (:user_id, :transaction_id, :service_id, :rate_percent, :gross_amount, :commission_amount)',
            [
                'user_id'           => $userId,
                'transaction_id'    => $transactionId,
                'service_id'        => $serviceId,
                'rate_percent'      => $rate,
                'gross_amount'      => $amount,
                'commission_amount' => $commissionAmount,
            ]
        );
    }

    /**
     * Log commission AND credit the user's commission wallet.
     * Called by TransactionService on successful transactions for Reseller / API Users.
     */
    public function creditToWallet(
        int    $userId,
        int    $transactionId,
        int    $serviceId,
        float  $rate,
        float  $transactionAmount,
        float  $commissionAmount,
        string $serviceLabel = 'VTU Service'
    ): void {
        if ($commissionAmount <= 0) {
            return;
        }
        $this->log($userId, $transactionId, $serviceId, $rate, $transactionAmount, $commissionAmount);
        $this->commissionWallet?->credit(
            $userId,
            $commissionAmount,
            sprintf('Commission earned — %s (%.2f%%)', $serviceLabel, $rate),
            $transactionId,
            'comm_tx_' . $transactionId
        );
    }

    /**
     * Save a commission rate for a given service.
     *
     * Modes:
     *   A. Type-based row  ($userId = null, $userType = 'reseller' or 'api'):
     *      Upserts the type-default row — the single rate that applies to all
     *      users of that type unless a per-user override exists.
     *
     *   B. User-specific override ($userId = integer, $userType ignored):
     *      Upserts a per-user override via INSERT … ON DUPLICATE KEY UPDATE.
     *      The existing UNIQUE KEY (service_id, user_id) handles deduplication.
     *
     * Legacy global rows ($userId = null, $userType = '') are not written by
     * this method and are preserved untouched in the database.
     *
     * @param int|null $userId    NULL = type-based row; integer = per-user override.
     * @param int      $serviceId The service to configure.
     * @param float    $rate      Commission rate (0–100 percent).
     * @param string   $userType  'reseller' or 'api' — required when $userId is null.
     */
    public function upsert(?int $userId, int $serviceId, float $rate, string $userType = ''): void
    {
        if ($serviceId <= 0) {
            throw new \InvalidArgumentException('A valid service is required.');
        }
        if ($rate < 0 || $rate > 100) {
            throw new \InvalidArgumentException('Commission rate must be between 0 and 100 percent.');
        }

        if ($userId === null) {
            // Type-based row: require a valid user type.
            if (!in_array($userType, self::CONFIGURABLE_TYPES, true)) {
                throw new \InvalidArgumentException(
                    'A valid user type ("reseller" or "api") is required when setting a type-level commission rate.'
                );
            }

            // UPDATE-first strategy:
            //   MariaDB 10.4 does not fire ON DUPLICATE KEY when user_id IS NULL
            //   (NULL != NULL in UNIQUE indexes). We use the same safe UPDATE-first
            //   / INSERT-if-missed pattern that was already in use for legacy rows.
            $pdo  = $this->db->pdo();
            $stmt = $pdo->prepare(
                'UPDATE commissions SET rate_percent = :rate, updated_at = NOW()
                 WHERE service_id = :service_id AND user_id IS NULL AND user_type = :user_type'
            );
            $stmt->execute(['rate' => $rate, 'service_id' => $serviceId, 'user_type' => $userType]);

            if ($stmt->rowCount() === 0) {
                // No existing type row — insert one, guarded against a race condition.
                $pdo->prepare(
                    'INSERT INTO commissions (service_id, user_id, user_type, rate_percent)
                     SELECT :service_id, NULL, :user_type, :rate
                     FROM DUAL
                     WHERE NOT EXISTS (
                         SELECT 1 FROM commissions
                         WHERE service_id = :service_id2 AND user_id IS NULL AND user_type = :user_type2
                     )'
                )->execute([
                    'service_id'  => $serviceId,
                    'user_type'   => $userType,
                    'rate'        => $rate,
                    'service_id2' => $serviceId,
                    'user_type2'  => $userType,
                ]);
            }
        } else {
            // Per-user override — INSERT ON DUPLICATE KEY works because user_id is not NULL.
            $this->db->execute(
                'INSERT INTO commissions (service_id, user_id, rate_percent)
                 VALUES (:service_id, :user_id, :rate)
                 ON DUPLICATE KEY UPDATE rate_percent = VALUES(rate_percent), updated_at = NOW()',
                ['service_id' => $serviceId, 'user_id' => $userId, 'rate' => $rate]
            );
        }
    }

}
