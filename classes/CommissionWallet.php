<?php

declare(strict_types=1);

namespace GemData\Classes;

/**
 * CommissionWallet
 *
 * Manages the separate commission balance for reseller users.
 * Completely isolated from the main wallet — no shared ledger.
 * All mutations are atomic and fully audited.
 */
class CommissionWallet
{
    public function __construct(private Database $db)
    {
    }

    // ── Ensure commission wallet row exists ───────────────────────

    public function ensure(int $userId): array
    {
        $this->db->execute(
            'INSERT IGNORE INTO commission_wallets (user_id, balance) VALUES (:user_id, 0.00)',
            ['user_id' => $userId]
        );
        return $this->db->first(
            'SELECT * FROM commission_wallets WHERE user_id = :user_id',
            ['user_id' => $userId]
        );
    }

    // ── Read balance ──────────────────────────────────────────────

    public function balance(int $userId): float
    {
        $wallet = $this->ensure($userId);
        return (float) $wallet['balance'];
    }

    // ── Credit (commission earned) ────────────────────────────────

    public function credit(
        int     $userId,
        float   $amount,
        string  $narration,
        ?int    $transactionId = null,
        ?string $idempotencyKey = null
    ): array {
        return $this->applyMutation('credit', $userId, $amount, $narration, 'commission', $transactionId, $idempotencyKey);
    }

    // ── Debit (withdrawal approved) ───────────────────────────────

    public function debit(
        int     $userId,
        float   $amount,
        string  $narration,
        ?string $idempotencyKey = null
    ): array {
        return $this->applyMutation('withdrawal', $userId, $amount, $narration, 'withdrawal', null, $idempotencyKey);
    }

    // ── Transaction history ───────────────────────────────────────

    public function history(int $userId, int $limit = 20, int $offset = 0): array
    {
        return $this->db->safeQuery(
            'SELECT * FROM commission_wallet_transactions
              WHERE user_id = :user_id
              ORDER BY created_at DESC
              LIMIT :limit OFFSET :offset',
            ['user_id' => $userId, 'limit' => $limit, 'offset' => $offset]
        );
    }

    public function totalEarned(int $userId): float
    {
        $row = $this->db->first(
            'SELECT COALESCE(SUM(amount),0) AS total
               FROM commission_wallet_transactions
              WHERE user_id = :user_id AND type = :type',
            ['user_id' => $userId, 'type' => 'credit']
        );
        return (float) ($row['total'] ?? 0);
    }

    public function totalWithdrawn(int $userId): float
    {
        $row = $this->db->first(
            'SELECT COALESCE(SUM(amount),0) AS total
               FROM commission_wallet_transactions
              WHERE user_id = :user_id AND type = :type',
            ['user_id' => $userId, 'type' => 'withdrawal']
        );
        return (float) ($row['total'] ?? 0);
    }

    // ── Internal mutation ─────────────────────────────────────────

    private function applyMutation(
        string  $type,
        int     $userId,
        float   $amount,
        string  $narration,
        string  $sourceType,
        ?int    $transactionId,
        ?string $idempotencyKey
    ): array {
        $amountMinor = $this->toMinor($amount);
        if ($amountMinor <= 0) {
            throw new \RuntimeException('Commission wallet amount must be greater than zero.');
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            // Idempotency check
            if ($idempotencyKey) {
                $existing = $this->db->first(
                    'SELECT * FROM commission_wallet_transactions WHERE idempotency_key = :k LIMIT 1',
                    ['k' => $idempotencyKey]
                );
                if ($existing) {
                    if ($ownsTransaction) {
                        $this->db->commit();
                    }
                    return $existing;
                }
            }

            // Lock row
            $this->ensure($userId);
            $wallet = $this->db->first(
                'SELECT * FROM commission_wallets WHERE user_id = :uid FOR UPDATE',
                ['uid' => $userId]
            );

            $beforeMinor = $this->toMinor((float) $wallet['balance']);
            $isDebit     = in_array($type, ['withdrawal', 'debit'], true);

            if ($isDebit && $beforeMinor < $amountMinor) {
                throw new \RuntimeException('Insufficient commission wallet balance.');
            }

            $afterMinor = $isDebit ? $beforeMinor - $amountMinor : $beforeMinor + $amountMinor;
            $reference  = strtoupper('CWL' . bin2hex(random_bytes(6)));

            $this->db->execute(
                'UPDATE commission_wallets SET balance = :b WHERE id = :id',
                ['b' => $this->fromMinor($afterMinor), 'id' => $wallet['id']]
            );

            $this->db->execute(
                'INSERT INTO commission_wallet_transactions
                 (user_id, wallet_id, reference, type, amount, balance_before, balance_after, narration, source_type, transaction_id, idempotency_key)
                 VALUES (:user_id, :wallet_id, :ref, :type, :amount, :before, :after, :narration, :src, :tx_id, :ikey)',
                [
                    'user_id'   => $userId,
                    'wallet_id' => $wallet['id'],
                    'ref'       => $reference,
                    'type'      => $type,
                    'amount'    => $this->fromMinor($amountMinor),
                    'before'    => $this->fromMinor($beforeMinor),
                    'after'     => $this->fromMinor($afterMinor),
                    'narration' => $narration,
                    'src'       => $sourceType,
                    'tx_id'     => $transactionId,
                    'ikey'      => $idempotencyKey,
                ]
            );

            $record = $this->db->first(
                'SELECT * FROM commission_wallet_transactions WHERE reference = :r LIMIT 1',
                ['r' => $reference]
            ) ?? [];

            if ($ownsTransaction) {
                $this->db->commit();
            }
            return $record;

        } catch (\Throwable $e) {
            if ($ownsTransaction) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function toMinor(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function fromMinor(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }
}
