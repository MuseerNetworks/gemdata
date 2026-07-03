<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class FinanceLedgerService
{
    public function __construct(private Database $db)
    {
    }

    public function tablesReady(): bool
    {
        return $this->db->tableExists('business_cash_ledger')
            && $this->db->tableExists('provider_wallet_ledger')
            && $this->db->tableExists('owner_balance_ledger')
            && $this->db->tableExists('finance_opening_reconciliation')
            && $this->db->tableExists('owner_withdrawals')
            && $this->db->columnExists('owner_withdrawals', 'withdrawal_type')
            && $this->db->columnExists('owner_withdrawals', 'bank_code')
            && $this->db->columnExists('owner_withdrawals', 'payout_status')
            && $this->db->columnExists('owner_withdrawals', 'payout_reference');
    }

    public function overview(): array
    {
        $businessCash = $this->signedSum('business_cash_ledger');
        $userLiability = $this->userLiability();
        $pendingExposure = $this->sum('SELECT COALESCE(SUM(amount),0) AS total FROM transactions WHERE status = "pending"');
        $providerPrepaid = $this->visibleProviderPrepaidBalance();
        $grossRevenue = $this->sum('SELECT COALESCE(SUM(selling_price),0) AS total FROM transactions WHERE status = "successful"');
        $providerCosts = $this->sum(
            'SELECT COALESCE(SUM(CASE WHEN pwl.id IS NOT NULL THEN pwl.amount ELSE t.cost_price END),0) AS total
             FROM transactions t
             LEFT JOIN provider_wallet_ledger pwl
               ON pwl.transaction_id = t.id
              AND pwl.entry_type = "transaction_cost"
              AND pwl.direction = "out"
             WHERE t.status = "successful"'
        );
        $confirmedProfit = $grossRevenue - $providerCosts;
        $availableOwnerCapital = $this->ownerBalance('capital');
        $availableOwnerProfit = $this->ownerBalance('profit');
        $profitWithdrawn = $this->sumOwnerBalance('profit', 'out', 'owner_withdrawal');
        $capitalReturned = $this->sumOwnerBalance('capital', 'out', 'owner_withdrawal');
        $safeAvailableCash = max(0.0, round($businessCash - $userLiability - $pendingExposure, 2));
        $profitWithdrawable = max(0.0, min($availableOwnerProfit, $safeAvailableCash));
        $capitalReturnWithdrawable = max(0.0, min($availableOwnerCapital, $safeAvailableCash));

        return [
            'business_cash' => round($businessCash, 2),
            'user_liability' => round($userLiability, 2),
            'pending_exposure' => round($pendingExposure, 2),
            'safe_available_cash' => $safeAvailableCash,
            'provider_prepaid_balance' => round($providerPrepaid, 2),
            'gross_revenue' => round($grossRevenue, 2),
            'provider_costs' => round($providerCosts, 2),
            'confirmed_profit' => round($confirmedProfit, 2),
            'profit_withdrawn' => round($profitWithdrawn, 2),
            'available_owner_profit' => $availableOwnerProfit,
            'available_profit' => $availableOwnerProfit,
            'profit_withdrawable' => $profitWithdrawable,
            'available_owner_capital' => $availableOwnerCapital,
            'available_capital' => $availableOwnerCapital,
            'capital_returned' => round($capitalReturned, 2),
            'capital_return_withdrawable' => $capitalReturnWithdrawable,
            'owner_withdrawn' => round($profitWithdrawn + $capitalReturned, 2),
            'owner_withdrawable_profit' => $profitWithdrawable,
            'total_owner_withdrawable' => min(round($profitWithdrawable + $capitalReturnWithdrawable, 2), $safeAvailableCash),
            'opening_reconciliation_done' => $this->openingReconciliationDone(),
        ];
    }

    public function providerBalances(): array
    {
        if (!$this->db->tableExists('provider_wallet_ledger')) {
            return [];
        }

        $rows = $this->db->query(
            'SELECT pa.id, pa.name, pa.code, pa.status, pa.current_balance, pa.balance_refreshed_at,
                    COALESCE(SUM(CASE WHEN pwl.direction = "in" THEN pwl.amount ELSE -pwl.amount END),0) AS ledger_balance
             FROM provider_accounts pa
             LEFT JOIN provider_wallet_ledger pwl ON pwl.provider_account_id = pa.id
             WHERE pa.status <> "archived"
             GROUP BY pa.id
             ORDER BY pa.priority_order ASC, pa.id ASC'
        );

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'code' => (string) $row['code'],
                'status' => (string) $row['status'],
                'ledger_balance' => round((float) $row['ledger_balance'], 2),
                'current_balance' => $row['current_balance'] !== null ? (float) $row['current_balance'] : null,
                'balance_refreshed_at' => $row['balance_refreshed_at'] ?? null,
            ];
        }, $rows);
    }

    public function recentBusinessLedger(int $limit = 20): array
    {
        if (!$this->db->tableExists('business_cash_ledger')) {
            return [];
        }

        return $this->db->query(
            'SELECT bcl.*, pa.name AS provider_name
             FROM business_cash_ledger bcl
             LEFT JOIN provider_accounts pa ON pa.id = bcl.provider_account_id
             ORDER BY bcl.id DESC
             LIMIT ' . max(1, $limit)
        );
    }

    public function recentProviderLedger(int $limit = 20): array
    {
        if (!$this->db->tableExists('provider_wallet_ledger')) {
            return [];
        }

        return $this->db->query(
            'SELECT pwl.*, pa.name AS provider_name, t.reference AS transaction_reference
             FROM provider_wallet_ledger pwl
             INNER JOIN provider_accounts pa ON pa.id = pwl.provider_account_id
             LEFT JOIN transactions t ON t.id = pwl.transaction_id
             ORDER BY pwl.id DESC
             LIMIT ' . max(1, $limit)
        );
    }

    public function recentOwnerBalanceLedger(int $limit = 20): array
    {
        if (!$this->db->tableExists('owner_balance_ledger')) {
            return [];
        }

        return $this->db->query(
            'SELECT obl.*, t.reference AS transaction_reference, ow.reference AS owner_withdrawal_reference, a.full_name AS admin_name
             FROM owner_balance_ledger obl
             LEFT JOIN transactions t ON t.id = obl.transaction_id
             LEFT JOIN owner_withdrawals ow ON ow.id = obl.owner_withdrawal_id
             LEFT JOIN admins a ON a.id = obl.created_by_admin_id
             ORDER BY obl.id DESC
             LIMIT ' . max(1, $limit)
        );
    }

    public function openingReconciliationDone(): bool
    {
        if (!$this->db->tableExists('finance_opening_reconciliation')) {
            return false;
        }

        return (int) ($this->db->first('SELECT COUNT(*) AS total FROM finance_opening_reconciliation')['total'] ?? 0) > 0;
    }

    public function openingReconciliation(): ?array
    {
        if (!$this->db->tableExists('finance_opening_reconciliation')) {
            return null;
        }

        return $this->db->first(
            'SELECT forr.*, a.full_name AS admin_name
             FROM finance_opening_reconciliation forr
             INNER JOIN admins a ON a.id = forr.created_by_admin_id
             ORDER BY forr.id ASC
             LIMIT 1'
        );
    }

    public function initializeOpeningBalances(float $openingCapital, float $openingProfit, int $adminId, string $notes): void
    {
        $this->assertTablesReady();
        $openingCapital = $this->validNonNegativeAmount($openingCapital);
        $openingProfit = $this->validNonNegativeAmount($openingProfit);
        $notes = $this->requireNotes($notes);
        if ($openingCapital <= 0 && $openingProfit <= 0) {
            throw new RuntimeException('Opening capital or opening profit must be greater than zero.');
        }
        if ($this->openingReconciliationDone()) {
            throw new RuntimeException('Opening capital and profit have already been initialized.');
        }

        $this->db->beginTransaction();
        try {
            $reference = strtoupper('FOR' . bin2hex(random_bytes(6)));
            $this->db->execute(
                'INSERT INTO finance_opening_reconciliation
                    (reference, opening_capital, opening_profit, notes, created_by_admin_id)
                 VALUES
                    (:reference, :opening_capital, :opening_profit, :notes, :admin_id)',
                [
                    'reference' => $reference,
                    'opening_capital' => $openingCapital,
                    'opening_profit' => $openingProfit,
                    'notes' => $notes,
                    'admin_id' => $adminId,
                ]
            );
            $openingId = $this->db->lastInsertId();
            if ($openingCapital > 0) {
                $businessId = $this->insertBusinessCash('opening_capital', 'in', $openingCapital, $adminId, 'Opening capital: ' . $notes, null, 'finance-opening:capital', 'finance_opening_reconciliation', $openingId);
                $this->insertOwnerBalance('capital', 'opening', 'in', $openingCapital, null, null, $businessId, $openingId, $adminId, 'Opening capital: ' . $notes, 'owner-opening:capital');
            }
            if ($openingProfit > 0) {
                $businessId = $this->insertBusinessCash('opening_profit', 'in', $openingProfit, $adminId, 'Opening profit: ' . $notes, null, 'finance-opening:profit', 'finance_opening_reconciliation', $openingId);
                $this->insertOwnerBalance('profit', 'opening', 'in', $openingProfit, null, null, $businessId, $openingId, $adminId, 'Opening profit: ' . $notes, 'owner-opening:profit');
            }
            $this->db->commit();
        } catch (\Throwable $throwable) {
            $this->db->rollBack();
            throw $throwable;
        }
    }

    public function resetOpeningBalances(float $newOpeningCapital, float $newOpeningProfit, int $adminId, string $notes): array
    {
        $this->assertTablesReady();
        $newOpeningCapital = $this->validNonNegativeAmount($newOpeningCapital);
        $newOpeningProfit = $this->validNonNegativeAmount($newOpeningProfit);
        $notes = $this->requireNotes($notes);

        $this->db->beginTransaction();
        try {
            $opening = $this->db->first(
                'SELECT * FROM finance_opening_reconciliation ORDER BY id ASC LIMIT 1 FOR UPDATE'
            );
            if (!$opening) {
                throw new RuntimeException('Opening capital and profit must be initialized before they can be reset.');
            }

            $openingId = (int) $opening['id'];
            $oldOpeningCapital = round((float) ($opening['opening_capital'] ?? 0), 2);
            $oldOpeningProfit = round((float) ($opening['opening_profit'] ?? 0), 2);
            $capitalDelta = round($newOpeningCapital - $oldOpeningCapital, 2);
            $profitDelta = round($newOpeningProfit - $oldOpeningProfit, 2);

            if ($capitalDelta == 0.0 && $profitDelta == 0.0) {
                $this->db->commit();
                return [
                    'opening_id' => $openingId,
                    'old_opening_capital' => $oldOpeningCapital,
                    'new_opening_capital' => $newOpeningCapital,
                    'capital_delta' => 0.0,
                    'old_opening_profit' => $oldOpeningProfit,
                    'new_opening_profit' => $newOpeningProfit,
                    'profit_delta' => 0.0,
                    'changed' => false,
                ];
            }

            $this->db->execute(
                'UPDATE finance_opening_reconciliation
                 SET opening_capital = :opening_capital,
                     opening_profit = :opening_profit,
                     notes = :notes
                 WHERE id = :id',
                [
                    'opening_capital' => $newOpeningCapital,
                    'opening_profit' => $newOpeningProfit,
                    'notes' => substr('Reset: ' . $notes, 0, 255),
                    'id' => $openingId,
                ]
            );

            $resetReference = strtolower(bin2hex(random_bytes(6)));
            if ($capitalDelta != 0.0) {
                $direction = $capitalDelta > 0 ? 'in' : 'out';
                $amount = abs($capitalDelta);
                $resetNotes = substr(sprintf('Opening capital reset from %0.2f to %0.2f. Reason: %s', $oldOpeningCapital, $newOpeningCapital, $notes), 0, 255);
                $businessId = $this->insertBusinessCash('manual_adjustment', $direction, $amount, $adminId, $resetNotes, null, 'finance-opening-reset:' . $openingId . ':' . $resetReference . ':capital');
                $this->insertOwnerBalance('capital', 'admin_reset', $direction, $amount, null, null, $businessId, $openingId, $adminId, $resetNotes, 'owner-opening-reset:' . $openingId . ':' . $resetReference . ':capital');
            }

            if ($profitDelta != 0.0) {
                $direction = $profitDelta > 0 ? 'in' : 'out';
                $amount = abs($profitDelta);
                $resetNotes = substr(sprintf('Opening profit reset from %0.2f to %0.2f. Reason: %s', $oldOpeningProfit, $newOpeningProfit, $notes), 0, 255);
                $businessId = $this->insertBusinessCash('manual_adjustment', $direction, $amount, $adminId, $resetNotes, null, 'finance-opening-reset:' . $openingId . ':' . $resetReference . ':profit');
                $this->insertOwnerBalance('profit', 'admin_reset', $direction, $amount, null, null, $businessId, $openingId, $adminId, $resetNotes, 'owner-opening-reset:' . $openingId . ':' . $resetReference . ':profit');
            }

            $this->db->commit();
            return [
                'opening_id' => $openingId,
                'old_opening_capital' => $oldOpeningCapital,
                'new_opening_capital' => $newOpeningCapital,
                'capital_delta' => $capitalDelta,
                'old_opening_profit' => $oldOpeningProfit,
                'new_opening_profit' => $newOpeningProfit,
                'profit_delta' => $profitDelta,
                'changed' => true,
            ];
        } catch (\Throwable $throwable) {
            $this->db->rollBack();
            throw $throwable;
        }
    }

    public function recordUserFunding(array $fundingRequest, ?int $adminId = null): void
    {
        if (!$this->db->tableExists('business_cash_ledger')) {
            return;
        }

        $requestId = (int) ($fundingRequest['id'] ?? 0);
        $amount = round((float) ($fundingRequest['amount'] ?? 0), 2);
        if ($requestId <= 0 || $amount <= 0) {
            return;
        }

        $this->db->safeExecute(
            'INSERT IGNORE INTO business_cash_ledger
                (reference, entry_type, direction, amount, source_table, source_id, notes, created_by_admin_id)
             VALUES
                (:reference, "user_funding_received", "in", :amount, "wallet_funding_requests", :source_id, :notes, :admin_id)',
            [
                'reference' => 'BCL-FUND-' . $requestId,
                'amount' => $amount,
                'source_id' => $requestId,
                'notes' => 'User wallet funding received.',
                'admin_id' => $adminId,
            ]
        );
    }

    public function recordTransactionCost(array $transaction): void
    {
        if (!$this->db->tableExists('provider_wallet_ledger')) {
            return;
        }

        $transactionId = (int) ($transaction['id'] ?? 0);
        $providerId = (int) ($transaction['provider_account_id'] ?? 0);
        $providerCode = trim((string) ($transaction['provider_code'] ?? ''));
        $cost = round((float) ($transaction['cost_price'] ?? 0), 2);
        if ($transactionId <= 0 || $providerId <= 0 || $providerCode === '' || $cost <= 0) {
            return;
        }
        $existing = $this->db->first(
            'SELECT id FROM provider_wallet_ledger WHERE transaction_id = :transaction_id AND entry_type = "transaction_cost" LIMIT 1',
            ['transaction_id' => $transactionId]
        );
        if ($existing) {
            return;
        }

        $balanceBefore = $this->providerLedgerBalance($providerId);
        $balanceAfter = round($balanceBefore - $cost, 2);
        $this->db->safeExecute(
            'INSERT IGNORE INTO provider_wallet_ledger
                (reference, provider_account_id, provider_code, entry_type, direction, amount, balance_before, balance_after, transaction_id, notes)
             VALUES
                (:reference, :provider_account_id, :provider_code, "transaction_cost", "out", :amount, :before, :after, :transaction_id, :notes)',
            [
                'reference' => 'PWL-TXN-' . $transactionId,
                'provider_account_id' => $providerId,
                'provider_code' => $providerCode,
                'amount' => $cost,
                'before' => $balanceBefore,
                'after' => $balanceAfter,
                'transaction_id' => $transactionId,
                'notes' => 'Provider cost for successful transaction.',
            ]
        );
        $provider = $this->db->first('SELECT current_balance FROM provider_accounts WHERE id = :id LIMIT 1', ['id' => $providerId]);
        if ($provider && $provider['current_balance'] !== null) {
            $this->writeProviderBalance($providerId, round((float) $provider['current_balance'] - $cost, 2), 'transaction_cost', 'Provider cost for transaction ' . $transactionId);
        }
    }

    public function recordTransactionOwnerBalances(array $transaction): void
    {
        if (!$this->db->tableExists('owner_balance_ledger')) {
            return;
        }

        $transactionId = (int) ($transaction['id'] ?? 0);
        if ($transactionId <= 0 || (string) ($transaction['status'] ?? '') !== 'successful') {
            return;
        }

        $capitalAmount = round((float) ($transaction['cost_price'] ?? 0), 2);
        $profitAmount = round((float) ($transaction['profit_amount'] ?? 0), 2);
        if ($capitalAmount <= 0 && $profitAmount <= 0) {
            return;
        }

        if ($capitalAmount > 0) {
            $this->insertOwnerBalance('capital', 'transaction_success', 'in', $capitalAmount, $transactionId, null, null, null, null, 'Provider-cost portion released from successful transaction.', 'owner-txn:capital:' . $transactionId);
        }
        if ($profitAmount > 0) {
            $this->insertOwnerBalance('profit', 'transaction_success', 'in', $profitAmount, $transactionId, null, null, null, null, 'Profit portion released from successful transaction.', 'owner-txn:profit:' . $transactionId);
        }
    }

    public function fundProvider(int $providerId, float $amount, int $adminId, string $notes, ?string $idempotencyKey = null): void
    {
        $this->assertTablesReady();
        $provider = $this->providerOrFail($providerId);
        $amount = $this->validAmount($amount);
        $notes = $this->requireNotes($notes);
        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey, 'finance-provider-fund');
        if ($this->alreadyRecorded('business_cash_ledger', $idempotencyKey) || $this->alreadyRecorded('provider_wallet_ledger', $idempotencyKey)) {
            return;
        }
        $overview = $this->overview();
        if ($amount > (float) $overview['safe_available_cash']) {
            throw new RuntimeException('Provider funding would exceed safe available business cash.');
        }

        $this->db->beginTransaction();
        try {
            $businessId = $this->insertBusinessCash('provider_wallet_funded', 'out', $amount, $adminId, $notes, $providerId, $idempotencyKey);
            $before = $this->providerLedgerBalance($providerId);
            $after = round($before + $amount, 2);
            $this->insertProviderWallet($provider, 'funding', 'in', $amount, $before, $after, $businessId, null, $adminId, $notes, $idempotencyKey);
            $this->writeProviderBalance($providerId, $this->providerCurrentAfter($provider, $after, $amount, 'in'), 'manual', 'Provider wallet funded: ' . $notes);
            $this->db->commit();
        } catch (\Throwable $throwable) {
            $this->db->rollBack();
            throw $throwable;
        }
    }

    public function recoverProvider(int $providerId, float $amount, int $adminId, string $notes, ?string $idempotencyKey = null): void
    {
        $this->assertTablesReady();
        $provider = $this->providerOrFail($providerId);
        $amount = $this->validAmount($amount);
        $notes = $this->requireNotes($notes);
        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey, 'finance-provider-recover');
        if ($this->alreadyRecorded('business_cash_ledger', $idempotencyKey) || $this->alreadyRecorded('provider_wallet_ledger', $idempotencyKey)) {
            return;
        }
        $ledgerBalance = $this->providerLedgerBalance($providerId);
        $knownProviderBalance = $provider['current_balance'] !== null ? (float) $provider['current_balance'] : null;
        $recoverable = $knownProviderBalance !== null ? min($ledgerBalance, $knownProviderBalance) : $ledgerBalance;
        if ($amount > max(0.0, $recoverable)) {
            throw new RuntimeException('Recovery amount exceeds available provider wallet balance.');
        }

        $this->db->beginTransaction();
        try {
            $before = $ledgerBalance;
            $after = round($before - $amount, 2);
            $providerLedgerId = $this->insertProviderWallet($provider, 'recovery', 'out', $amount, $before, $after, null, null, $adminId, $notes, $idempotencyKey);
            $businessId = $this->insertBusinessCash('provider_wallet_recovered', 'in', $amount, $adminId, $notes, $providerId, $idempotencyKey);
            $this->db->safeExecute(
                'UPDATE provider_wallet_ledger SET business_cash_ledger_id = :business_id WHERE id = :id',
                ['business_id' => $businessId, 'id' => $providerLedgerId]
            );
            $this->writeProviderBalance($providerId, $this->providerCurrentAfter($provider, $after, $amount, 'out'), 'manual', 'Provider wallet recovered: ' . $notes);
            $this->db->commit();
        } catch (\Throwable $throwable) {
            $this->db->rollBack();
            throw $throwable;
        }
    }

    public function adjustProvider(int $providerId, string $direction, float $amount, int $adminId, string $notes, ?string $idempotencyKey = null): void
    {
        $this->assertTablesReady();
        $provider = $this->providerOrFail($providerId);
        $amount = $this->validAmount($amount);
        $notes = $this->requireNotes($notes);
        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey, 'finance-provider-adjust');
        if ($this->alreadyRecorded('provider_wallet_ledger', $idempotencyKey)) {
            return;
        }
        if (!in_array($direction, ['in', 'out'], true)) {
            throw new RuntimeException('Invalid provider adjustment direction.');
        }

        $this->db->beginTransaction();
        try {
            $before = $this->providerLedgerBalance($providerId);
            $after = round($before + ($direction === 'in' ? $amount : -$amount), 2);
            $this->insertProviderWallet($provider, 'manual_adjustment', $direction, $amount, $before, $after, null, null, $adminId, $notes, $idempotencyKey);
            $this->writeProviderBalance($providerId, $after, 'manual', 'Provider wallet adjustment: ' . $notes);
            $this->db->commit();
        } catch (\Throwable $throwable) {
            $this->db->rollBack();
            throw $throwable;
        }
    }

    public function injectOwnerCapital(float $amount, int $adminId, string $notes, ?string $idempotencyKey = null): void
    {
        $this->assertTablesReady();
        $amount = $this->validAmount($amount);
        $notes = $this->requireNotes($notes);
        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey, 'finance-owner-capital');
        if ($this->alreadyRecorded('business_cash_ledger', $idempotencyKey)) {
            return;
        }
        $this->insertBusinessCash('owner_capital_injected', 'in', $amount, $adminId, $notes, null, $idempotencyKey);
        $businessId = $this->ledgerIdByIdempotencyKey('business_cash_ledger', $idempotencyKey);
        $this->insertOwnerBalance('capital', 'capital_injection', 'in', $amount, null, null, $businessId, null, $adminId, $notes, $idempotencyKey ? 'owner-' . $idempotencyKey : null);
    }

    public function recordOwnerWithdrawalPaid(array $withdrawal, ?int $adminId): void
    {
        if (!$this->db->tableExists('business_cash_ledger')) {
            return;
        }

        $withdrawalId = (int) ($withdrawal['id'] ?? 0);
        $amount = round((float) ($withdrawal['amount'] ?? 0), 2);
        if ($withdrawalId <= 0 || $amount <= 0) {
            return;
        }

        $this->db->safeExecute(
            'INSERT IGNORE INTO business_cash_ledger
                (reference, entry_type, direction, amount, source_table, source_id, owner_withdrawal_id, notes, created_by_admin_id)
             VALUES
                (:reference, "owner_withdrawal", "out", :amount, "owner_withdrawals", :source_id, :owner_withdrawal_id, :notes, :admin_id)',
            [
                'reference' => 'BCL-OWNER-' . $withdrawalId,
                'amount' => $amount,
                'source_id' => $withdrawalId,
                'owner_withdrawal_id' => $withdrawalId,
                'notes' => (string) ($withdrawal['notes'] ?? 'Owner transfer paid.'),
                'admin_id' => $adminId,
            ]
        );
        $businessId = (int) ($this->db->first(
            'SELECT id FROM business_cash_ledger WHERE owner_withdrawal_id = :owner_withdrawal_id AND entry_type = "owner_withdrawal" LIMIT 1',
            ['owner_withdrawal_id' => $withdrawalId]
        )['id'] ?? 0);
        $balanceType = (string) ($withdrawal['withdrawal_type'] ?? 'profit') === 'capital_return' ? 'capital' : 'profit';
        $this->insertOwnerBalance($balanceType, 'owner_withdrawal', 'out', $amount, null, $withdrawalId, $businessId > 0 ? $businessId : null, null, $adminId, (string) ($withdrawal['notes'] ?? 'Owner transfer paid.'), 'owner-withdrawal:' . $balanceType . ':' . $withdrawalId);
    }

    public function backfillExisting(): array
    {
        $this->assertTablesReady();
        $fundingBefore = $this->countRows('business_cash_ledger');
        $providerBefore = $this->countRows('provider_wallet_ledger');

        $this->db->safeExecute(
            'INSERT IGNORE INTO business_cash_ledger
                (reference, entry_type, direction, amount, source_table, source_id, notes, created_at)
             SELECT CONCAT("BCL-FUND-", id), "user_funding_received", "in", amount, "wallet_funding_requests", id,
                    "Backfilled credited wallet funding.", COALESCE(credited_at, created_at)
             FROM wallet_funding_requests
             WHERE status = "credited"'
        );

        $this->db->safeExecute(
            'INSERT IGNORE INTO provider_wallet_ledger
                (reference, provider_account_id, provider_code, entry_type, direction, amount, balance_before, balance_after, transaction_id, notes, created_at)
             SELECT CONCAT("PWL-TXN-", id), provider_account_id, provider_code, "transaction_cost", "out", cost_price,
                    NULL, NULL, id, "Backfilled provider cost for successful transaction.", COALESCE(processed_at, created_at)
             FROM transactions
             WHERE status = "successful"
               AND provider_account_id IS NOT NULL
               AND provider_code IS NOT NULL
               AND cost_price > 0'
        );

        return [
            'business_cash_rows_added' => max(0, $this->countRows('business_cash_ledger') - $fundingBefore),
            'provider_wallet_rows_added' => max(0, $this->countRows('provider_wallet_ledger') - $providerBefore),
        ];
    }

    private function assertTablesReady(): void
    {
        if (!$this->tablesReady()) {
            throw new RuntimeException('Finance ledger tables are not installed yet. Run the finance ledger migration first.');
        }
    }

    private function signedSum(string $table): float
    {
        if (!$this->db->tableExists($table)) {
            return 0.0;
        }

        return $this->sum("SELECT COALESCE(SUM(CASE WHEN direction = \"in\" THEN amount ELSE -amount END),0) AS total FROM {$table}");
    }

    private function visibleProviderPrepaidBalance(): float
    {
        if (!$this->db->tableExists('provider_wallet_ledger')) {
            return 0.0;
        }

        return $this->sum(
            'SELECT COALESCE(SUM(CASE WHEN pwl.direction = "in" THEN pwl.amount ELSE -pwl.amount END),0) AS total
             FROM provider_wallet_ledger pwl
             INNER JOIN provider_accounts pa ON pa.id = pwl.provider_account_id
             WHERE pa.status <> "archived"'
        );
    }

    private function userLiability(): float
    {
        return $this->sum('SELECT COALESCE(SUM(balance),0) AS total FROM wallets')
            + $this->sum('SELECT COALESCE(SUM(balance),0) AS total FROM commission_wallets')
            + $this->sum('SELECT COALESCE(SUM(amount),0) AS total FROM withdrawal_requests WHERE status IN ("pending","approved")');
    }

    private function providerLedgerBalance(int $providerId): float
    {
        if (!$this->db->tableExists('provider_wallet_ledger')) {
            return 0.0;
        }

        return $this->sum(
            'SELECT COALESCE(SUM(CASE WHEN direction = "in" THEN amount ELSE -amount END),0) AS total
             FROM provider_wallet_ledger
             WHERE provider_account_id = :provider_id',
            ['provider_id' => $providerId]
        );
    }

    private function providerOrFail(int $providerId): array
    {
        $provider = $this->db->first('SELECT * FROM provider_accounts WHERE id = :id LIMIT 1', ['id' => $providerId]);
        if (!$provider || ($provider['status'] ?? '') === 'archived') {
            throw new RuntimeException('Provider account was not found.');
        }

        return $provider;
    }

    private function ownerBalance(string $balanceType): float
    {
        if (!$this->db->tableExists('owner_balance_ledger')) {
            return 0.0;
        }

        $balanceType = $this->normalizeBalanceType($balanceType);
        return round($this->sum(
            'SELECT COALESCE(SUM(CASE WHEN direction = "in" THEN amount ELSE -amount END),0) AS total
             FROM owner_balance_ledger
             WHERE balance_type = :balance_type',
            ['balance_type' => $balanceType]
        ), 2);
    }

    private function sumOwnerBalance(string $balanceType, string $direction, ?string $entryType = null): float
    {
        if (!$this->db->tableExists('owner_balance_ledger')) {
            return 0.0;
        }

        $balanceType = $this->normalizeBalanceType($balanceType);
        if (!in_array($direction, ['in', 'out'], true)) {
            throw new RuntimeException('Invalid owner balance direction.');
        }

        $sql = 'SELECT COALESCE(SUM(amount),0) AS total
                FROM owner_balance_ledger
                WHERE balance_type = :balance_type
                  AND direction = :direction';
        $params = [
            'balance_type' => $balanceType,
            'direction' => $direction,
        ];
        if ($entryType !== null) {
            $sql .= ' AND entry_type = :entry_type';
            $params['entry_type'] = $entryType;
        }

        return round($this->sum($sql, $params), 2);
    }

    private function insertBusinessCash(
        string $entryType,
        string $direction,
        float $amount,
        int $adminId,
        string $notes,
        ?int $providerId = null,
        ?string $idempotencyKey = null,
        ?string $sourceTable = null,
        ?int $sourceId = null,
        ?int $ownerWithdrawalId = null
    ): int
    {
        $reference = strtoupper('BCL' . bin2hex(random_bytes(6)));
        $hasIdempotency = $idempotencyKey !== null && $this->db->columnExists('business_cash_ledger', 'idempotency_key');
        $columns = 'reference, entry_type, direction, amount, source_table, source_id, provider_account_id, owner_withdrawal_id, notes, created_by_admin_id';
        $values = ':reference, :entry_type, :direction, :amount, :source_table, :source_id, :provider_account_id, :owner_withdrawal_id, :notes, :admin_id';
        $params = [
            'reference' => $reference,
            'entry_type' => $entryType,
            'direction' => $direction,
            'amount' => $amount,
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'provider_account_id' => $providerId,
            'owner_withdrawal_id' => $ownerWithdrawalId,
            'notes' => $notes,
            'admin_id' => $adminId,
        ];
        if ($hasIdempotency) {
            $columns .= ', idempotency_key';
            $values .= ', :idempotency_key';
            $params['idempotency_key'] = $idempotencyKey;
        }

        $this->db->safeExecute(
            ($hasIdempotency ? 'INSERT IGNORE' : 'INSERT') . ' INTO business_cash_ledger (' . $columns . ') VALUES (' . $values . ')',
            $params
        );

        $insertId = $this->db->lastInsertId();
        if ($insertId <= 0 && $hasIdempotency) {
            return $this->ledgerIdByIdempotencyKey('business_cash_ledger', $idempotencyKey) ?? 0;
        }

        return $insertId;
    }

    private function insertOwnerBalance(
        string $balanceType,
        string $entryType,
        string $direction,
        float $amount,
        ?int $transactionId,
        ?int $ownerWithdrawalId,
        ?int $businessCashLedgerId,
        ?int $openingReconciliationId,
        ?int $adminId,
        string $notes,
        ?string $idempotencyKey = null
    ): int {
        if (!$this->db->tableExists('owner_balance_ledger')) {
            return 0;
        }

        $balanceType = $this->normalizeBalanceType($balanceType);
        if (!in_array($direction, ['in', 'out'], true)) {
            throw new RuntimeException('Invalid owner balance direction.');
        }

        $amount = $this->validAmount($amount);
        $reference = strtoupper('OBL' . bin2hex(random_bytes(6)));
        $this->db->safeExecute(
            'INSERT IGNORE INTO owner_balance_ledger
                (reference, balance_type, entry_type, direction, amount, transaction_id, owner_withdrawal_id,
                 business_cash_ledger_id, opening_reconciliation_id, idempotency_key, notes, created_by_admin_id)
             VALUES
                (:reference, :balance_type, :entry_type, :direction, :amount, :transaction_id, :owner_withdrawal_id,
                 :business_cash_ledger_id, :opening_reconciliation_id, :idempotency_key, :notes, :admin_id)',
            [
                'reference' => $reference,
                'balance_type' => $balanceType,
                'entry_type' => $entryType,
                'direction' => $direction,
                'amount' => $amount,
                'transaction_id' => $transactionId,
                'owner_withdrawal_id' => $ownerWithdrawalId,
                'business_cash_ledger_id' => $businessCashLedgerId,
                'opening_reconciliation_id' => $openingReconciliationId,
                'idempotency_key' => $idempotencyKey,
                'notes' => substr($notes, 0, 255),
                'admin_id' => $adminId,
            ]
        );

        $insertId = $this->db->lastInsertId();
        if ($insertId <= 0) {
            if ($idempotencyKey !== null) {
                return $this->ledgerIdByIdempotencyKey('owner_balance_ledger', $idempotencyKey) ?? 0;
            }

            $row = null;
            if ($transactionId !== null) {
                $row = $this->db->first(
                    'SELECT id FROM owner_balance_ledger
                     WHERE transaction_id = :transaction_id AND balance_type = :balance_type AND entry_type = :entry_type
                     LIMIT 1',
                    ['transaction_id' => $transactionId, 'balance_type' => $balanceType, 'entry_type' => $entryType]
                );
            } elseif ($ownerWithdrawalId !== null) {
                $row = $this->db->first(
                    'SELECT id FROM owner_balance_ledger
                     WHERE owner_withdrawal_id = :owner_withdrawal_id AND balance_type = :balance_type AND entry_type = :entry_type
                     LIMIT 1',
                    ['owner_withdrawal_id' => $ownerWithdrawalId, 'balance_type' => $balanceType, 'entry_type' => $entryType]
                );
            }

            return $row ? (int) $row['id'] : 0;
        }

        return $insertId;
    }

    private function insertProviderWallet(array $provider, string $entryType, string $direction, float $amount, ?float $before, ?float $after, ?int $businessId, ?int $transactionId, ?int $adminId, string $notes, ?string $idempotencyKey = null): int
    {
        $reference = strtoupper('PWL' . bin2hex(random_bytes(6)));
        $hasIdempotency = $idempotencyKey !== null && $this->db->columnExists('provider_wallet_ledger', 'idempotency_key');
        $columns = 'reference, provider_account_id, provider_code, entry_type, direction, amount, balance_before, balance_after,
                 transaction_id, business_cash_ledger_id, notes, created_by_admin_id';
        $values = ':reference, :provider_account_id, :provider_code, :entry_type, :direction, :amount, :before, :after,
                 :transaction_id, :business_cash_ledger_id, :notes, :admin_id';
        $params = [
            'reference' => $reference,
            'provider_account_id' => (int) $provider['id'],
            'provider_code' => (string) $provider['code'],
            'entry_type' => $entryType,
            'direction' => $direction,
            'amount' => $amount,
            'before' => $before,
            'after' => $after,
            'transaction_id' => $transactionId,
            'business_cash_ledger_id' => $businessId,
            'notes' => $notes,
            'admin_id' => $adminId,
        ];
        if ($hasIdempotency) {
            $columns .= ', idempotency_key';
            $values .= ', :idempotency_key';
            $params['idempotency_key'] = $idempotencyKey;
        }

        $this->db->safeExecute(
            ($hasIdempotency ? 'INSERT IGNORE' : 'INSERT') . ' INTO provider_wallet_ledger (' . $columns . ') VALUES (' . $values . ')',
            $params
        );

        $insertId = $this->db->lastInsertId();
        if ($insertId <= 0 && $hasIdempotency) {
            return $this->ledgerIdByIdempotencyKey('provider_wallet_ledger', $idempotencyKey) ?? 0;
        }

        return $insertId;
    }

    private function alreadyRecorded(string $table, ?string $idempotencyKey): bool
    {
        return $this->ledgerIdByIdempotencyKey($table, $idempotencyKey) !== null;
    }

    private function ledgerIdByIdempotencyKey(string $table, ?string $idempotencyKey): ?int
    {
        if ($idempotencyKey === null || !$this->db->tableExists($table) || !$this->db->columnExists($table, 'idempotency_key')) {
            return null;
        }

        $row = $this->db->first("SELECT id FROM {$table} WHERE idempotency_key = :idempotency_key LIMIT 1", [
            'idempotency_key' => $idempotencyKey,
        ]);

        return $row ? (int) $row['id'] : null;
    }

    private function normalizeIdempotencyKey(?string $key, string $prefix): ?string
    {
        $key = substr(trim((string) $key), 0, 120);
        if ($key === '') {
            return null;
        }

        return str_starts_with($key, $prefix . ':') ? $key : $prefix . ':' . $key;
    }

    private function writeProviderBalance(int $providerId, float $amount, string $source, string $notes): void
    {
        $this->db->safeExecute(
            'INSERT INTO provider_balance_logs (provider_account_id, balance_amount, source, notes)
             VALUES (:provider_account_id, :balance_amount, :source, :notes)',
            [
                'provider_account_id' => $providerId,
                'balance_amount' => round($amount, 2),
                'source' => $source,
                'notes' => substr($notes, 0, 255),
            ]
        );
        if ($this->db->columnExists('provider_accounts', 'current_balance')) {
            $this->db->safeExecute(
                'UPDATE provider_accounts SET current_balance = :balance, balance_refreshed_at = NOW() WHERE id = :id',
                ['balance' => round($amount, 2), 'id' => $providerId]
            );
        }
    }

    private function providerCurrentAfter(array $provider, float $ledgerAfter, float $amount, string $direction): float
    {
        if ($provider['current_balance'] === null) {
            return $ledgerAfter;
        }

        $current = (float) $provider['current_balance'];
        return round($current + ($direction === 'in' ? $amount : -$amount), 2);
    }

    private function normalizeBalanceType(string $balanceType): string
    {
        $balanceType = strtolower(trim($balanceType));
        if (!in_array($balanceType, ['capital', 'profit'], true)) {
            throw new RuntimeException('Invalid owner balance type.');
        }

        return $balanceType;
    }

    private function validNonNegativeAmount(float $amount): float
    {
        $amount = round($amount, 2);
        if ($amount < 0) {
            throw new RuntimeException('Amount cannot be negative.');
        }

        return $amount;
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
            throw new RuntimeException('A note is required for finance ledger actions.');
        }

        return substr($notes, 0, 255);
    }

    private function countRows(string $table): int
    {
        return (int) ($this->db->first("SELECT COUNT(*) AS total FROM {$table}")['total'] ?? 0);
    }

    private function sum(string $sql, array $params = []): float
    {
        try {
            return (float) ($this->db->first($sql, $params)['total'] ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }
}
