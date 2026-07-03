<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class OwnerWithdrawalService
{
    public function __construct(
        private Database $db,
        private FinanceLedgerService $financeLedger
    ) {
    }

    public function request(
        int $adminId,
        float $amount,
        string $notes,
        string $withdrawalType = 'profit',
        array $transferDetails = []
    ): array {
        $this->assertReady();
        $amount = $this->validAmount($amount);
        $notes = $this->requireNotes($notes);
        $withdrawalType = $this->normalizeWithdrawalType($withdrawalType);
        $transferDetails = $this->normalizeTransferDetails($transferDetails, true);
        $overview = $this->financeLedger->overview();
        $limit = $withdrawalType === 'capital_return'
            ? (float) ($overview['available_capital'] ?? $overview['available_owner_capital'] ?? 0)
            : (float) ($overview['available_profit'] ?? $overview['available_owner_profit'] ?? 0);
        if ($amount > $limit) {
            throw new RuntimeException($withdrawalType === 'capital_return'
                ? 'Owner capital return exceeds available capital.'
                : 'Owner profit withdrawal exceeds available profit.');
        }

        $open = $this->db->first('SELECT id FROM owner_withdrawals WHERE status IN ("pending","approved") LIMIT 1');
        if ($open) {
            throw new RuntimeException('An owner transfer is already pending or approved.');
        }

        $reference = strtoupper('OWD' . bin2hex(random_bytes(6)));
        $this->db->execute(
            'INSERT INTO owner_withdrawals (
                reference, withdrawal_type, amount, status, bank_name, account_number,
                account_name, transfer_reference, bank_code, payout_provider, payout_status,
                payout_reference, requested_by_admin_id, notes
             ) VALUES (
                :reference, :withdrawal_type, :amount, "pending", :bank_name, :account_number,
                :account_name, :transfer_reference, :bank_code, :payout_provider, :payout_status,
                :payout_reference, :admin_id, :notes
             )',
            [
                'reference' => $reference,
                'withdrawal_type' => $withdrawalType,
                'amount' => $amount,
                'bank_name' => $transferDetails['bank_name'],
                'account_number' => $transferDetails['account_number'],
                'account_name' => $transferDetails['account_name'],
                'transfer_reference' => $transferDetails['transfer_reference'],
                'bank_code' => $transferDetails['bank_code'],
                'payout_provider' => $transferDetails['payout_provider'],
                'payout_status' => $transferDetails['payout_status'],
                'payout_reference' => $transferDetails['payout_reference'],
                'admin_id' => $adminId,
                'notes' => $notes,
            ]
        );

        return $this->get((int) $this->db->lastInsertId());
    }

    public function updatePayoutResult(int $withdrawalId, string $status, string $providerReference, array $safeResponse = [], ?string $failureReason = null): array
    {
        $this->assertReady();
        $status = $this->normalizePayoutStatus($status);
        $providerReference = $this->normalizeOptionalTransferReference($providerReference) ?? '';
        $failureReason = $failureReason !== null ? $this->safeText($failureReason, 255) : null;

        $this->db->execute(
            'UPDATE owner_withdrawals
             SET payout_status = :payout_status,
                 payout_reference = CASE WHEN :payout_reference_check <> "" THEN :payout_reference_value ELSE payout_reference END,
                 transfer_reference = CASE WHEN :transfer_reference_check <> "" THEN :transfer_reference_value ELSE transfer_reference END,
                 payout_response_json = :payout_response_json,
                 payout_requested_at = COALESCE(payout_requested_at, NOW()),
                 payout_failure_reason = :payout_failure_reason
             WHERE id = :id AND status IN ("pending","approved")',
            [
                'payout_status' => $status,
                'payout_reference_check' => $providerReference,
                'payout_reference_value' => $providerReference,
                'transfer_reference_check' => $providerReference,
                'transfer_reference_value' => $providerReference,
                'payout_response_json' => $safeResponse !== [] ? json_encode($safeResponse) : null,
                'payout_failure_reason' => $failureReason,
                'id' => $withdrawalId,
            ]
        );

        return $this->get($withdrawalId);
    }

    public function failPayout(int $withdrawalId, string $reason, array $safeResponse = []): void
    {
        $this->assertReady();
        $reason = $this->safeText($reason, 255) ?: 'KatPay payout failed.';
        $this->db->execute(
            'UPDATE owner_withdrawals
             SET status = "rejected", payout_status = "failed", reviewed_at = NOW(),
                 rejection_reason = :rejection_reason, payout_failure_reason = :payout_failure_reason,
                 payout_response_json = :payout_response_json
             WHERE id = :id AND status IN ("pending","approved")',
            [
                'rejection_reason' => $reason,
                'payout_failure_reason' => $reason,
                'payout_response_json' => $safeResponse !== [] ? json_encode($safeResponse) : null,
                'id' => $withdrawalId,
            ]
        );
    }

    public function markPaidFromPayout(int $withdrawalId, ?int $adminId, string $providerReference, array $safeResponse = []): void
    {
        $this->assertReady();
        $this->db->beginTransaction();
        try {
            $withdrawal = $this->db->first('SELECT * FROM owner_withdrawals WHERE id = :id FOR UPDATE', ['id' => $withdrawalId]);
            if (!$withdrawal || !in_array((string) ($withdrawal['status'] ?? ''), ['pending', 'approved'], true)) {
                throw new RuntimeException('Only unpaid owner transfers can be marked paid from payout confirmation.');
            }

            $this->assertStillWithinLimit($withdrawal);
            $reference = $this->normalizeOptionalTransferReference($providerReference) ?? (string) ($withdrawal['payout_reference'] ?? $withdrawal['transfer_reference'] ?? '');
            $this->db->execute(
                'UPDATE owner_withdrawals
                 SET status = "paid", payout_status = "successful", paid_by_admin_id = :admin_id,
                     paid_at = NOW(), payout_confirmed_at = NOW(),
                     payout_reference = CASE WHEN :payout_reference_check <> "" THEN :payout_reference_value ELSE payout_reference END,
                     transfer_reference = CASE WHEN :transfer_reference_check <> "" THEN :transfer_reference_value ELSE transfer_reference END,
                     payout_response_json = CASE WHEN :response_json_check IS NOT NULL THEN :response_json_value ELSE payout_response_json END,
                     payout_failure_reason = NULL
                  WHERE id = :id AND status IN ("pending","approved")',
                [
                    'admin_id' => $adminId,
                    'payout_reference_check' => $reference,
                    'payout_reference_value' => $reference,
                    'transfer_reference_check' => $reference,
                    'transfer_reference_value' => $reference,
                    'response_json_check' => $safeResponse !== [] ? json_encode($safeResponse) : null,
                    'response_json_value' => $safeResponse !== [] ? json_encode($safeResponse) : null,
                    'id' => $withdrawalId,
                ]
            );

            $withdrawal['status'] = 'paid';
            $withdrawal['transfer_reference'] = $reference !== '' ? $reference : ($withdrawal['transfer_reference'] ?? null);
            $this->financeLedger->recordOwnerWithdrawalPaid($withdrawal, $adminId);
            $this->db->commit();
        } catch (\Throwable $throwable) {
            $this->db->rollBack();
            throw $throwable;
        }
    }

    public function confirmPayoutByReference(string $providerReference, array $safeResponse = []): bool
    {
        $this->assertReady();
        $providerReference = $this->normalizeOptionalTransferReference($providerReference) ?? '';
        if ($providerReference === '') {
            return false;
        }

        $withdrawal = $this->db->first(
            'SELECT * FROM owner_withdrawals
             WHERE payout_provider = "katpay"
               AND (payout_reference = :payout_reference OR transfer_reference = :transfer_reference)
             ORDER BY id DESC
             LIMIT 1',
            ['payout_reference' => $providerReference, 'transfer_reference' => $providerReference]
        );
        if (!$withdrawal) {
            return false;
        }

        if (($withdrawal['status'] ?? '') === 'paid') {
            return true;
        }

        $this->markPaidFromPayout((int) $withdrawal['id'], null, $providerReference, $safeResponse);
        return true;
    }

    public function confirmPayoutPaidManually(int $withdrawalId, int $adminId): void
    {
        $this->assertReady();
        $this->db->beginTransaction();
        try {
            $withdrawal = $this->db->first('SELECT * FROM owner_withdrawals WHERE id = :id FOR UPDATE', ['id' => $withdrawalId]);
            if (!$withdrawal || !in_array((string) ($withdrawal['status'] ?? ''), ['pending', 'approved'], true)) {
                throw new RuntimeException('Only unpaid owner transfers can be confirmed as paid.');
            }
            $payoutProvider = (string) ($withdrawal['payout_provider'] ?? 'manual');
            if ($payoutProvider === 'manual') {
                throw new RuntimeException('Manual owner transfers must use the normal Mark Paid flow.');
            }
            $providerReference = $this->normalizeOptionalTransferReference($withdrawal['payout_reference'] ?? $withdrawal['transfer_reference'] ?? null) ?? '';
            $safeResponseJson = json_encode([
                'manual_recovery' => true,
                'provider' => $payoutProvider,
                'provider_reference' => $providerReference !== '' ? $providerReference : null,
                'confirmed_by_admin_id' => $adminId,
                'confirmed_at' => date(DATE_ATOM),
            ]);

            $this->db->execute(
                'UPDATE owner_withdrawals
                 SET status = "paid", payout_status = "successful",
                     reviewed_by_admin_id = COALESCE(reviewed_by_admin_id, :reviewed_admin_id),
                     reviewed_at = COALESCE(reviewed_at, NOW()),
                     paid_by_admin_id = :paid_admin_id,
                     paid_at = NOW(), payout_confirmed_at = NOW(),
                     payout_reference = CASE WHEN :payout_reference_check <> "" THEN :payout_reference_value ELSE payout_reference END,
                     transfer_reference = CASE WHEN :transfer_reference_check <> "" THEN :transfer_reference_value ELSE transfer_reference END,
                     payout_response_json = :response_json,
                     payout_failure_reason = NULL
                  WHERE id = :id AND status IN ("pending","approved")',
                [
                    'reviewed_admin_id' => $adminId,
                    'paid_admin_id' => $adminId,
                    'payout_reference_check' => $providerReference,
                    'payout_reference_value' => $providerReference,
                    'transfer_reference_check' => $providerReference,
                    'transfer_reference_value' => $providerReference,
                    'response_json' => $safeResponseJson,
                    'id' => $withdrawalId,
                ]
            );

            $withdrawal['status'] = 'paid';
            $withdrawal['payout_status'] = 'successful';
            if ($providerReference !== '') {
                $withdrawal['payout_reference'] = $providerReference;
                $withdrawal['transfer_reference'] = $providerReference;
            }
            $withdrawal['notes'] = 'Manual payout paid confirmation.';
            $this->financeLedger->recordOwnerWithdrawalPaid($withdrawal, $adminId);
            $this->db->commit();
        } catch (\Throwable $throwable) {
            $this->db->rollBack();
            throw $throwable;
        }
    }

    public function approve(int $withdrawalId, int $adminId, string $notes = ''): void
    {
        $this->assertReady();
        $this->db->execute(
            'UPDATE owner_withdrawals
             SET status = "approved", reviewed_by_admin_id = :admin_id, reviewed_at = NOW(),
                 notes = CASE WHEN :notes_check <> "" THEN :notes_value ELSE notes END
             WHERE id = :id AND status = "pending"',
            ['admin_id' => $adminId, 'notes_check' => substr(trim($notes), 0, 255), 'notes_value' => substr(trim($notes), 0, 255), 'id' => $withdrawalId]
        );
        if ($this->db->first('SELECT id FROM owner_withdrawals WHERE id = :id AND status = "approved"', ['id' => $withdrawalId]) === null) {
            throw new RuntimeException('Only pending owner transfers can be approved.');
        }
    }

    public function reject(int $withdrawalId, int $adminId, string $reason): void
    {
        $this->assertReady();
        $reason = $this->requireNotes($reason);
        $this->db->execute(
            'UPDATE owner_withdrawals
             SET status = "rejected", reviewed_by_admin_id = :admin_id, reviewed_at = NOW(), rejection_reason = :reason
             WHERE id = :id AND status IN ("pending","approved")',
            ['admin_id' => $adminId, 'reason' => $reason, 'id' => $withdrawalId]
        );
        if ($this->db->first('SELECT id FROM owner_withdrawals WHERE id = :id AND status = "rejected"', ['id' => $withdrawalId]) === null) {
            throw new RuntimeException('Owner transfer could not be rejected.');
        }
    }

    public function markPaid(int $withdrawalId, int $adminId, ?string $transferReference = null): void
    {
        $this->assertReady();
        $this->db->beginTransaction();
        try {
            $withdrawal = $this->db->first('SELECT * FROM owner_withdrawals WHERE id = :id FOR UPDATE', ['id' => $withdrawalId]);
            if (!$withdrawal || ($withdrawal['status'] ?? '') !== 'approved') {
                throw new RuntimeException('Only approved owner transfers can be marked paid.');
            }
            if (($withdrawal['payout_provider'] ?? 'manual') === 'katpay') {
                throw new RuntimeException('KatPay owner transfers can only be marked paid after KatPay confirms payout success.');
            }

            $this->assertStillWithinLimit($withdrawal);

            $this->db->execute(
                'UPDATE owner_withdrawals
                 SET status = "paid", paid_by_admin_id = :admin_id, paid_at = NOW(),
                     transfer_reference = CASE
                         WHEN :transfer_reference_value IS NOT NULL AND :transfer_reference_check <> "" THEN :transfer_reference_assign
                         ELSE transfer_reference
                     END
                 WHERE id = :id AND status = "approved"',
                [
                    'admin_id' => $adminId,
                    'transfer_reference_value' => $this->normalizeOptionalTransferReference($transferReference),
                    'transfer_reference_check' => $this->normalizeOptionalTransferReference($transferReference) ?? '',
                    'transfer_reference_assign' => $this->normalizeOptionalTransferReference($transferReference),
                    'id' => $withdrawalId,
                ]
            );
            $withdrawal['status'] = 'paid';
            if ($transferReference !== null && trim($transferReference) !== '') {
                $withdrawal['transfer_reference'] = $this->normalizeOptionalTransferReference($transferReference);
            }
            $this->financeLedger->recordOwnerWithdrawalPaid($withdrawal, $adminId);
            $this->db->commit();
        } catch (\Throwable $throwable) {
            $this->db->rollBack();
            throw $throwable;
        }
    }

    public function recent(int $limit = 20): array
    {
        if (!$this->db->tableExists('owner_withdrawals')) {
            return [];
        }

        return $this->db->query(
            'SELECT ow.*, req.full_name AS requested_by_name, rev.full_name AS reviewed_by_name, paid.full_name AS paid_by_name
             FROM owner_withdrawals ow
             INNER JOIN admins req ON req.id = ow.requested_by_admin_id
             LEFT JOIN admins rev ON rev.id = ow.reviewed_by_admin_id
             LEFT JOIN admins paid ON paid.id = ow.paid_by_admin_id
             ORDER BY ow.id DESC
             LIMIT ' . max(1, $limit)
        );
    }

    private function get(int $id): array
    {
        return $this->db->first('SELECT * FROM owner_withdrawals WHERE id = :id LIMIT 1', ['id' => $id]) ?? [];
    }

    private function assertReady(): void
    {
        if (!$this->financeLedger->tablesReady()) {
            throw new RuntimeException('Finance ledger tables are not installed yet. Run the finance ledger migration first.');
        }
    }

    private function validAmount(float $amount): float
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new RuntimeException('Amount must be greater than zero.');
        }

        return $amount;
    }

    private function requireNotes(string $notes): string
    {
        $notes = trim($notes);
        if ($notes === '') {
            throw new RuntimeException('A note is required.');
        }

        return substr($notes, 0, 255);
    }

    private function normalizeWithdrawalType(string $type): string
    {
        $type = strtolower(trim($type));
        if (!in_array($type, ['profit', 'capital_return'], true)) {
            throw new RuntimeException('Unsupported owner transfer type.');
        }

        return $type;
    }

    private function normalizeTransferDetails(array $details, bool $requireDestination): array
    {
        $bankName = $this->safeText((string) ($details['bank_name'] ?? ''), 120);
        $accountNumber = $this->normalizeAccountNumber((string) ($details['account_number'] ?? ''));
        $accountName = $this->safeText((string) ($details['account_name'] ?? ''), 160);
        if ($requireDestination && ($bankName === '' || $accountNumber === '' || $accountName === '')) {
            throw new RuntimeException('Bank name, account number, and account name are required for owner transfer.');
        }

        return [
            'bank_name' => $bankName,
            'account_number' => $accountNumber,
            'account_name' => $accountName,
            'bank_code' => $this->safeText((string) ($details['bank_code'] ?? ''), 60),
            'transfer_reference' => $this->normalizeOptionalTransferReference($details['transfer_reference'] ?? null),
            'payout_provider' => $this->safeText((string) ($details['payout_provider'] ?? 'manual'), 40) ?: 'manual',
            'payout_status' => $this->normalizePayoutStatus((string) ($details['payout_status'] ?? 'not_requested')),
            'payout_reference' => $this->normalizeOptionalTransferReference($details['payout_reference'] ?? null),
        ];
    }

    private function normalizeAccountNumber(string $accountNumber): string
    {
        $accountNumber = preg_replace('/\s+/', ' ', trim($accountNumber)) ?? '';
        if ($accountNumber === '') {
            return '';
        }
        if (!preg_match('/^[0-9 \-]+$/', $accountNumber)) {
            throw new RuntimeException('Account number may contain only digits, spaces, and hyphens.');
        }

        return substr($accountNumber, 0, 40);
    }

    private function normalizePayoutStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if ($status === '') {
            return 'not_requested';
        }
        if (!in_array($status, ['not_requested', 'processing', 'successful', 'failed'], true)) {
            return 'processing';
        }

        return $status;
    }

    private function assertStillWithinLimit(array $withdrawal): void
    {
        $overview = $this->financeLedger->overview();
        $withdrawalType = $this->normalizeWithdrawalType((string) ($withdrawal['withdrawal_type'] ?? 'profit'));
        $limit = $withdrawalType === 'capital_return'
            ? (float) ($overview['available_capital'] ?? $overview['available_owner_capital'] ?? 0)
            : (float) ($overview['available_profit'] ?? $overview['available_owner_profit'] ?? 0);
        if ((float) $withdrawal['amount'] > $limit) {
            throw new RuntimeException($withdrawalType === 'capital_return'
                ? 'Owner capital return exceeds available capital.'
                : 'Owner profit withdrawal exceeds available profit.');
        }
    }

    private function normalizeOptionalTransferReference(?string $reference): ?string
    {
        $reference = $this->safeText((string) ($reference ?? ''), 120);
        return $reference !== '' ? $reference : null;
    }

    private function safeText(string $value, int $maxLength): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';
        $value = preg_replace('/[^\pL\pN .,_@#\/:\-]/u', '', $value) ?? '';
        return substr($value, 0, $maxLength);
    }
}
