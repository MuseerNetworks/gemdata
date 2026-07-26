<?php

declare(strict_types=1);

namespace GemData\Classes;

class Commission
{
    public function __construct(
        private Database          $db,
        private ?CommissionWallet $commissionWallet = null
    ) {
    }

    public function resolveRate(int $userId, int $serviceId): float
    {
        // Check for a user-specific override first
        $specific = $this->db->first(
            'SELECT rate_percent FROM commissions WHERE user_id = :user_id AND service_id = :service_id ORDER BY id DESC LIMIT 1',
            ['user_id' => $userId, 'service_id' => $serviceId]
        );
        if ($specific) {
            return (float) $specific['rate_percent'];
        }
        // Fall back to the global default rate for this service
        $default = $this->db->first(
            'SELECT rate_percent FROM commissions WHERE user_id IS NULL AND service_id = :service_id ORDER BY id DESC LIMIT 1',
            ['service_id' => $serviceId]
        );
        return $default ? (float) $default['rate_percent'] : 0.0;
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
     * Log commission AND credit the reseller's commission wallet.
     * Called by TransactionService on successful transactions for RESELLER users.
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

    public function upsert(?int $userId, int $serviceId, float $rate): void
    {
        if ($serviceId <= 0) {
            throw new \InvalidArgumentException('A valid service is required.');
        }
        if ($rate < 0 || $rate > 100) {
            throw new \InvalidArgumentException('Commission rate must be between 0 and 100 percent.');
        }

        // MariaDB 10.4 NOTE: The commissions table has a UNIQUE KEY on (service_id, user_id),
        // but MariaDB follows the SQL standard where NULL != NULL, so INSERT ... ON DUPLICATE
        // KEY UPDATE does NOT fire when user_id IS NULL. We therefore use an UPDATE-first
        // strategy: try to UPDATE the existing row, and only INSERT if no row was matched.
        if ($userId === null) {
            // Global (default) commission: user_id IS NULL
            // MariaDB 10.4: NULL != NULL in UNIQUE indexes, so INSERT...ON DUPLICATE KEY
            // does not fire for NULL user_id. Use UPDATE-first, INSERT-if-missed instead.
            $pdo  = $this->db->pdo();
            $stmt = $pdo->prepare(
                'UPDATE commissions SET rate_percent = :rate, updated_at = NOW()
                 WHERE service_id = :service_id AND user_id IS NULL'
            );
            $stmt->execute(['rate' => $rate, 'service_id' => $serviceId]);
            if ($stmt->rowCount() === 0) {
                // No existing global row — insert one, guarded against a race condition
                $pdo->prepare(
                    'INSERT INTO commissions (service_id, user_id, rate_percent)
                     SELECT :service_id, NULL, :rate
                     FROM DUAL
                     WHERE NOT EXISTS (
                         SELECT 1 FROM commissions
                         WHERE service_id = :service_id2 AND user_id IS NULL
                     )'
                )->execute(['service_id' => $serviceId, 'rate' => $rate, 'service_id2' => $serviceId]);
            }
        } else {
            // User-specific commission: user_id = :user_id — INSERT ON DUPLICATE KEY works here
            $this->db->execute(
                'INSERT INTO commissions (service_id, user_id, rate_percent)
                 VALUES (:service_id, :user_id, :rate)
                 ON DUPLICATE KEY UPDATE rate_percent = VALUES(rate_percent), updated_at = NOW()',
                ['service_id' => $serviceId, 'user_id' => $userId, 'rate' => $rate]
            );
        }
    }

}
