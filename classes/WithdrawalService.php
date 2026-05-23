<?php

declare(strict_types=1);

namespace GemData\Classes;

class WithdrawalService
{
    public function __construct(
        private Database $db,
        private CommissionWallet $commissionWallet
    ) {
    }

    public function request(
        int $userId,
        float $amount,
        string $bankName,
        string $accountNumber,
        string $accountName
    ): array {
        $minimum = $this->minimumAmount();
        if ($amount < $minimum) {
            throw new \InvalidArgumentException('Your withdrawable commission is below the minimum withdrawal amount.');
        }

        $reference = strtoupper('WDR' . bin2hex(random_bytes(6)));

        $this->db->beginTransaction();
        try {
            $this->commissionWallet->ensure($userId);
            $wallet = $this->db->first(
                'SELECT * FROM commission_wallets WHERE user_id = :uid LIMIT 1 FOR UPDATE',
                ['uid' => $userId]
            );
            if (!$wallet || (float) $wallet['balance'] < $amount) {
                throw new \RuntimeException('Insufficient commission wallet balance.');
            }

            $pending = $this->db->first(
                'SELECT id FROM withdrawal_requests WHERE user_id = :uid AND status = :s LIMIT 1 FOR UPDATE',
                ['uid' => $userId, 's' => 'pending']
            );
            if ($pending) {
                throw new \RuntimeException('You already have a pending withdrawal request. Please wait for it to be reviewed.');
            }

            $this->db->execute(
                'INSERT INTO withdrawal_requests
                 (user_id, reference, amount, bank_name, account_number, account_name, status)
                 VALUES (:user_id, :ref, :amount, :bank, :acct_no, :acct_name, :status)',
                [
                    'user_id' => $userId,
                    'ref' => $reference,
                    'amount' => $amount,
                    'bank' => $bankName,
                    'acct_no' => $accountNumber,
                    'acct_name' => $accountName,
                    'status' => 'pending',
                ]
            );

            $requestId = $this->db->lastInsertId();
            $this->commissionWallet->debit(
                $userId,
                $amount,
                'Commission withdrawal reserved - Ref: ' . $reference,
                'wdr_request_' . $requestId
            );

            $record = $this->db->first(
                'SELECT * FROM withdrawal_requests WHERE id = :id LIMIT 1',
                ['id' => $requestId]
            ) ?? [];

            $this->db->commit();
            return $record;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function approve(int $requestId, int $adminId, string $note = ''): void
    {
        $this->db->beginTransaction();
        try {
            $req = $this->getForUpdate($requestId);
            if ($req['status'] !== 'pending') {
                throw new \RuntimeException('Only pending requests can be approved.');
            }

            if (!$this->hasWithdrawalReservation($requestId)) {
                $this->commissionWallet->debit(
                    (int) $req['user_id'],
                    (float) $req['amount'],
                    'Commission withdrawal reserved - Ref: ' . $req['reference'],
                    'wdr_request_' . $requestId
                );
            }

            $this->db->execute(
                'UPDATE withdrawal_requests
                    SET status = :s, admin_note = :note, reviewed_by_admin_id = :admin, reviewed_at = NOW()
                  WHERE id = :id',
                ['s' => 'approved', 'note' => $note, 'admin' => $adminId, 'id' => $requestId]
            );
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function markPaid(int $requestId, int $adminId): void
    {
        $req = $this->getOrFail($requestId);
        if ($req['status'] !== 'approved') {
            throw new \RuntimeException('Only approved requests can be marked as paid.');
        }

        $this->db->execute(
            'UPDATE withdrawal_requests SET status = :s, paid_at = NOW() WHERE id = :id',
            ['s' => 'paid', 'id' => $requestId]
        );
    }

    public function reject(int $requestId, int $adminId, string $reason): void
    {
        $this->db->beginTransaction();
        try {
            $req = $this->getForUpdate($requestId);
            if ($req['status'] !== 'pending') {
                throw new \RuntimeException('Only pending requests can be rejected.');
            }

            if ($this->hasWithdrawalReservation($requestId)) {
                $this->commissionWallet->restoreWithdrawal(
                    (int) $req['user_id'],
                    (float) $req['amount'],
                    'Commission withdrawal returned - Ref: ' . $req['reference'],
                    'wdr_reject_' . $requestId
                );
            }

            $this->db->execute(
                'UPDATE withdrawal_requests
                    SET status = :s, admin_note = :note, reviewed_by_admin_id = :admin, reviewed_at = NOW()
                  WHERE id = :id',
                ['s' => 'rejected', 'note' => $reason, 'admin' => $adminId, 'id' => $requestId]
            );
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function listPending(): array
    {
        return $this->db->safeQuery(
            'SELECT wr.*, u.full_name, u.email, u.phone
               FROM withdrawal_requests wr
               JOIN users u ON u.id = wr.user_id
              WHERE wr.status = :s
              ORDER BY wr.created_at ASC',
            ['s' => 'pending']
        );
    }

    public function listAll(int $limit = 50, int $offset = 0, ?string $status = null): array
    {
        $where = $status ? 'WHERE wr.status = :s' : '';
        $params = $status ? ['s' => $status, 'limit' => $limit, 'offset' => $offset] : ['limit' => $limit, 'offset' => $offset];
        return $this->db->safeQuery(
            "SELECT wr.*, u.full_name, u.email
               FROM withdrawal_requests wr
               JOIN users u ON u.id = wr.user_id
               {$where}
              ORDER BY wr.created_at DESC
              LIMIT :limit OFFSET :offset",
            $params
        );
    }

    public function listByUser(int $userId, int $limit = 20): array
    {
        return $this->db->safeQuery(
            'SELECT * FROM withdrawal_requests
              WHERE user_id = :uid
              ORDER BY created_at DESC
              LIMIT :limit',
            ['uid' => $userId, 'limit' => $limit]
        );
    }

    public function totalPaidOut(): float
    {
        $row = $this->db->first(
            'SELECT COALESCE(SUM(amount),0) AS total FROM withdrawal_requests WHERE status IN (:a,:b)',
            ['a' => 'approved', 'b' => 'paid']
        );
        return (float) ($row['total'] ?? 0);
    }

    public function minimumAmount(): float
    {
        $row = $this->db->safeFirst(
            'SELECT setting_value FROM system_settings WHERE setting_key = :key LIMIT 1',
            ['key' => 'commission_min_withdrawal']
        );
        if (!$row) {
            $this->db->safeExecute(
                'INSERT INTO system_settings (setting_key, setting_value, setting_group) VALUES (:key, :value, :group)',
                ['key' => 'commission_min_withdrawal', 'value' => '500', 'group' => 'commission']
            );
            return 500.0;
        }

        $minimum = (float) ($row['setting_value'] ?? 500);
        return $minimum > 0 ? $minimum : 500.0;
    }

    private function getOrFail(int $requestId): array
    {
        $req = $this->db->first(
            'SELECT * FROM withdrawal_requests WHERE id = :id LIMIT 1',
            ['id' => $requestId]
        );
        if (!$req) {
            throw new \RuntimeException('Withdrawal request not found.');
        }
        return $req;
    }

    private function getForUpdate(int $requestId): array
    {
        $req = $this->db->first(
            'SELECT * FROM withdrawal_requests WHERE id = :id LIMIT 1 FOR UPDATE',
            ['id' => $requestId]
        );
        if (!$req) {
            throw new \RuntimeException('Withdrawal request not found.');
        }
        return $req;
    }

    private function hasWithdrawalReservation(int $requestId): bool
    {
        $row = $this->db->safeFirst(
            'SELECT id FROM commission_wallet_transactions WHERE idempotency_key = :key LIMIT 1',
            ['key' => 'wdr_request_' . $requestId]
        );
        return $row !== null;
    }
}
