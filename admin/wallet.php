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
    $idempotencyKey = trim((string) ($_POST['idempotency_key'] ?? ''));
    if ($idempotencyKey === '') {
        $idempotencyKey = fresh_idempotency_key('admin-wallet');
    }
    if ($_POST['type'] === 'credit') {
        app(\GemData\Classes\Wallet::class)->credit($userId, $amount, 'Admin wallet credit', 'admin', ['admin_id' => $admin['id'], 'reason' => $reason], 'admin_adjustment', null, $idempotencyKey, $reason);
    } else {
        app(\GemData\Classes\Wallet::class)->debit($userId, $amount, 'Admin wallet debit', 'admin', ['admin_id' => $admin['id'], 'reason' => $reason], 'admin_adjustment', null, $idempotencyKey, $reason);
    }
    app(\GemData\Classes\ActivityLogger::class)->log('admin', (int) $admin['id'], 'wallet_adjustment', 'Admin adjusted user wallet.', ['user_id' => $userId, 'type' => $_POST['type'], 'amount' => $amount, 'reason' => $reason]);
    flash('success', 'Wallet updated successfully.');
    redirect(base_url('admin/wallet.php?user_id=' . $userId));
}

if (!$targetUser && is_post() && ($_POST['action'] ?? '') === 'provider_balance') {
    verify_csrf();
    $providerManager->logBalance((int) $_POST['provider_id'], (float) $_POST['balance_amount'], 'manual', trim((string) ($_POST['notes'] ?? '')));
    app(\GemData\Classes\ActivityLogger::class)->log('admin', (int) $admin['id'], 'provider_balance_logged', 'Admin logged provider balance.', ['provider_id' => (int) $_POST['provider_id']]);
    flash('success', 'Provider balance logged successfully.');
    redirect(base_url('admin/wallet.php'));
}

$wallet = $targetUser ? app(\GemData\Classes\Wallet::class)->ensure($userId) : null;
$history = $targetUser ? db()->query('SELECT * FROM wallet_transactions WHERE user_id = :user_id ORDER BY id DESC LIMIT 20', ['user_id' => $userId]) : [];
$systemBalance = (float) (db()->first('SELECT COALESCE(SUM(balance), 0) AS total FROM wallets')['total'] ?? 0);

render_header('Wallet Control', 'admin');
?>
<?php if (!$targetUser): ?>
    <div class="space-y-6">
        <?php if ($message = flash('success')): ?><div class="notice notice-success"><?= e($message); ?></div><?php endif; ?>
        <div class="grid gap-4 md:grid-cols-3">
            <div class="surface-card p-5"><p class="text-slate-400">System Wallet Balance</p><p class="mt-2 text-3xl font-black text-white"><?= e(money($systemBalance)); ?></p></div>
            <div class="surface-card p-5"><p class="text-slate-400">Users With Wallet</p><p class="mt-2 text-3xl font-black text-white"><?= (int) $walletUsersCount; ?></p></div>
            <div class="surface-card p-5"><p class="text-slate-400">Providers</p><p class="mt-2 text-3xl font-black text-white"><?= count($providers); ?></p></div>
        </div>
        <div class="grid gap-6 xl:grid-cols-[1.1fr,0.9fr]">
            <div class="surface-card p-6">
                <p class="eyebrow">Wallet Control</p>
                <h1 class="mt-3 text-3xl font-black text-white">Select a user wallet to manage</h1>
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
