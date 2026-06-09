<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('wallet.manage');
$finance = app(\GemData\Classes\FinanceLedgerService::class);
$ownerWithdrawals = app(\GemData\Classes\OwnerWithdrawalService::class);
$activityLogger = app(\GemData\Classes\ActivityLogger::class);

function require_finance_admin_password(array $admin): void
{
    $password = (string) ($_POST['admin_password'] ?? '');
    if (!auth()->confirmAdminPassword((int) $admin['id'], $password)) {
        throw new RuntimeException('Admin password confirmation failed.');
    }
}

function mask_owner_account_number(?string $accountNumber): string
{
    $digits = preg_replace('/\D+/', '', (string) $accountNumber) ?? '';
    if ($digits === '') {
        return '';
    }

    $lastFour = substr($digits, -4);
    return str_repeat('*', max(4, strlen($digits) - 4)) . $lastFour;
}

if (is_post()) {
    try {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');
        require_finance_admin_password($admin);

        if ($action === 'backfill') {
            $result = $finance->backfillExisting();
            $activityLogger->log('admin', (int) $admin['id'], 'finance_ledger_backfilled', 'Admin ran finance ledger backfill.', $result);
            flash('success', sprintf('Backfill complete. Business cash rows: %d. Provider wallet rows: %d.', (int) $result['business_cash_rows_added'], (int) $result['provider_wallet_rows_added']));
        } elseif ($action === 'fund_provider') {
            $finance->fundProvider((int) $_POST['provider_id'], (float) $_POST['amount'], (int) $admin['id'], (string) ($_POST['notes'] ?? ''), (string) ($_POST['idempotency_key'] ?? ''));
            $activityLogger->log('admin', (int) $admin['id'], 'provider_wallet_funded', 'Admin recorded provider wallet funding.', [
                'provider_id' => (int) $_POST['provider_id'],
                'amount' => (float) $_POST['amount'],
            ]);
            flash('success', 'Provider wallet funding recorded.');
        } elseif ($action === 'recover_provider') {
            $finance->recoverProvider((int) $_POST['provider_id'], (float) $_POST['amount'], (int) $admin['id'], (string) ($_POST['notes'] ?? ''), (string) ($_POST['idempotency_key'] ?? ''));
            $activityLogger->log('admin', (int) $admin['id'], 'provider_wallet_recovered', 'Admin recorded provider wallet recovery.', [
                'provider_id' => (int) $_POST['provider_id'],
                'amount' => (float) $_POST['amount'],
            ]);
            flash('success', 'Provider wallet recovery recorded.');
        } elseif ($action === 'adjust_provider') {
            $finance->adjustProvider((int) $_POST['provider_id'], (string) ($_POST['direction'] ?? ''), (float) $_POST['amount'], (int) $admin['id'], (string) ($_POST['notes'] ?? ''), (string) ($_POST['idempotency_key'] ?? ''));
            $activityLogger->log('admin', (int) $admin['id'], 'provider_wallet_adjusted', 'Admin recorded provider wallet adjustment.', [
                'provider_id' => (int) $_POST['provider_id'],
                'direction' => (string) ($_POST['direction'] ?? ''),
                'amount' => (float) $_POST['amount'],
            ]);
            flash('success', 'Provider wallet adjustment recorded.');
        } elseif ($action === 'inject_owner_capital') {
            $finance->injectOwnerCapital((float) $_POST['amount'], (int) $admin['id'], (string) ($_POST['notes'] ?? ''), (string) ($_POST['idempotency_key'] ?? ''));
            $activityLogger->log('admin', (int) $admin['id'], 'owner_capital_injected', 'Admin recorded owner capital injection.', [
                'amount' => (float) ($_POST['amount'] ?? 0),
            ]);
            flash('success', 'Owner capital injection recorded.');
        } elseif ($action === 'request_owner_withdrawal') {
            $withdrawal = $ownerWithdrawals->request(
                (int) $admin['id'],
                (float) $_POST['amount'],
                (string) ($_POST['notes'] ?? ''),
                (string) ($_POST['withdrawal_type'] ?? 'profit'),
                [
                    'bank_name' => $_POST['bank_name'] ?? '',
                    'account_number' => $_POST['account_number'] ?? '',
                    'account_name' => $_POST['account_name'] ?? '',
                    'transfer_reference' => $_POST['transfer_reference'] ?? '',
                ]
            );
            $activityLogger->log('admin', (int) $admin['id'], 'owner_transfer_recorded', 'Admin recorded owner transfer.', [
                'owner_withdrawal_id' => (int) ($withdrawal['id'] ?? 0),
                'withdrawal_type' => (string) ($withdrawal['withdrawal_type'] ?? 'profit'),
                'amount' => (float) ($_POST['amount'] ?? 0),
            ]);
            flash('success', 'Owner transfer recorded.');
        } elseif ($action === 'approve_owner_withdrawal') {
            $ownerWithdrawals->approve((int) $_POST['withdrawal_id'], (int) $admin['id'], (string) ($_POST['notes'] ?? ''));
            $activityLogger->log('admin', (int) $admin['id'], 'owner_transfer_approved', 'Admin approved owner transfer.', [
                'owner_withdrawal_id' => (int) $_POST['withdrawal_id'],
            ]);
            flash('success', 'Owner transfer approved.');
        } elseif ($action === 'reject_owner_withdrawal') {
            $ownerWithdrawals->reject((int) $_POST['withdrawal_id'], (int) $admin['id'], (string) ($_POST['notes'] ?? ''));
            $activityLogger->log('admin', (int) $admin['id'], 'owner_transfer_rejected', 'Admin rejected owner transfer.', [
                'owner_withdrawal_id' => (int) $_POST['withdrawal_id'],
            ]);
            flash('success', 'Owner transfer rejected.');
        } elseif ($action === 'mark_owner_withdrawal_paid') {
            $ownerWithdrawals->markPaid((int) $_POST['withdrawal_id'], (int) $admin['id'], (string) ($_POST['transfer_reference'] ?? ''));
            $activityLogger->log('admin', (int) $admin['id'], 'owner_transfer_paid', 'Admin marked owner transfer paid.', [
                'owner_withdrawal_id' => (int) $_POST['withdrawal_id'],
            ]);
            flash('success', 'Owner transfer marked paid and business cash outflow recorded.');
        } else {
            throw new RuntimeException('Unsupported finance action.');
        }
    } catch (Throwable $throwable) {
        flash('error', $throwable->getMessage());
    }

    redirect(base_url('admin/finance.php'));
}

$ledgerReady = $finance->tablesReady();
$summary = $finance->overview();
$providers = db()->query(
    'SELECT id, name, code, status, current_balance
     FROM provider_accounts
     WHERE status <> "archived"
     ORDER BY priority_order ASC, id ASC'
);
$providerBalances = $finance->providerBalances();
$businessRows = $finance->recentBusinessLedger(20);
$providerRows = $finance->recentProviderLedger(20);
$ownerRows = $ownerWithdrawals->recent(20);

render_header('Finance Ledger', 'admin');
?>
<div class="space-y-6">
    <?php if ($message = flash('success')): ?><div class="notice notice-success"><?= e($message); ?></div><?php endif; ?>
    <?php if ($message = flash('error')): ?><div class="notice notice-error"><?= e($message); ?></div><?php endif; ?>

    <section class="rounded-2xl border border-gem-border bg-white p-5 shadow-card">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-widest text-gem-blue">Finance Ledger</p>
                <h1 class="mt-1 text-2xl font-extrabold text-gem-text">Business cash, provider prepaid balance, and owner transfers</h1>
                <p class="mt-1 text-[14px] text-gem-muted">Minimal accounting view for separating user liabilities from business-owned money.</p>
            </div>
            <?php if ($ledgerReady): ?>
                <form method="post" class="flex flex-wrap gap-2">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="backfill">
                    <input class="rounded-xl border border-gem-border px-3 py-2 text-[13px]" name="admin_password" type="password" placeholder="Admin password" required>
                    <button class="rounded-xl bg-gem-blue px-4 py-2 text-[13px] font-bold text-white" type="submit">Run Backfill</button>
                </form>
            <?php endif; ?>
        </div>
        <?php if (!$ledgerReady): ?>
            <div class="mt-4 rounded-2xl border border-gem-orange/30 bg-gem-orange/10 p-4 text-[13px] font-semibold text-gem-orange">
                Finance ledger tables are not installed yet. Run the finance ledger migration before using this page.
            </div>
        <?php endif; ?>
    </section>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <?php
        $cards = [
            'Business Cash' => ['amount' => $summary['business_cash'] ?? 0, 'note' => 'Cash received minus provider funding and owner transfers'],
            'User Liability' => ['amount' => $summary['user_liability'] ?? 0, 'note' => 'Wallets, commission wallets, unpaid withdrawals'],
            'Pending Exposure' => ['amount' => $summary['pending_exposure'] ?? 0, 'note' => 'Pending transaction amount protected from withdrawal'],
            'Safe Available Cash' => ['amount' => $summary['safe_available_cash'] ?? 0, 'note' => 'Business cash after user liabilities and pending exposure'],
            'Provider Prepaid' => ['amount' => $summary['provider_prepaid_balance'] ?? 0, 'note' => 'Provider wallet ledger balance'],
            'Gross Revenue' => ['amount' => $summary['gross_revenue'] ?? 0, 'note' => 'Successful sales'],
            'Provider Costs' => ['amount' => $summary['provider_costs'] ?? 0, 'note' => 'Successful provider costs with ledger fallback'],
            'Confirmed Profit' => ['amount' => $summary['confirmed_profit'] ?? 0, 'note' => 'Gross revenue less provider costs'],
            'Profit Withdrawn' => ['amount' => $summary['profit_withdrawn'] ?? 0, 'note' => 'Paid owner transfers marked as profit'],
            'Profit Withdrawable' => ['amount' => $summary['profit_withdrawable'] ?? 0, 'note' => 'Profit remaining, capped by safe cash'],
            'Owner Capital Available' => ['amount' => $summary['available_owner_capital'] ?? 0, 'note' => 'Injected or recovered capital less returns'],
            'Capital Returned' => ['amount' => $summary['capital_returned'] ?? 0, 'note' => 'Paid owner capital-return withdrawals'],
            'Capital Returnable' => ['amount' => $summary['capital_return_withdrawable'] ?? 0, 'note' => 'Capital available, capped by safe cash'],
        ];
        ?>
        <?php foreach ($cards as $label => $card): ?>
            <div class="rounded-2xl border border-gem-border bg-white p-4 shadow-card">
                <p class="text-[11px] font-bold uppercase tracking-widest text-gem-muted"><?= e($label); ?></p>
                <p class="mt-2 font-mono text-[22px] font-black text-gem-text"><?= e(money((float) $card['amount'])); ?></p>
                <p class="mt-1 text-[12px] text-gem-muted"><?= e($card['note']); ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <section class="rounded-2xl border border-gem-border bg-white p-5 shadow-card">
            <h2 class="text-lg font-extrabold text-gem-text">Fund provider wallet</h2>
            <p class="mt-1 text-[13px] text-gem-muted">Moves business cash into provider prepaid balance. This is not an expense.</p>
            <form class="mt-4 space-y-3" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="fund_provider">
                <input type="hidden" name="idempotency_key" value="<?= e(fresh_idempotency_key('finance-provider-fund')); ?>">
                <select class="w-full rounded-xl border border-gem-border px-3 py-2" name="provider_id" required>
                    <?php foreach ($providers as $provider): ?><option value="<?= (int) $provider['id']; ?>"><?= e($provider['name'] . ' (' . $provider['code'] . ')'); ?></option><?php endforeach; ?>
                </select>
                <input class="w-full rounded-xl border border-gem-border px-3 py-2" name="amount" type="number" min="0.01" step="0.01" placeholder="Amount" required>
                <input class="w-full rounded-xl border border-gem-border px-3 py-2" name="notes" placeholder="Required note" required>
                <input class="w-full rounded-xl border border-gem-border px-3 py-2" name="admin_password" type="password" placeholder="Admin password" required>
                <button class="w-full rounded-xl bg-gem-blue px-4 py-2.5 font-bold text-white" type="submit">Record Funding</button>
            </form>
        </section>

        <section class="rounded-2xl border border-gem-border bg-white p-5 shadow-card">
            <h2 class="text-lg font-extrabold text-gem-text">Recover provider wallet</h2>
            <p class="mt-1 text-[13px] text-gem-muted">Moves provider prepaid balance back to business cash. This is not profit.</p>
            <form class="mt-4 space-y-3" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="recover_provider">
                <input type="hidden" name="idempotency_key" value="<?= e(fresh_idempotency_key('finance-provider-recover')); ?>">
                <select class="w-full rounded-xl border border-gem-border px-3 py-2" name="provider_id" required>
                    <?php foreach ($providers as $provider): ?><option value="<?= (int) $provider['id']; ?>"><?= e($provider['name'] . ' (' . $provider['code'] . ')'); ?></option><?php endforeach; ?>
                </select>
                <input class="w-full rounded-xl border border-gem-border px-3 py-2" name="amount" type="number" min="0.01" step="0.01" placeholder="Amount" required>
                <input class="w-full rounded-xl border border-gem-border px-3 py-2" name="notes" placeholder="Required note" required>
                <input class="w-full rounded-xl border border-gem-border px-3 py-2" name="admin_password" type="password" placeholder="Admin password" required>
                <button class="w-full rounded-xl bg-gem-green px-4 py-2.5 font-bold text-white" type="submit">Record Recovery</button>
            </form>
        </section>

        <section class="rounded-2xl border border-gem-border bg-white p-5 shadow-card">
            <h2 class="text-lg font-extrabold text-gem-text">Owner Transfer</h2>
            <p class="mt-1 text-[13px] text-gem-muted">Choose profit withdrawal or capital return. Both are capped by safe available cash.</p>
            <form class="mt-4 space-y-3" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="request_owner_withdrawal">
                <select class="w-full rounded-xl border border-gem-border px-3 py-2" name="withdrawal_type" required>
                    <option value="profit">Profit Withdrawal - limit <?= e(money((float) ($summary['profit_withdrawable'] ?? 0))); ?></option>
                    <option value="capital_return">Capital Return - limit <?= e(money((float) ($summary['capital_return_withdrawable'] ?? 0))); ?></option>
                </select>
                <input class="w-full rounded-xl border border-gem-border px-3 py-2" name="amount" type="number" min="0.01" step="0.01" placeholder="Amount" required>
                <input class="w-full rounded-xl border border-gem-border px-3 py-2" name="bank_name" placeholder="Bank Name" required>
                <input class="w-full rounded-xl border border-gem-border px-3 py-2" name="account_number" placeholder="Account Number" required>
                <input class="w-full rounded-xl border border-gem-border px-3 py-2" name="account_name" placeholder="Account Name" required>
                <input class="w-full rounded-xl border border-gem-border px-3 py-2" name="transfer_reference" placeholder="Transfer Reference optional">
                <input class="w-full rounded-xl border border-gem-border px-3 py-2" name="notes" placeholder="Required note" required>
                <input class="w-full rounded-xl border border-gem-border px-3 py-2" name="admin_password" type="password" placeholder="Admin password" required>
                <button class="w-full rounded-xl bg-gem-orange px-4 py-2.5 font-bold text-white" type="submit">Record Owner Transfer</button>
            </form>
        </section>
    </div>

    <section class="rounded-2xl border border-gem-border bg-white p-5 shadow-card">
        <h2 class="text-lg font-extrabold text-gem-text">Owner capital injection and provider adjustment</h2>
        <p class="mt-1 text-[13px] text-gem-muted">Capital injection increases business cash and available owner capital. Provider adjustment is only for confirmed opening balances/corrections.</p>
        <form class="mt-4 grid gap-3 md:grid-cols-4" method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <input type="hidden" name="action" value="inject_owner_capital">
            <input type="hidden" name="idempotency_key" value="<?= e(fresh_idempotency_key('finance-owner-capital')); ?>">
            <input class="rounded-xl border border-gem-border px-3 py-2" name="amount" type="number" min="0.01" step="0.01" placeholder="Owner capital amount" required>
            <input class="rounded-xl border border-gem-border px-3 py-2" name="notes" placeholder="Required note" required>
            <input class="rounded-xl border border-gem-border px-3 py-2" name="admin_password" type="password" placeholder="Admin password" required>
            <button class="rounded-xl bg-gem-green px-4 py-2.5 font-bold text-white" type="submit">Record Owner Capital</button>
        </form>
        <form class="mt-4 grid gap-3 md:grid-cols-5" method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <input type="hidden" name="action" value="adjust_provider">
            <input type="hidden" name="idempotency_key" value="<?= e(fresh_idempotency_key('finance-provider-adjust')); ?>">
            <select class="rounded-xl border border-gem-border px-3 py-2" name="provider_id" required>
                <?php foreach ($providers as $provider): ?><option value="<?= (int) $provider['id']; ?>"><?= e($provider['name'] . ' (' . $provider['code'] . ')'); ?></option><?php endforeach; ?>
            </select>
            <select class="rounded-xl border border-gem-border px-3 py-2" name="direction" required>
                <option value="in">Increase</option>
                <option value="out">Decrease</option>
            </select>
            <input class="rounded-xl border border-gem-border px-3 py-2" name="amount" type="number" min="0.01" step="0.01" placeholder="Amount" required>
            <input class="rounded-xl border border-gem-border px-3 py-2" name="notes" placeholder="Required note" required>
            <input class="rounded-xl border border-gem-border px-3 py-2" name="admin_password" type="password" placeholder="Admin password" required>
            <button class="rounded-xl bg-gem-blue px-4 py-2.5 font-bold text-white md:col-span-5" type="submit">Record Manual Adjustment</button>
        </form>
    </section>

    <section class="rounded-2xl border border-gem-border bg-white p-5 shadow-card">
        <h2 class="text-lg font-extrabold text-gem-text">Provider ledger balances</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-gem-border text-left text-[13px]">
                <thead class="bg-gem-gray text-[11px] uppercase tracking-widest text-gem-muted"><tr><th class="px-4 py-3">Provider</th><th class="px-4 py-3">Ledger Balance</th><th class="px-4 py-3">Known API/Current Balance</th><th class="px-4 py-3">Status</th></tr></thead>
                <tbody class="divide-y divide-gem-border">
                    <?php foreach ($providerBalances as $row): ?>
                        <tr><td class="px-4 py-3 font-bold"><?= e($row['name'] . ' (' . $row['code'] . ')'); ?></td><td class="px-4 py-3"><?= e(money((float) $row['ledger_balance'])); ?></td><td class="px-4 py-3"><?= $row['current_balance'] !== null ? e(money((float) $row['current_balance'])) : 'Unknown'; ?></td><td class="px-4 py-3"><?= e($row['status']); ?></td></tr>
                    <?php endforeach; ?>
                    <?php if ($providerBalances === []): ?><tr><td class="px-4 py-3 text-gem-muted" colspan="4">No provider ledger rows yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid gap-4 xl:grid-cols-3">
        <section class="rounded-2xl border border-gem-border bg-white p-5 shadow-card">
            <h2 class="text-lg font-extrabold text-gem-text">Owner Transfers</h2>
            <div class="mt-4 space-y-3">
                <?php foreach ($ownerRows as $row): ?>
                    <div class="rounded-2xl border border-gem-border bg-gem-gray p-3">
                        <div class="flex items-start justify-between gap-3"><div><p class="font-mono text-[12px] font-bold"><?= e($row['reference']); ?></p><p class="text-[12px] text-gem-muted"><?= e(str_replace('_', ' ', (string) ($row['withdrawal_type'] ?? 'profit'))); ?> / <?= e($row['status']); ?> by <?= e($row['requested_by_name']); ?></p><p class="mt-1 text-[12px] text-gem-muted"><?= e(trim((string) ($row['bank_name'] ?? '') . ' ' . mask_owner_account_number($row['account_number'] ?? '') . ' ' . (string) ($row['account_name'] ?? ''))); ?></p><?php if (!empty($row['transfer_reference'])): ?><p class="mt-1 font-mono text-[11px] text-gem-muted">Ref <?= e($row['transfer_reference']); ?></p><?php endif; ?></div><p class="font-mono font-black"><?= e(money((float) $row['amount'])); ?></p></div>
                        <?php if (in_array($row['status'], ['pending', 'approved'], true)): ?>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <?php if ($row['status'] === 'pending'): ?>
                                    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="action" value="approve_owner_withdrawal"><input type="hidden" name="withdrawal_id" value="<?= (int) $row['id']; ?>"><input class="mb-2 w-full rounded-xl border border-gem-border px-3 py-2 text-[12px]" name="admin_password" type="password" placeholder="Password" required><button class="rounded-xl bg-gem-blue px-3 py-2 text-[12px] font-bold text-white">Approve</button></form>
                                <?php endif; ?>
                                <?php if ($row['status'] === 'approved'): ?>
                                    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="action" value="mark_owner_withdrawal_paid"><input type="hidden" name="withdrawal_id" value="<?= (int) $row['id']; ?>"><input class="mb-2 w-full rounded-xl border border-gem-border px-3 py-2 text-[12px]" name="transfer_reference" placeholder="Transfer Reference optional" value="<?= e((string) ($row['transfer_reference'] ?? '')); ?>"><input class="mb-2 w-full rounded-xl border border-gem-border px-3 py-2 text-[12px]" name="admin_password" type="password" placeholder="Password" required><button class="rounded-xl bg-gem-green px-3 py-2 text-[12px] font-bold text-white">Mark Paid</button></form>
                                <?php endif; ?>
                                <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="action" value="reject_owner_withdrawal"><input type="hidden" name="withdrawal_id" value="<?= (int) $row['id']; ?>"><input class="mb-2 w-full rounded-xl border border-gem-border px-3 py-2 text-[12px]" name="notes" placeholder="Reason" required><input class="mb-2 w-full rounded-xl border border-gem-border px-3 py-2 text-[12px]" name="admin_password" type="password" placeholder="Password" required><button class="rounded-xl bg-gem-red px-3 py-2 text-[12px] font-bold text-white">Reject</button></form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($ownerRows === []): ?><p class="text-[13px] text-gem-muted">No owner transfers yet.</p><?php endif; ?>
            </div>
        </section>

        <section class="rounded-2xl border border-gem-border bg-white p-5 shadow-card">
            <h2 class="text-lg font-extrabold text-gem-text">Business cash ledger</h2>
            <div class="mt-4 space-y-2">
                <?php foreach ($businessRows as $row): ?>
                    <div class="rounded-xl border border-gem-border p-3 text-[13px]"><div class="flex justify-between gap-3"><span class="font-bold"><?= e($row['entry_type']); ?></span><span class="font-mono <?= $row['direction'] === 'in' ? 'text-gem-green' : 'text-gem-orange'; ?>"><?= e(($row['direction'] === 'in' ? '+' : '-') . money((float) $row['amount'])); ?></span></div><p class="mt-1 text-[12px] text-gem-muted"><?= e($row['notes'] ?? ''); ?></p></div>
                <?php endforeach; ?>
                <?php if ($businessRows === []): ?><p class="text-[13px] text-gem-muted">No business cash ledger rows yet.</p><?php endif; ?>
            </div>
        </section>

        <section class="rounded-2xl border border-gem-border bg-white p-5 shadow-card">
            <h2 class="text-lg font-extrabold text-gem-text">Provider wallet ledger</h2>
            <div class="mt-4 space-y-2">
                <?php foreach ($providerRows as $row): ?>
                    <div class="rounded-xl border border-gem-border p-3 text-[13px]"><div class="flex justify-between gap-3"><span class="font-bold"><?= e($row['provider_code'] . ' ' . $row['entry_type']); ?></span><span class="font-mono <?= $row['direction'] === 'in' ? 'text-gem-green' : 'text-gem-orange'; ?>"><?= e(($row['direction'] === 'in' ? '+' : '-') . money((float) $row['amount'])); ?></span></div><p class="mt-1 text-[12px] text-gem-muted"><?= e($row['transaction_reference'] ? 'Txn ' . $row['transaction_reference'] : ($row['notes'] ?? '')); ?></p></div>
                <?php endforeach; ?>
                <?php if ($providerRows === []): ?><p class="text-[13px] text-gem-muted">No provider wallet ledger rows yet.</p><?php endif; ?>
            </div>
        </section>
    </div>
</div>
<?php render_footer('admin'); ?>
