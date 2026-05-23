<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('wallet.manage');
$userId = (int) ($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
$targetUser = db()->first('SELECT * FROM users WHERE id = :id', ['id' => $userId]);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$walletUsersCount = (int) ((db()->first('SELECT COUNT(*) AS total FROM users')['total'] ?? 0));
$pagination = pagination_meta($walletUsersCount, $page, $perPage);
$walletUsers = db()->query(
    'SELECT u.id, u.full_name, u.email, COALESCE(w.balance, 0) AS balance
     FROM users u
     LEFT JOIN wallets w ON w.user_id = u.id
     ORDER BY u.full_name
     LIMIT ' . $pagination['offset'] . ', ' . $pagination['per_page']
);
$providerManager = app(\GemData\Classes\ProviderManager::class);
$providers = $providerManager->allProviders();
$walletService = app(\GemData\Classes\Wallet::class);
$activityLogger = app(\GemData\Classes\ActivityLogger::class);
$hasAdjustmentLogs = db()->tableExists('wallet_adjustment_logs');
$hasRefundLogs = db()->tableExists('refund_logs');
$hasSettlementReconciliations = db()->tableExists('settlement_reconciliations');
$hasProviderExpenseLogs = db()->tableExists('provider_expense_logs');

if ($targetUser && is_post()) {
    verify_csrf();
    $amount = max(0, (float) ($_POST['amount'] ?? 0));
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    if (!auth()->confirmAdminPassword((int) $admin['id'], $adminPassword)) {
        flash('success', 'Admin password confirmation failed. Wallet was not changed.');
        redirect(base_url('admin/wallet.php?user_id=' . $userId));
    }
    if ($reason === '') {
        flash('success', 'A reason is required for wallet adjustments.');
        redirect(base_url('admin/wallet.php?user_id=' . $userId));
    }
    if ($amount <= 0) {
        flash('success', 'Enter an adjustment amount greater than zero.');
        redirect(base_url('admin/wallet.php?user_id=' . $userId));
    }
    $idempotencyKey = trim((string) ($_POST['idempotency_key'] ?? ''));
    if ($idempotencyKey === '') {
        $idempotencyKey = fresh_idempotency_key('admin-wallet');
    }
    $type = (string) ($_POST['type'] ?? 'debit');
    if (!in_array($type, ['credit', 'debit'], true)) {
        flash('success', 'Unsupported wallet adjustment type.');
        redirect(base_url('admin/wallet.php?user_id=' . $userId));
    }

    try {
        $record = $type === 'credit'
            ? $walletService->credit($userId, $amount, 'Admin wallet credit', 'admin', ['admin_id' => $admin['id'], 'reason' => $reason], 'admin_adjustment', null, $idempotencyKey, $reason)
            : $walletService->debit($userId, $amount, 'Admin wallet debit', 'admin', ['admin_id' => $admin['id'], 'reason' => $reason], 'admin_adjustment', null, $idempotencyKey, $reason);

        if ($hasAdjustmentLogs) {
            db()->execute(
                'INSERT IGNORE INTO wallet_adjustment_logs
                    (wallet_transaction_id, admin_id, user_id, adjustment_type, amount, balance_before, balance_after, reason, idempotency_key)
                 VALUES
                    (:wallet_transaction_id, :admin_id, :user_id, :adjustment_type, :amount, :balance_before, :balance_after, :reason, :idempotency_key)',
                [
                    'wallet_transaction_id' => $record['id'] ?? null,
                    'admin_id' => (int) $admin['id'],
                    'user_id' => $userId,
                    'adjustment_type' => $type,
                    'amount' => (float) ($record['amount'] ?? $amount),
                    'balance_before' => (float) ($record['balance_before'] ?? 0),
                    'balance_after' => (float) ($record['balance_after'] ?? 0),
                    'reason' => $reason,
                    'idempotency_key' => $idempotencyKey,
                ]
            );
        }

        $activityLogger->log('admin', (int) $admin['id'], 'wallet_adjustment', 'Admin adjusted user wallet.', ['user_id' => $userId, 'type' => $type, 'amount' => $amount, 'reason' => $reason]);
        flash('success', 'Wallet updated successfully.');
    } catch (Throwable $throwable) {
        flash('success', 'Wallet update failed: ' . $throwable->getMessage());
    }
    redirect(base_url('admin/wallet.php?user_id=' . $userId));
}

if (!$targetUser && is_post() && ($_POST['action'] ?? '') === 'provider_balance') {
    verify_csrf();
    $providerManager->logBalance((int) $_POST['provider_id'], (float) $_POST['balance_amount'], 'manual', trim((string) ($_POST['notes'] ?? '')));
    $activityLogger->log('admin', (int) $admin['id'], 'provider_balance_logged', 'Admin logged provider balance.', ['provider_id' => (int) $_POST['provider_id']]);
    flash('success', 'Provider balance logged successfully.');
    redirect(base_url('admin/wallet.php'));
}

$wallet = $targetUser ? $walletService->ensure($userId) : null;
$history = $targetUser ? db()->query('SELECT * FROM wallet_transactions WHERE user_id = :user_id ORDER BY id DESC LIMIT 20', ['user_id' => $userId]) : [];
$systemBalance = (float) (db()->first('SELECT COALESCE(SUM(balance), 0) AS total FROM wallets')['total'] ?? 0);
$todayFunding = (float) (db()->first('SELECT COALESCE(SUM(amount), 0) AS total FROM wallet_transactions WHERE type = "credit" AND source_type = "funding" AND DATE(created_at) = CURDATE()')['total'] ?? 0);
$todayRefunds = (float) (db()->first('SELECT COALESCE(SUM(amount), 0) AS total FROM wallet_transactions WHERE type = "refund" AND DATE(created_at) = CURDATE()')['total'] ?? 0);
$todayRevenue = (float) (db()->first('SELECT COALESCE(SUM(selling_price), 0) AS total FROM transactions WHERE status = "successful" AND DATE(created_at) = CURDATE()')['total'] ?? 0);
$todayProfit = (float) (db()->first('SELECT COALESCE(SUM(profit_amount), 0) AS total FROM transactions WHERE status = "successful" AND DATE(created_at) = CURDATE()')['total'] ?? 0);
$pendingReconciliationCount = (int) (db()->first('SELECT COUNT(*) AS total FROM transactions WHERE status = "pending" OR failure_code IN ("provider_timeout","all_providers_failed","provider_error")')['total'] ?? 0);
$recentAdjustments = $hasAdjustmentLogs ? db()->query(
    'SELECT wal.*, u.full_name, u.email, a.full_name AS admin_name
     FROM wallet_adjustment_logs wal
     INNER JOIN users u ON u.id = wal.user_id
     INNER JOIN admins a ON a.id = wal.admin_id
     ORDER BY wal.id DESC LIMIT 12'
) : [];
$refundRows = $hasRefundLogs ? db()->query(
    'SELECT rl.*, u.full_name, u.email, a.full_name AS admin_name
     FROM refund_logs rl
     INNER JOIN users u ON u.id = rl.user_id
     LEFT JOIN admins a ON a.id = rl.admin_id
     ORDER BY rl.id DESC LIMIT 12'
) : db()->query(
    'SELECT wt.*, u.full_name, u.email, NULL AS admin_name, wt.narration AS reason, "completed" AS status
     FROM wallet_transactions wt
     INNER JOIN users u ON u.id = wt.user_id
     WHERE wt.type = "refund"
     ORDER BY wt.id DESC LIMIT 12'
);
$fundingRows = db()->query(
    'SELECT wfr.*, u.full_name, u.email
     FROM wallet_funding_requests wfr
     INNER JOIN users u ON u.id = wfr.user_id
     ORDER BY wfr.id DESC LIMIT 12'
);
$providerBalanceRows = db()->query(
    'SELECT pbl.*, pa.name AS provider_name, pa.code AS provider_code, pa.low_balance_threshold
     FROM provider_balance_logs pbl
     INNER JOIN provider_accounts pa ON pa.id = pbl.provider_account_id
     ORDER BY pbl.id DESC LIMIT 12'
);
$providerExpenseRows = $hasProviderExpenseLogs ? db()->query(
    'SELECT pel.*, pa.name AS provider_name
     FROM provider_expense_logs pel
     LEFT JOIN provider_accounts pa ON pa.id = pel.provider_account_id
     ORDER BY pel.id DESC LIMIT 12'
) : db()->query(
    'SELECT provider_code, COUNT(*) AS total_transactions,
            COALESCE(SUM(CASE WHEN status = "successful" THEN selling_price - profit_amount ELSE 0 END), 0) AS cost_amount,
            COALESCE(SUM(CASE WHEN status = "successful" THEN selling_price ELSE 0 END), 0) AS selling_amount,
            COALESCE(SUM(CASE WHEN status = "successful" THEN profit_amount ELSE 0 END), 0) AS profit_amount
     FROM transactions
     GROUP BY provider_code
     ORDER BY cost_amount DESC LIMIT 12'
);
$reconciliationRows = db()->query(
    'SELECT t.*, u.full_name, s.name AS service_name
     FROM transactions t
     INNER JOIN users u ON u.id = t.user_id
     INNER JOIN services s ON s.id = t.service_id
     WHERE t.status = "pending" OR t.failure_code IN ("provider_timeout","all_providers_failed","provider_error")
     ORDER BY t.id DESC LIMIT 12'
);

render_header('Wallet Control', 'admin');
?>
<?php if (!$targetUser): ?>
    <div class="space-y-6">
        <?php if ($message = flash('success')): ?><div class="notice notice-success"><?= e($message); ?></div><?php endif; ?>
        <section class="surface-card p-6">
            <p class="eyebrow">Finance Operations</p>
            <h1 class="mt-2 text-3xl font-black text-white">Wallet, refunds, and reconciliation</h1>
            <p class="mt-2 max-w-3xl text-slate-400">Monitor platform liability, provider balances, funding logs, refunds, and transactions that need reconciliation without changing the underlying wallet safety model.</p>
        </section>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <div class="surface-card p-5"><p class="text-slate-400">Wallet Liability</p><p class="mt-2 text-3xl font-black text-white"><?= e(money($systemBalance)); ?></p></div>
            <div class="surface-card p-5"><p class="text-slate-400">Funding Today</p><p class="mt-2 text-3xl font-black text-white"><?= e(money($todayFunding)); ?></p></div>
            <div class="surface-card p-5"><p class="text-slate-400">Refunds Today</p><p class="mt-2 text-3xl font-black text-white"><?= e(money($todayRefunds)); ?></p></div>
            <div class="surface-card p-5"><p class="text-slate-400">Revenue Today</p><p class="mt-2 text-3xl font-black text-white"><?= e(money($todayRevenue)); ?></p></div>
            <div class="surface-card p-5"><p class="text-slate-400">Profit Today</p><p class="mt-2 text-3xl font-black text-white"><?= e(money($todayProfit)); ?></p></div>
            <div class="surface-card p-5"><p class="text-slate-400">Reconciliation Queue</p><p class="mt-2 text-3xl font-black text-white"><?= (int) $pendingReconciliationCount; ?></p></div>
        </div>
        <div class="grid gap-6 xl:grid-cols-[1.1fr,0.9fr]">
            <div class="surface-card p-6">
                <p class="eyebrow">Wallet Control</p>
                <h2 class="mt-3 text-2xl font-black text-white">Select a user wallet to manage</h2>
                <div class="table-shell mt-6">
                    <table>
                        <thead><tr class="text-slate-400"><th>User</th><th>Email</th><th>Balance</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($walletUsers as $walletUser): ?>
                                <tr data-search-item data-search="<?= e($walletUser['full_name'] . ' ' . $walletUser['email'] . ' ' . $walletUser['balance']); ?>">
                                    <td><?= e($walletUser['full_name']); ?></td>
                                    <td><?= e($walletUser['email']); ?></td>
                                    <td><?= e(money($walletUser['balance'])); ?></td>
                                    <td><a class="secondary-action inline-flex items-center justify-center" href="<?= e(base_url('admin/wallet.php?user_id=' . $walletUser['id'])); ?>">Manage Wallet</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">
                    <?php render_pagination($pagination, 'admin/wallet.php'); ?>
                </div>
            </div>
            <div class="surface-card p-6">
                <h2 class="text-2xl font-bold text-white">Provider Balance Tracking</h2>
                <div class="space-y-4 mt-4">
                    <?php foreach ($providers as $provider): ?>
                        <?php $latest = $providerManager->latestBalanceLog((int) $provider['id']); ?>
                        <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-white"><?= e($provider['name']); ?></p>
                                    <p class="text-xs text-slate-400">Latest balance: <?= e(money($latest['balance_amount'] ?? 0)); ?></p>
                                </div>
                                <span class="text-xs text-slate-400">Threshold <?= e(money($provider['low_balance_threshold'])); ?></span>
                            </div>
                            <form method="post" class="mt-4 grid gap-3 md:grid-cols-[1fr,1fr,auto]">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="provider_balance">
                                <input type="hidden" name="provider_id" value="<?= (int) $provider['id']; ?>">
                                <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="balance_amount" placeholder="Balance amount">
                                <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="notes" placeholder="Notes">
                                <button class="rounded-lg border border-white/10 px-4 py-3 font-semibold" type="submit">Log</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="grid gap-6 xl:grid-cols-2">
            <section class="surface-card p-6">
                <h2 class="text-2xl font-bold text-white">Recent Wallet Adjustments</h2>
                <div class="table-shell mt-4">
                    <table>
                        <thead><tr class="text-slate-400"><th>User</th><th>Type</th><th>Amount</th><th>Before</th><th>After</th><th>Admin</th><th>Reason</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentAdjustments as $row): ?>
                                <tr>
                                    <td><div class="font-semibold text-white"><?= e($row['full_name']); ?></div><div class="text-xs text-slate-400"><?= e($row['email']); ?></div></td>
                                    <td><?= e(ucfirst((string) $row['adjustment_type'])); ?></td>
                                    <td><?= e(money($row['amount'])); ?></td>
                                    <td><?= e(money($row['balance_before'])); ?></td>
                                    <td><?= e(money($row['balance_after'])); ?></td>
                                    <td><?= e($row['admin_name']); ?></td>
                                    <td><span class="text-xs text-slate-400"><?= e($row['reason']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($recentAdjustments === []): ?><tr><td colspan="7" class="text-slate-400">No wallet adjustment log entries yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="surface-card p-6">
                <h2 class="text-2xl font-bold text-white">Refund Logs</h2>
                <div class="table-shell mt-4">
                    <table>
                        <thead><tr class="text-slate-400"><th>User</th><th>Amount</th><th>Status</th><th>Reason</th><th>Admin</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($refundRows as $row): ?>
                                <tr>
                                    <td><div class="font-semibold text-white"><?= e($row['full_name']); ?></div><div class="text-xs text-slate-400"><?= e($row['email']); ?></div></td>
                                    <td><?= e(money($row['amount'])); ?></td>
                                    <td><span class="status-chip status-<?= e($row['status'] ?? 'completed'); ?>"><?= e(ucfirst((string) ($row['status'] ?? 'completed'))); ?></span></td>
                                    <td><span class="text-xs text-slate-400"><?= e($row['reason'] ?? $row['narration'] ?? 'Refund'); ?></span></td>
                                    <td><?= e($row['admin_name'] ?? 'System'); ?></td>
                                    <td><?= e($row['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($refundRows === []): ?><tr><td colspan="6" class="text-slate-400">No refunds recorded yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="grid gap-6 xl:grid-cols-2">
            <section class="surface-card p-6">
                <h2 class="text-2xl font-bold text-white">Funding Logs</h2>
                <div class="table-shell mt-4">
                    <table>
                        <thead><tr class="text-slate-400"><th>User</th><th>Reference</th><th>Provider</th><th>Status</th><th>Amount</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($fundingRows as $row): ?>
                                <tr>
                                    <td><div class="font-semibold text-white"><?= e($row['full_name']); ?></div><div class="text-xs text-slate-400"><?= e($row['email']); ?></div></td>
                                    <td class="font-mono text-xs"><?= e($row['reference']); ?></td>
                                    <td><?= e($row['provider']); ?></td>
                                    <td><span class="status-chip status-<?= e($row['status']); ?>"><?= e(ucfirst((string) $row['status'])); ?></span></td>
                                    <td><?= e(money($row['amount'])); ?></td>
                                    <td><?= e($row['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($fundingRows === []): ?><tr><td colspan="6" class="text-slate-400">No funding requests recorded yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="surface-card p-6">
                <h2 class="text-2xl font-bold text-white">Provider Balance Logs</h2>
                <div class="table-shell mt-4">
                    <table>
                        <thead><tr class="text-slate-400"><th>Provider</th><th>Balance</th><th>Threshold</th><th>Source</th><th>Notes</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($providerBalanceRows as $row): ?>
                                <tr>
                                    <td><div class="font-semibold text-white"><?= e($row['provider_name']); ?></div><div class="text-xs text-slate-400"><?= e($row['provider_code']); ?></div></td>
                                    <td><?= e(money($row['balance_amount'])); ?></td>
                                    <td><?= e(money($row['low_balance_threshold'])); ?></td>
                                    <td><?= e($row['source']); ?></td>
                                    <td><span class="text-xs text-slate-400"><?= e($row['notes'] ?? ''); ?></span></td>
                                    <td><?= e($row['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($providerBalanceRows === []): ?><tr><td colspan="6" class="text-slate-400">No provider balance logs recorded yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="grid gap-6 xl:grid-cols-2">
            <section class="surface-card p-6">
                <h2 class="text-2xl font-bold text-white">Provider Expenses</h2>
                <div class="table-shell mt-4">
                    <table>
                        <thead><tr class="text-slate-400"><th>Provider</th><th>Cost</th><th>Selling</th><th>Profit</th><th>Source</th></tr></thead>
                        <tbody>
                            <?php foreach ($providerExpenseRows as $row): ?>
                                <tr>
                                    <td><?= e($row['provider_name'] ?? $row['provider_code'] ?? 'unassigned'); ?></td>
                                    <td><?= e(money($row['cost_amount'])); ?></td>
                                    <td><?= e(money($row['selling_amount'])); ?></td>
                                    <td><?= e(money($row['profit_amount'])); ?></td>
                                    <td><?= e($row['source'] ?? (($row['total_transactions'] ?? 0) . ' txns')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($providerExpenseRows === []): ?><tr><td colspan="5" class="text-slate-400">No provider expense data recorded yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="surface-card p-6">
                <h2 class="text-2xl font-bold text-white">Reconciliation Queue</h2>
                <div class="table-shell mt-4">
                    <table>
                        <thead><tr class="text-slate-400"><th>Reference</th><th>User</th><th>Service</th><th>Status</th><th>Amount</th><th>Failure</th></tr></thead>
                        <tbody>
                            <?php foreach ($reconciliationRows as $row): ?>
                                <tr>
                                    <td class="font-mono text-xs"><?= e($row['reference']); ?></td>
                                    <td><?= e($row['full_name']); ?></td>
                                    <td><?= e($row['service_name']); ?></td>
                                    <td><span class="status-chip status-<?= e($row['status']); ?>"><?= e(ucfirst((string) $row['status'])); ?></span></td>
                                    <td><?= e(money($row['amount'])); ?></td>
                                    <td><span class="text-xs text-slate-400"><?= e($row['failure_code'] ?? 'Awaiting provider confirmation'); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($reconciliationRows === []): ?><tr><td colspan="6" class="text-slate-400">No transactions require reconciliation right now.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
<?php else: ?>
    <div class="grid gap-6 lg:grid-cols-[0.8fr,1.2fr]">
        <section class="surface-card p-6">
            <h1 class="text-3xl font-black text-white"><?= e($targetUser['full_name']); ?></h1>
            <p class="mt-2 text-slate-300">Current balance: <?= e(money($wallet['balance'])); ?></p>
            <?php if ($message = flash('success')): ?><div class="notice notice-success mt-4"><?= e($message); ?></div><?php endif; ?>
            <form method="post" class="mt-6 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="user_id" value="<?= (int) $userId; ?>">
                <input type="hidden" name="idempotency_key" value="<?= e(fresh_idempotency_key('admin-wallet')); ?>">
                <input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="amount" placeholder="Amount">
                <input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="reason" placeholder="Reason for adjustment" required>
                <input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="admin_password" type="password" placeholder="Confirm admin password" required>
                <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4 text-sm text-slate-300">
                    This action updates a real wallet balance. Confirm the amount, reason, and account before submitting.
                </div>
                <div class="flex gap-3">
                    <button class="rounded-lg bg-emerald-400 px-5 py-3 font-semibold text-slate-950" type="submit" name="type" value="credit">Credit Wallet</button>
                    <button class="rounded-lg bg-rose-400 px-5 py-3 font-semibold text-slate-950" type="submit" name="type" value="debit">Debit Wallet</button>
                </div>
            </form>
        </section>
        <section class="surface-card p-6">
            <h2 class="text-2xl font-bold text-white">Wallet Audit Trail</h2>
            <div class="table-shell mt-4">
                <table>
                    <thead><tr class="text-slate-400"><th>Reference</th><th>Type</th><th>Source</th><th>Amount</th><th>Before</th><th>After</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($history as $row): ?>
                            <tr data-search-item data-search="<?= e($row['reference'] . ' ' . $row['type'] . ' ' . $row['amount']); ?>">
                                <td><?= e($row['reference']); ?></td>
                                <td><?= e($row['type']); ?></td>
                                <td><?= e(($row['source_type'] ?? 'legacy') . (!empty($row['reason_code']) ? ' / ' . $row['reason_code'] : '')); ?></td>
                                <td><?= e(money($row['amount'])); ?></td>
                                <td><?= e(money($row['balance_before'])); ?></td>
                                <td><?= e(money($row['balance_after'])); ?></td>
                                <td><?= e($row['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
<?php endif; ?>
<?php render_footer(); ?>
