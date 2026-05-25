<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('users.view');
$userId = (int) ($_GET['user_id'] ?? 0);
$user = db()->first('SELECT u.*, w.balance FROM users u LEFT JOIN wallets w ON w.user_id = u.id WHERE u.id = :id', ['id' => $userId]);
if (!$user) {
    redirect(base_url('admin/users.php'));
}

$fundingAccounts = app(\GemData\Classes\FundingAccountProviderService::class);
if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if (in_array($action, ['generate_funding_account', 'generate_katpay_account'], true)) {
        require_permission('users.manage');
        try {
            $account = $action === 'generate_katpay_account'
                ? $fundingAccounts->ensureAccountForProvider('katpay', $userId, true, (int) $admin['id'])
                : $fundingAccounts->ensureActiveAccountForUser($userId, true, (int) $admin['id']);
            if (($account['status'] ?? '') === 'assigned') {
                flash('success', 'Funding account is assigned.');
            } elseif (($account['status'] ?? '') === 'pending') {
                flash('success', 'Funding account assignment has been requested.');
            } else {
                flash('error', 'Funding account generation failed. Review the safe status below.');
            }
        } catch (Throwable $throwable) {
            app_logger()->warning('Admin funding account generation failed.', [
                'admin_id' => $admin['id'] ?? null,
                'user_id' => $userId,
                'error' => $throwable->getMessage(),
            ]);
            flash('error', 'Funding account generation failed. Review configuration and schema readiness.');
        }

        redirect(base_url('admin/user-detail.php?user_id=' . $userId));
    }
}

$transactions = db()->query(
    'SELECT t.*, s.name AS service_name
     FROM transactions t
     INNER JOIN services s ON s.id = t.service_id
     WHERE t.user_id = :user_id
     ORDER BY t.id DESC LIMIT 20',
    ['user_id' => $userId]
);
$walletRows = db()->query('SELECT * FROM wallet_transactions WHERE user_id = :user_id ORDER BY id DESC LIMIT 20', ['user_id' => $userId]);
$activity = db()->query('SELECT * FROM activity_logs WHERE actor_id = :actor_id AND actor_type = "user" ORDER BY id DESC LIMIT 20', ['actor_id' => $userId]);
$apiUser = db()->first('SELECT au.*, ak.api_key, ak.status AS key_status FROM api_users au LEFT JOIN api_keys ak ON ak.api_user_id = au.id WHERE au.user_id = :user_id LIMIT 1', ['user_id' => $userId]);
$fundingAccount = $fundingAccounts->getActiveAccountForUser($userId);
$allFundingAccounts = $fundingAccounts->allAccountsForUser($userId);
$activeFundingProvider = $fundingAccounts->activeProvider();
$fundingStatus = (string) ($fundingAccount['status'] ?? 'pending');
$fundingAssigned = $fundingStatus === 'assigned' && trim((string) ($fundingAccount['dedicated_account_number'] ?? '')) !== '';
$customPrices = db()->query(
    'SELECT ucp.*, s.name AS service_name
     FROM user_custom_prices ucp
     INNER JOIN services s ON s.id = ucp.service_id
     WHERE ucp.user_id = :user_id
     ORDER BY s.name',
    ['user_id' => $userId]
);

render_header('User Detail', 'admin');
?>
<div class="space-y-6">
    <section class="surface-card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="eyebrow">User Profile</p>
                <h1 class="mt-2 text-3xl font-black text-white"><?= e($user['full_name']); ?></h1>
                <p class="mt-2 text-slate-300"><?= e($user['email']); ?> · <?= e($user['phone']); ?></p>
            </div>
            <div class="grid gap-2 text-right">
                <span class="text-sm text-slate-400">Tier: <strong class="text-white"><?= e($user['tier']); ?></strong></span>
                <span class="text-sm text-slate-400">Wallet: <strong class="text-white"><?= e(money($user['balance'] ?? 0)); ?></strong></span>
                <span class="text-sm text-slate-400">Status: <strong class="text-white"><?= e($user['status']); ?></strong></span>
            </div>
        </div>
    </section>

    <?php if ($message = flash('success')): ?><div class="notice notice-success"><?= e($message); ?></div><?php endif; ?>
    <?php if ($message = flash('error')): ?><div class="notice notice-error"><?= e($message); ?></div><?php endif; ?>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="surface-card p-6">
            <h2 class="text-2xl font-black text-white">API & Pricing Summary</h2>
            <div class="mt-4 space-y-3">
                <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4">
                    <p class="text-sm text-slate-400">API Access</p>
                    <p class="mt-1 text-white"><?= $apiUser ? e($apiUser['status']) : 'disabled'; ?></p>
                    <p class="mt-1 font-mono text-xs text-slate-400"><?= e($apiUser['api_key'] ?? 'No API key'); ?></p>
                </div>
                <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4">
                    <p class="text-sm text-slate-400">Referral Code</p>
                    <p class="mt-1 text-white"><?= e($user['referral_code'] ?: '-'); ?></p>
                </div>
                <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-sm text-slate-400">Funding Account</p>
                            <p class="mt-1 text-white"><?= e($fundingAssigned ? 'assigned' : $fundingStatus); ?></p>
                            <?php if ($fundingAssigned): ?>
                                <p class="mt-1 text-xs text-slate-400"><?= e((string) ($fundingAccount['bank_name'] ?? '')); ?> / <?= e((string) ($fundingAccount['dedicated_account_number'] ?? '')); ?></p>
                            <?php elseif ($fundingStatus === 'failed'): ?>
                                <p class="mt-1 text-xs text-slate-400"><?= e((string) ($fundingAccount['last_error_message'] ?? 'Generation failed.')); ?></p>
                            <?php else: ?>
                                <p class="mt-1 text-xs text-slate-400">Active provider: <?= e(ucfirst($activeFundingProvider)); ?>. Assignment is pending or not requested yet.</p>
                            <?php endif; ?>
                        </div>
                        <?php if (admin_can('users.manage')): ?>
                            <div class="flex flex-wrap gap-2">
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="generate_funding_account">
                                    <button class="secondary-action inline-flex items-center justify-center px-3 py-2 text-sm" type="submit">Retrieve/Generate Funding Account</button>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="generate_katpay_account">
                                    <button class="secondary-action inline-flex items-center justify-center px-3 py-2 text-sm" type="submit">Retry KatPay</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="table-shell mt-5">
                <table>
                    <thead><tr class="text-slate-400"><th>Provider</th><th>Account</th><th>Status</th><th>Requested</th><th>Assigned</th><th>Safe Message</th></tr></thead>
                    <tbody>
                    <?php if ($allFundingAccounts === []): ?>
                        <tr><td colspan="6" class="text-slate-400">No funding account rows yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($allFundingAccounts as $row): ?>
                            <tr>
                                <td><?= e(ucwords(str_replace('_', ' ', (string) $row['provider']))); ?></td>
                                <td><span class="font-mono"><?= e((string) ($row['dedicated_account_number'] ?? '-')); ?></span><br><span class="text-xs text-slate-400"><?= e((string) ($row['bank_name'] ?? '')); ?> <?= e((string) ($row['account_name'] ?? '')); ?></span></td>
                                <td><?= e((string) ($row['status'] ?? 'pending')); ?></td>
                                <td><?= e((string) ($row['requested_at'] ?? '-')); ?></td>
                                <td><?= e((string) ($row['assigned_at'] ?? '-')); ?></td>
                                <td><?= e((string) ($row['last_error_message'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-shell mt-5">
                <table>
                    <thead><tr class="text-slate-400"><th>Custom Price</th><th>Network</th><th>Selling Price</th></tr></thead>
                    <tbody>
                    <?php if ($customPrices === []): ?>
                        <tr><td colspan="3" class="text-slate-400">No per-user pricing overrides yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($customPrices as $row): ?>
                            <tr><td><?= e($row['service_name']); ?></td><td><?= e($row['network_code'] ?? 'default'); ?></td><td><?= e(money($row['selling_price'])); ?></td></tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="surface-card p-6">
            <h2 class="text-2xl font-black text-white">Recent Activity</h2>
            <div class="mt-4 space-y-3">
                <?php if ($activity === []): ?>
                    <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4 text-slate-400">No activity logs recorded for this user yet.</div>
                <?php else: ?>
                    <?php foreach ($activity as $row): ?>
                        <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4">
                            <p class="font-semibold text-white"><?= e($row['action']); ?></p>
                            <p class="mt-1 text-sm text-slate-300"><?= e($row['description']); ?></p>
                            <p class="mt-1 text-xs text-slate-500"><?= e($row['created_at']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <section class="surface-card p-6">
        <h2 class="text-2xl font-black text-white">Recent Transactions</h2>
        <div class="table-shell mt-4">
            <table>
                <thead><tr class="text-slate-400"><th>Reference</th><th>Service</th><th>Status</th><th>Amount</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($transactions as $row): ?>
                    <tr><td><?= e($row['reference']); ?></td><td><?= e($row['service_name']); ?></td><td><?= e($row['status']); ?></td><td><?= e(money($row['amount'])); ?></td><td><?= e($row['created_at']); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="surface-card p-6">
        <h2 class="text-2xl font-black text-white">Wallet History</h2>
        <div class="table-shell mt-4">
            <table>
                <thead><tr class="text-slate-400"><th>Reference</th><th>Type</th><th>Amount</th><th>After</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($walletRows as $row): ?>
                    <tr><td><?= e($row['reference']); ?></td><td><?= e($row['type']); ?></td><td><?= e(money($row['amount'])); ?></td><td><?= e(money($row['balance_after'])); ?></td><td><?= e($row['created_at']); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php render_footer(); ?>
