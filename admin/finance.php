<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('wallet.manage');
$finance = app(\GemData\Classes\FinanceLedgerService::class);
$ownerWithdrawals = app(\GemData\Classes\OwnerWithdrawalService::class);
$katPayPayouts = app(\GemData\Classes\KatPayPayoutService::class);
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

        if ($action === 'initialize_opening_balances') {
            $finance->initializeOpeningBalances(
                (float) ($_POST['opening_capital'] ?? 0),
                (float) ($_POST['opening_profit'] ?? 0),
                (int) $admin['id'],
                'One-time opening capital/profit reconciliation.'
            );
            $activityLogger->log('admin', (int) $admin['id'], 'finance_opening_balances_initialized', 'Admin initialized owner opening capital and profit.', [
                'opening_capital' => (float) ($_POST['opening_capital'] ?? 0),
                'opening_profit' => (float) ($_POST['opening_profit'] ?? 0),
            ]);
            flash('success', 'Opening capital and opening profit initialized.');
        } elseif ($action === 'backfill') {
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
        } elseif ($action === 'send_owner_withdrawal_katpay') {
            $bankCode = trim((string) ($_POST['bank_code'] ?? ''));
            $bankName = '';
            foreach ($katPayPayouts->banks() as $bank) {
                if (($bank['bank_code'] ?? '') === $bankCode) {
                    $bankName = (string) ($bank['bank_name'] ?? '');
                    break;
                }
            }
            if ($bankCode === '' || $bankName === '') {
                throw new RuntimeException('Select a valid KatPay bank before sending payout.');
            }

            $reference = strtoupper('OWD' . bin2hex(random_bytes(6)));
            $withdrawal = $ownerWithdrawals->request(
                (int) $admin['id'],
                (float) $_POST['amount'],
                (string) ($_POST['notes'] ?? ''),
                (string) ($_POST['withdrawal_type'] ?? 'profit'),
                [
                    'bank_name' => $bankName,
                    'bank_code' => $bankCode,
                    'account_number' => $_POST['account_number'] ?? '',
                    'account_name' => $_POST['account_name'] ?? '',
                    'transfer_reference' => $reference,
                    'payout_provider' => 'katpay',
                    'payout_status' => 'processing',
                    'payout_reference' => $reference,
                ]
            );
            try {
                $payout = $katPayPayouts->payout([
                    'amount' => (float) $withdrawal['amount'],
                    'bank_code' => $bankCode,
                    'account_number' => (string) $withdrawal['account_number'],
                    'account_name' => (string) $withdrawal['account_name'],
                    'description' => (string) ($withdrawal['notes'] ?? 'GemData owner transfer'),
                    'reference' => $reference,
                ]);
                $ownerWithdrawals->updatePayoutResult(
                    (int) $withdrawal['id'],
                    (string) $payout['status'],
                    (string) $payout['provider_reference'],
                    (array) $payout['safe_response'],
                    (string) ($payout['message'] ?? '')
                );
                if (($payout['status'] ?? '') === 'successful') {
                    $ownerWithdrawals->markPaidFromPayout((int) $withdrawal['id'], (int) $admin['id'], (string) $payout['provider_reference'], (array) $payout['safe_response']);
                    flash('success', 'KatPay payout processed and owner transfer marked paid.');
                } elseif (($payout['status'] ?? '') === 'failed') {
                    $ownerWithdrawals->failPayout((int) $withdrawal['id'], (string) ($payout['message'] ?: 'KatPay payout failed.'), (array) $payout['safe_response']);
                    flash('error', 'KatPay payout failed. No business cash outflow was recorded.');
                } else {
                    flash('success', 'KatPay payout submitted. Owner transfer remains unpaid until KatPay confirms success.');
                }
            } catch (Throwable $throwable) {
                $ownerWithdrawals->failPayout((int) $withdrawal['id'], $throwable->getMessage());
                throw $throwable;
            }
            $activityLogger->log('admin', (int) $admin['id'], 'owner_transfer_katpay_sent', 'Admin submitted owner transfer through KatPay payout.', [
                'owner_withdrawal_id' => (int) ($withdrawal['id'] ?? 0),
                'withdrawal_type' => (string) ($withdrawal['withdrawal_type'] ?? 'profit'),
                'amount' => (float) ($_POST['amount'] ?? 0),
                'bank_code' => $bankCode,
            ]);
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
                    'payout_provider' => 'manual',
                    'payout_status' => 'not_requested',
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
        } elseif ($action === 'confirm_payout_owner_withdrawal_paid') {
            $ownerWithdrawals->confirmPayoutPaidManually(
                (int) $_POST['withdrawal_id'],
                (int) $admin['id']
            );
            $activityLogger->log('admin', (int) $admin['id'], 'owner_transfer_payout_paid_manual', 'Admin manually confirmed an owner payout transfer as paid.', [
                'owner_withdrawal_id' => (int) $_POST['withdrawal_id'],
            ]);
            flash('success', 'Owner payout transfer confirmed paid and business cash outflow recorded.');
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
$knownProviderBalanceTotal = 0.0;
foreach ($providerBalances as $providerBalanceRow) {
    if ($providerBalanceRow['current_balance'] !== null) {
        $knownProviderBalanceTotal += (float) $providerBalanceRow['current_balance'];
    }
}
$businessRows = $finance->recentBusinessLedger(20);
$providerRows = $finance->recentProviderLedger(20);
$ownerBalanceRows = $finance->recentOwnerBalanceLedger(20);
$ownerRows = $ownerWithdrawals->recent(20);
$openingReconciliation = $finance->openingReconciliation();
$katPayPayoutConfigured = $katPayPayouts->isConfigured();
$katPayBanks = $katPayPayoutConfigured ? $katPayPayouts->banks() : [];

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

    <?php if ($ledgerReady && !$finance->openingReconciliationDone()): ?>
        <section class="rounded-2xl border border-gem-blue/20 bg-white p-5 shadow-card">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-widest text-gem-blue">One-time setup</p>
                <h2 class="mt-1 text-lg font-extrabold text-gem-text">Opening Capital and Opening Profit</h2>
                <p class="mt-1 text-[13px] text-gem-muted">Enter only verified opening owner capital and opening owner profit. These values are audited and can be initialized only once.</p>
            </div>
            <form class="mt-4 grid gap-3 md:grid-cols-4" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="initialize_opening_balances">
                <input class="rounded-xl border border-gem-border px-3 py-2" name="opening_capital" type="number" min="0" step="0.01" placeholder="Opening Capital" required>
                <input class="rounded-xl border border-gem-border px-3 py-2" name="opening_profit" type="number" min="0" step="0.01" placeholder="Opening Profit" required>
                <input class="rounded-xl border border-gem-border px-3 py-2" name="admin_password" type="password" placeholder="Admin password" required>
                <button class="rounded-xl bg-gem-blue px-4 py-2.5 font-bold text-white" type="submit">Initialize Once</button>
            </form>
        </section>
    <?php elseif ($ledgerReady && $openingReconciliation): ?>
        <section class="rounded-2xl border border-gem-border bg-white p-5 shadow-card">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-widest text-gem-muted">Opening reconciliation locked</p>
                    <h2 class="mt-1 text-lg font-extrabold text-gem-text"><?= e($openingReconciliation['reference']); ?></h2>
                    <p class="mt-1 text-[13px] text-gem-muted">Initialized by <?= e((string) ($openingReconciliation['admin_name'] ?? 'admin')); ?> on <?= e((string) $openingReconciliation['created_at']); ?>.</p>
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="rounded-xl border border-gem-border bg-gem-gray px-4 py-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-gem-muted">Opening Capital</p>
                        <p class="font-mono text-lg font-black text-gem-text"><?= e(money((float) $openingReconciliation['opening_capital'])); ?></p>
                    </div>
                    <div class="rounded-xl border border-gem-border bg-gem-gray px-4 py-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-gem-muted">Opening Profit</p>
                        <p class="font-mono text-lg font-black text-gem-text"><?= e(money((float) $openingReconciliation['opening_profit'])); ?></p>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <?php
        $cards = [
            [
                'label' => 'User Liability',
                'amount' => $summary['user_liability'] ?? 0,
                'note' => 'Wallets, commission wallets, unpaid withdrawals',
                'href' => base_url('admin/wallet.php'),
                'icon' => 'wallet',
                'tone' => 'wallet',
                'aria' => 'Open wallet liability details',
            ],
            [
                'label' => 'Pending Transactions',
                'amount' => $summary['pending_exposure'] ?? 0,
                'note' => 'Pending transaction exposure protected from withdrawal',
                'href' => base_url('admin/transactions.php?status=pending'),
                'icon' => 'pending',
                'tone' => 'pending',
                'aria' => 'Open pending transactions',
            ],
            [
                'label' => 'Safe Available Cash',
                'amount' => $summary['safe_available_cash'] ?? 0,
                'note' => 'Cash left after liabilities and pending exposure',
                'href' => base_url('admin/finance.php'),
                'icon' => 'shield',
                'tone' => 'security',
                'aria' => 'Open finance ledger safe available cash details',
            ],
            [
                'label' => 'Providers Balance',
                'amount' => $knownProviderBalanceTotal,
                'note' => 'Known current balances from configured providers',
                'href' => base_url('admin/finance.php') . '#provider-balances',
                'icon' => 'server',
                'tone' => 'providers',
                'aria' => 'Open provider balances section',
            ],
            [
                'label' => 'Successful Transactions',
                'amount' => $summary['gross_revenue'] ?? 0,
                'note' => 'Successful transaction selling value',
                'href' => base_url('admin/transactions.php?status=successful'),
                'icon' => 'transactions',
                'tone' => 'transactions',
                'aria' => 'Open successful transactions',
            ],
            [
                'label' => 'Provider Costs',
                'amount' => $summary['provider_costs'] ?? 0,
                'note' => 'Provider costs recognized from successful sales',
                'href' => base_url('admin/finance.php') . '#provider-wallet-ledger',
                'icon' => 'revenue',
                'tone' => 'revenue',
                'aria' => 'Open provider wallet ledger',
            ],
            [
                'label' => 'Available Capital',
                'amount' => $summary['available_capital'] ?? 0,
                'note' => 'Owner capital balance before safe-cash limit',
                'href' => base_url('admin/finance.php') . '#owner-transfers',
                'icon' => 'revenue',
                'tone' => 'capital',
                'aria' => 'Open owner capital transfers section',
            ],
            [
                'label' => 'Capital Withdrawable',
                'amount' => $summary['capital_return_withdrawable'] ?? 0,
                'note' => 'Capital limited by Safe Available Cash',
                'href' => base_url('admin/finance.php') . '#owner-transfers',
                'icon' => 'shield',
                'tone' => 'capital',
                'aria' => 'Open capital withdrawable details',
            ],
            [
                'label' => 'Available Profit',
                'amount' => $summary['available_profit'] ?? 0,
                'note' => 'Owner profit balance before safe-cash limit',
                'href' => base_url('admin/finance.php') . '#owner-transfers',
                'icon' => 'profit',
                'tone' => 'profit',
                'aria' => 'Open owner profit transfers section',
            ],
            [
                'label' => 'Profit Withdrawable',
                'amount' => $summary['profit_withdrawable'] ?? 0,
                'note' => 'Profit limited by Safe Available Cash',
                'href' => base_url('admin/finance.php') . '#owner-transfers',
                'icon' => 'profit',
                'tone' => 'profit',
                'aria' => 'Open profit withdrawable details',
            ],
        ];
        $metricToneClasses = [
            'wallet' => 'from-green-600 to-emerald-400 shadow-green-500/20',
            'pending' => 'from-amber-600 to-yellow-400 shadow-amber-500/20',
            'security' => 'from-rose-600 to-pink-400 shadow-rose-500/20',
            'providers' => 'from-purple-600 to-violet-400 shadow-purple-500/20',
            'transactions' => 'from-indigo-600 to-violet-400 shadow-indigo-500/20',
            'revenue' => 'from-emerald-600 to-teal-400 shadow-emerald-500/20',
            'capital' => 'from-sky-600 to-cyan-400 shadow-sky-500/20',
            'profit' => 'from-lime-600 to-lime-400 shadow-lime-500/20',
        ];
        ?>
        <?php foreach ($cards as $card): ?>
            <?php $iconTone = $metricToneClasses[$card['tone']] ?? 'from-slate-600 to-slate-400 shadow-slate-500/20'; ?>
            <a class="admin-click-card admin-metric-card group relative flex min-h-[9.25rem] flex-col justify-between gap-4 overflow-hidden rounded-2xl border border-gem-border bg-white p-5 text-gem-text no-underline shadow-card transition-all duration-200 hover:-translate-y-1 hover:border-gem-blue/30 hover:shadow-panel focus-visible:outline focus-visible:outline-4 focus-visible:outline-gem-blue/20" href="<?= e($card['href']); ?>" aria-label="<?= e($card['aria']); ?>">
                <div class="relative z-[1] flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="admin-metric-label m-0 text-[12px] font-extrabold uppercase tracking-wide text-gem-muted"><?= e($card['label']); ?></p>
                        <p class="admin-metric-value mt-3 max-w-full break-words font-mono text-[clamp(1.45rem,2.2vw,2rem)] font-black leading-tight text-gem-text"><?= e(money((float) $card['amount'])); ?></p>
                    </div>
                    <div class="admin-icon-box admin-icon-<?= e($card['tone']); ?> inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br <?= e($iconTone); ?> text-white shadow-lg">
                        <?= icon_svg($card['icon']); ?>
                    </div>
                </div>
                <div class="admin-card-footer relative z-[1] flex items-center justify-between gap-3">
                    <p class="min-w-0 text-[13px] font-semibold text-gem-muted"><?= e($card['note']); ?></p>
                    <span class="admin-card-chev inline-flex h-5 w-5 shrink-0 text-gem-muted transition-transform duration-200 group-hover:translate-x-1 group-hover:text-gem-blue"><?= icon_svg('chevron'); ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
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
            <h2 class="text-lg font-extrabold text-gem-text">Owner Transfer</h2>
            <p class="mt-1 text-[13px] text-gem-muted">Choose profit withdrawal or capital return. KatPay payout only marks paid after KatPay confirms success.</p>
            <?php if (!$katPayPayoutConfigured || $katPayBanks === []): ?>
                <div class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-[12px] font-semibold text-amber-800">
                    <?= e($katPayPayouts->configurationMessage()); ?>
                </div>
            <?php endif; ?>
            <form class="mt-4 space-y-3" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <select class="w-full rounded-xl border border-gem-border px-3 py-2" name="withdrawal_type" required>
                    <option value="profit">Profit Withdrawal - limit <?= e(money((float) ($summary['profit_withdrawable'] ?? 0))); ?></option>
                    <option value="capital_return">Capital Return - limit <?= e(money((float) ($summary['capital_return_withdrawable'] ?? 0))); ?></option>
                </select>
                <input class="w-full rounded-xl border border-gem-border px-3 py-2" name="amount" type="number" min="0.01" step="0.01" placeholder="Amount" required>
                <?php if ($katPayPayoutConfigured && $katPayBanks !== []): ?>
                    <select class="w-full rounded-xl border border-gem-border px-3 py-2" name="bank_code" required>
                        <option value="">Select KatPay bank</option>
                        <?php foreach ($katPayBanks as $bank): ?>
                            <option value="<?= e((string) $bank['bank_code']); ?>"><?= e((string) $bank['bank_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <input class="w-full rounded-xl border border-gem-border px-3 py-2" name="bank_name" placeholder="Bank Name for manual transfer"<?= $katPayPayoutConfigured && $katPayBanks !== [] ? '' : ' required'; ?>>
                <input class="w-full rounded-xl border border-gem-border px-3 py-2" name="account_number" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" placeholder="10-digit Account Number" required>
                <input class="w-full rounded-xl border border-gem-border px-3 py-2" name="account_name" placeholder="Account Name" required>
                <input class="w-full rounded-xl border border-gem-border px-3 py-2" name="transfer_reference" placeholder="Transfer Reference optional">
                <input class="w-full rounded-xl border border-gem-border px-3 py-2" name="notes" placeholder="Required note" required>
                <input class="w-full rounded-xl border border-gem-border px-3 py-2" name="admin_password" type="password" placeholder="Admin password" required>
                <div class="grid gap-2 sm:grid-cols-2">
                    <button class="rounded-xl bg-gem-orange px-4 py-2.5 font-bold text-white" type="submit" name="action" value="request_owner_withdrawal">Record Manual Transfer</button>
                    <button class="rounded-xl bg-gem-blue px-4 py-2.5 font-bold text-white disabled:cursor-not-allowed disabled:opacity-50" type="submit" name="action" value="send_owner_withdrawal_katpay"<?= (!$katPayPayoutConfigured || $katPayBanks === []) ? ' disabled aria-disabled="true"' : ''; ?>>Send via KatPay</button>
                </div>
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

    <section id="provider-balances" class="scroll-mt-24 rounded-2xl border border-gem-border bg-white p-5 shadow-card">
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

    <div class="grid gap-4 xl:grid-cols-4">
        <section id="owner-transfers" class="scroll-mt-24 rounded-2xl border border-gem-border bg-white p-5 shadow-card">
            <h2 class="text-lg font-extrabold text-gem-text">Owner Transfers</h2>
            <div class="mt-4 space-y-3">
                <?php foreach ($ownerRows as $row): ?>
                    <?php $isKatPayTransfer = (string) ($row['payout_provider'] ?? 'manual') === 'katpay'; ?>
                    <div class="rounded-2xl border border-gem-border bg-gem-gray p-3">
                        <div class="flex items-start justify-between gap-3"><div><p class="font-mono text-[12px] font-bold"><?= e($row['reference']); ?></p><p class="text-[12px] text-gem-muted"><?= e(str_replace('_', ' ', (string) ($row['withdrawal_type'] ?? 'profit'))); ?> / <?= e($row['status']); ?><?= $isKatPayTransfer ? ' / KatPay ' . e((string) ($row['payout_status'] ?? 'processing')) : ''; ?> by <?= e($row['requested_by_name']); ?></p><p class="mt-1 text-[12px] text-gem-muted"><?= e(trim((string) ($row['bank_name'] ?? '') . ' ' . mask_owner_account_number($row['account_number'] ?? '') . ' ' . (string) ($row['account_name'] ?? ''))); ?></p><?php if (!empty($row['transfer_reference'])): ?><p class="mt-1 font-mono text-[11px] text-gem-muted">Ref <?= e($row['transfer_reference']); ?></p><?php endif; ?></div><p class="font-mono font-black"><?= e(money((float) $row['amount'])); ?></p></div>
                        <?php if (in_array($row['status'], ['pending', 'approved'], true)): ?>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <?php if ($row['status'] === 'pending' && !$isKatPayTransfer): ?>
                                    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="action" value="approve_owner_withdrawal"><input type="hidden" name="withdrawal_id" value="<?= (int) $row['id']; ?>"><input class="mb-2 w-full rounded-xl border border-gem-border px-3 py-2 text-[12px]" name="admin_password" type="password" placeholder="Password" required><button class="rounded-xl bg-gem-blue px-3 py-2 text-[12px] font-bold text-white">Approve</button></form>
                                <?php endif; ?>
                                <?php if ($row['status'] === 'approved' && !$isKatPayTransfer): ?>
                                    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="action" value="mark_owner_withdrawal_paid"><input type="hidden" name="withdrawal_id" value="<?= (int) $row['id']; ?>"><input class="mb-2 w-full rounded-xl border border-gem-border px-3 py-2 text-[12px]" name="transfer_reference" placeholder="Transfer Reference optional" value="<?= e((string) ($row['transfer_reference'] ?? '')); ?>"><input class="mb-2 w-full rounded-xl border border-gem-border px-3 py-2 text-[12px]" name="admin_password" type="password" placeholder="Password" required><button class="rounded-xl bg-gem-green px-3 py-2 text-[12px] font-bold text-white">Mark Paid</button></form>
                                <?php endif; ?>
                                <?php if ($isKatPayTransfer): ?>
                                    <form method="post" onsubmit="return confirm('Only confirm paid if the money has already reached the destination account. Continue?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="action" value="confirm_payout_owner_withdrawal_paid"><input type="hidden" name="withdrawal_id" value="<?= (int) $row['id']; ?>"><input class="mb-2 w-full rounded-xl border border-gem-border px-3 py-2 text-[12px]" name="admin_password" type="password" placeholder="Password" required><button class="rounded-xl bg-gem-green px-3 py-2 text-[12px] font-bold text-white">Confirm Paid</button></form>
                                <?php endif; ?>
                                <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="action" value="reject_owner_withdrawal"><input type="hidden" name="withdrawal_id" value="<?= (int) $row['id']; ?>"><input class="mb-2 w-full rounded-xl border border-gem-border px-3 py-2 text-[12px]" name="notes" placeholder="Reason" required><input class="mb-2 w-full rounded-xl border border-gem-border px-3 py-2 text-[12px]" name="admin_password" type="password" placeholder="Password" required><button class="rounded-xl bg-gem-red px-3 py-2 text-[12px] font-bold text-white">Reject</button></form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($ownerRows === []): ?><p class="text-[13px] text-gem-muted">No owner transfers yet.</p><?php endif; ?>
            </div>
        </section>

        <section id="owner-balance-ledger" class="scroll-mt-24 rounded-2xl border border-gem-border bg-white p-5 shadow-card">
            <h2 class="text-lg font-extrabold text-gem-text">Owner balance ledger</h2>
            <p class="mt-1 text-[13px] text-gem-muted">Capital and profit accounting entries. Withdrawals remain limited by Safe Available Cash.</p>
            <div class="mt-4 space-y-2">
                <?php foreach ($ownerBalanceRows as $row): ?>
                    <?php
                    $sourceLabel = '';
                    if (!empty($row['transaction_reference'])) {
                        $sourceLabel = 'Txn ' . $row['transaction_reference'];
                    } elseif (!empty($row['owner_withdrawal_reference'])) {
                        $sourceLabel = 'Owner transfer ' . $row['owner_withdrawal_reference'];
                    } elseif (!empty($row['admin_name'])) {
                        $sourceLabel = 'Admin ' . $row['admin_name'];
                    }
                    ?>
                    <div class="rounded-xl border border-gem-border p-3 text-[13px]">
                        <div class="flex justify-between gap-3">
                            <span class="font-bold"><?= e($row['balance_type'] . ' ' . $row['entry_type']); ?></span>
                            <span class="font-mono <?= $row['direction'] === 'in' ? 'text-gem-green' : 'text-gem-orange'; ?>"><?= e(($row['direction'] === 'in' ? '+' : '-') . money((float) $row['amount'])); ?></span>
                        </div>
                        <p class="mt-1 text-[12px] text-gem-muted"><?= e($sourceLabel !== '' ? $sourceLabel : ($row['notes'] ?? '')); ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if ($ownerBalanceRows === []): ?><p class="text-[13px] text-gem-muted">No owner balance ledger rows yet.</p><?php endif; ?>
            </div>
        </section>

        <section id="business-cash-ledger" class="scroll-mt-24 rounded-2xl border border-gem-border bg-white p-5 shadow-card">
            <h2 class="text-lg font-extrabold text-gem-text">Business cash ledger</h2>
            <div class="mt-4 space-y-2">
                <?php foreach ($businessRows as $row): ?>
                    <div class="rounded-xl border border-gem-border p-3 text-[13px]"><div class="flex justify-between gap-3"><span class="font-bold"><?= e($row['entry_type']); ?></span><span class="font-mono <?= $row['direction'] === 'in' ? 'text-gem-green' : 'text-gem-orange'; ?>"><?= e(($row['direction'] === 'in' ? '+' : '-') . money((float) $row['amount'])); ?></span></div><p class="mt-1 text-[12px] text-gem-muted"><?= e($row['notes'] ?? ''); ?></p></div>
                <?php endforeach; ?>
                <?php if ($businessRows === []): ?><p class="text-[13px] text-gem-muted">No business cash ledger rows yet.</p><?php endif; ?>
            </div>
        </section>

        <section id="provider-wallet-ledger" class="scroll-mt-24 rounded-2xl border border-gem-border bg-white p-5 shadow-card">
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
