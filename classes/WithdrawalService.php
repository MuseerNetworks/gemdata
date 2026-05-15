<?php

declare(strict_types=1);

namespace GemData\Classes;

/**
 * WithdrawalService
 *
 * Handles reseller commission withdrawal requests.
 * Flow: reseller requests → admin reviews → admin approves/rejects
 * On approval: commission wallet is debited. Bank transfer is manual.
 */
class WithdrawalService
{
    public function __construct(
        private Database         $db,
        private CommissionWallet $commissionWallet
    ) {
    }

    // ── Reseller requests a withdrawal ────────────────────────────

    public function request(
        int    $userId,
        float  $amount,
        string $bankName,
        string $accountNumber,
        string $accountName
    ): array {
        if ($amount < 500) {
            throw new \InvalidArgumentException('Minimum withdrawal amount is ₦500.');
        }

        $balance = $this->commissionWallet->balance($userId);
        if ($balance < $amount) {
            throw new \RuntimeException('Insufficient commission wallet balance.');
        }

        // Prevent duplicate pending request
        $pending = $this->db->first(
            'SELECT id FROM withdrawal_requests WHERE user_id = :uid AND status = :s LIMIT 1',
            ['uid' => $userId, 's' => 'pending']
        );
        if ($pending) {
            throw new \RuntimeException('You already have a pending withdrawal request. Please wait for it to be reviewed.');
        }

        $reference = strtoupper('WDR' . bin2hex(random_bytes(6)));

        $this->db->execute(
            'INSERT INTO withdrawal_requests
             (user_id, reference, amount, bank_name, account_number, account_name, status)
             VALUES (:user_id, :ref, :amount, :bank, :acct_no, :acct_name, :status)',
            [
                'user_id'   => $userId,
                'ref'       => $reference,
                'amount'    => $amount,
                'bank'      => $bankName,
                'acct_no'   => $accountNumber,
                'acct_name' => $accountName,
                'status'    => 'pending',
            ]
        );

        return $this->db->first(
            'SELECT * FROM withdrawal_requests WHERE reference = :r LIMIT 1',
            ['r' => $reference]
        ) ?? [];
    }

    // ── Admin approves withdrawal ─────────────────────────────────

    public function approve(int $requestId, int $adminId, string $note = ''): void
    {
        $req = $this->getOrFail($requestId);
        if ($req['status'] !== 'pending') {
            throw new \RuntimeException('Only pending requests can be approved.');
        }

        // Debit commission wallet
        $this->commissionWallet->debit(
            (int) $req['user_id'],
            (float) $req['amount'],
            'Withdrawal approved — Ref: ' . $req['reference'],
            'wdr_approve_' . $requestId
        );

        $this->db->execute(
            'UPDATE withdrawal_requests
                SET status = :s, admin_note = :note, reviewed_by_admin_id = :admin, reviewed_at = NOW()
              WHERE id = :id',
            ['s' => 'approved', 'note' => $note, 'admin' => $adminId, 'id' => $requestId]
        );
    }

    // ── Admin marks as paid (after manual bank transfer) ─────────

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

    // ── Admin rejects withdrawal ──────────────────────────────────

    public function reject(int $requestId, int $adminId, string $reason): void
    {
        $req = $this->getOrFail($requestId);
        if ($req['status'] !== 'pending') {
            throw new \RuntimeException('Only pending requests can be rejected.');
        }
        $this->db->execute(
            'UPDATE withdrawal_requests
                SET status = :s, admin_note = :note, reviewed_by_admin_id = :admin, reviewed_at = NOW()
              WHERE id = :id',
            ['s' => 'rejected', 'note' => $reason, 'admin' => $adminId, 'id' => $requestId]
        );
    }

    // ── List queries ──────────────────────────────────────────────

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

    // ── Internal helpers ──────────────────────────────────────────

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
}
