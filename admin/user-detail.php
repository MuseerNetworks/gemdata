<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_permission('users.view');
$userId = (int) ($_GET['user_id'] ?? 0);
$user = db()->first('SELECT u.*, w.balance FROM users u LEFT JOIN wallets w ON w.user_id = u.id WHERE u.id = :id', ['id' => $userId]);
if (!$user) {
    redirect(base_url('admin/users.php'));
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
