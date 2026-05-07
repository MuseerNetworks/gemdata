<?php

declare(strict_types=1);

namespace GemData\Classes;

class Wallet
{
    public function __construct(private Database $db)
    {
    }

    public function ensure(int $userId): array
    {
        $wallet = $this->db->first('SELECT * FROM wallets WHERE user_id = :user_id', ['user_id' => $userId]);
        if ($wallet) {
            return $wallet;
        }
        $this->db->execute('INSERT INTO wallets (user_id, balance) VALUES (:user_id, 0)', ['user_id' => $userId]);
        return $this->db->first('SELECT * FROM wallets WHERE user_id = :user_id', ['user_id' => $userId]);
    }

    public function balance(int $userId): float
    {
        $wallet = $this->ensure($userId);
        return (float) $wallet['balance'];
    }

    public function credit(int $userId, float $amount, string $narration, string $channel = 'web', array $meta = [], string $sourceType = 'legacy', ?int $sourceId = null, ?string $idempotencyKey = null, ?string $reasonCode = null): array
    {
        return $this->applyMutation($userId, 'credit', $amount, $narration, $channel, $meta, $sourceType, $sourceId, $idempotencyKey, $reasonCode);
    }

    public function debit(int $userId, float $amount, string $narration, string $channel = 'web', array $meta = [], string $sourceType = 'legacy', ?int $sourceId = null, ?string $idempotencyKey = null, ?string $reasonCode = null): array
    {
        return $this->applyMutation($userId, 'debit', $amount, $narration, $channel, $meta, $sourceType, $sourceId, $idempotencyKey, $reasonCode);
    }

    public function refund(int $userId, float $amount, string $narration, string $channel = 'web', array $meta = [], string $sourceType = 'legacy', ?int $sourceId = null, ?string $idempotencyKey = null, ?string $reasonCode = null): array
    {
        return $this->applyMutation($userId, 'refund', $amount, $narration, $channel, $meta, $sourceType, $sourceId, $idempotencyKey, $reasonCode);
    }

    private function applyMutation(int $userId, string $type, float $amount, string $narration, string $channel, array $meta, string $sourceType, ?int $sourceId, ?string $idempotencyKey, ?string $reasonCode): array
    {
        $amountMinor = $this->amountToMinor($amount);
        if ($amountMinor <= 0) {
            throw new \RuntimeException('Amount must be greater than zero.');
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            if ($idempotencyKey) {
                $existing = $this->db->first(
                    'SELECT * FROM wallet_transactions WHERE idempotency_key = :idempotency_key LIMIT 1',
                    ['idempotency_key' => $idempotencyKey]
                );
                if ($existing) {
                    if ($ownsTransaction) {
                        $this->db->commit();
                    }
                    return $existing;
                }
            }

            $wallet = $this->lockWallet($userId);
            $beforeMinor = $this->amountToMinor((float) $wallet['balance']);
            if ($type === 'debit' && $beforeMinor < $amountMinor) {
                throw new \RuntimeException('Insufficient wallet balance.');
            }

            $afterMinor = match ($type) {
                'credit', 'refund' => $beforeMinor + $amountMinor,
                'debit' => $beforeMinor - $amountMinor,
                default => throw new \RuntimeException('Unsupported wallet mutation type.'),
            };

            $reference = strtoupper('WAL' . bin2hex(random_bytes(6)));
            $beforeAmount = $this->minorToAmount($beforeMinor);
            $afterAmount = $this->minorToAmount($afterMinor);
            $amountValue = $this->minorToAmount($amountMinor);

            $this->db->execute(
                'UPDATE wallets SET balance = :balance WHERE id = :id',
                ['balance' => $afterAmount, 'id' => $wallet['id']]
            );
            $this->db->execute(
                'INSERT INTO wallet_transactions (user_id, wallet_id, reference, type, channel, amount, balance_before, balance_after, narration, source_type, source_id, idempotency_key, reason_code, meta_json)
                 VALUES (:user_id, :wallet_id, :reference, :type, :channel, :amount, :before, :after, :narration, :source_type, :source_id, :idempotency_key, :reason_code, :meta_json)',
                [
                    'user_id' => $userId,
                    'wallet_id' => $wallet['id'],
                    'reference' => $reference,
                    'type' => $type,
                    'channel' => $channel,
                    'amount' => $amountValue,
                    'before' => $beforeAmount,
                    'after' => $afterAmount,
                    'narration' => $narration,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'idempotency_key' => $idempotencyKey,
                    'reason_code' => $reasonCode,
                    'meta_json' => json_encode($meta),
                ]
            );
            $record = $this->db->first('SELECT * FROM wallet_transactions WHERE reference = :reference LIMIT 1', ['reference' => $reference]) ?? [];

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return $record;
        } catch (\Throwable $throwable) {
            if ($ownsTransaction) {
                $this->db->rollBack();
            }
            throw $throwable;
        }
    }

    private function lockWallet(int $userId): array
    {
        $this->db->execute('INSERT IGNORE INTO wallets (user_id, balance) VALUES (:user_id, 0.00)', ['user_id' => $userId]);
        $wallet = $this->db->first('SELECT * FROM wallets WHERE user_id = :user_id FOR UPDATE', ['user_id' => $userId]);
        if (!$wallet) {
            throw new \RuntimeException('Wallet record could not be loaded.');
        }

        return $wallet;
    }

    private function amountToMinor(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function minorToAmount(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }
}
