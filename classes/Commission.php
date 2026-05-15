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
        $specific = $this->db->first(
            'SELECT rate_percent FROM commissions WHERE user_id = :user_id AND service_id = :service_id LIMIT 1',
            ['user_id' => $userId, 'service_id' => $serviceId]
        );
        if ($specific) {
            return (float) $specific['rate_percent'];
        }
        $default = $this->db->first(
            'SELECT rate_percent FROM commissions WHERE user_id IS NULL AND service_id = :service_id LIMIT 1',
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
        $existing = $this->db->first(
            'SELECT id FROM commissions WHERE service_id = :service_id AND ((user_id IS NULL AND :user_id IS NULL) OR user_id = :user_id) LIMIT 1',
            ['service_id' => $serviceId, 'user_id' => $userId]
        );
        if ($existing) {
            $this->db->execute('UPDATE commissions SET rate_percent = :rate WHERE id = :id', [
                'rate' => $rate, 'id' => $existing['id'],
            ]);
            return;
        }
        $this->db->execute(
            'INSERT INTO commissions (service_id, user_id, rate_percent) VALUES (:service_id, :user_id, :rate)',
            ['service_id' => $serviceId, 'user_id' => $userId, 'rate' => $rate]
        );
    }
}
