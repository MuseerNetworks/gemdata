<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('users.view');
$userId = (int) ($_GET['user_id'] ?? 0);
$user = db()->first('SELECT u.*, w.balance FROM users u LEFT JOIN wallets w ON w.user_id = u.id WHERE u.id = :id', ['id' => $userId]);
if (!$user) {
    redirect(base_url('admin/users.php'));
}

$logger = app(\GemData\Classes\ActivityLogger::class);
$userSecurity = app(\GemData\Classes\UserSecurityService::class);
$mailService = app(\GemData\Classes\MailService::class);
$fundingAccounts = app(\GemData\Classes\FundingAccountProviderService::class);
if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    $manageUrl = base_url('admin/user-detail.php?user_id=' . $userId . '#manage');

    if ($action === 'toggle_api') {
        require_permission('users.manage');
        if (!auth()->confirmAdminPassword((int) $admin['id'], $adminPassword)) {
            flash('success', 'Admin password confirmation failed. API access was not changed.');
            redirect($manageUrl);
        }

        $apiUser = db()->first('SELECT * FROM api_users WHERE user_id = :user_id', ['user_id' => $userId]);
        if (!$apiUser) {
            db()->execute("INSERT INTO api_users (user_id, status, created_by_admin_id) VALUES (:user_id, 'active', :admin_id)", ['user_id' => $userId, 'admin_id' => $admin['id']]);
            db()->execute('UPDATE users SET is_api_user = 1 WHERE id = :id', ['id' => $userId]);
            flash('success', 'User upgraded to API user.');
        } else {
            $newStatus = $apiUser['status'] === 'active' ? 'inactive' : 'active';
            db()->execute('UPDATE api_users SET status = :status WHERE id = :id', ['status' => $newStatus, 'id' => $apiUser['id']]);
            db()->execute('UPDATE users SET is_api_user = :is_api_user WHERE id = :id', ['is_api_user' => $newStatus === 'active' ? 1 : 0, 'id' => $userId]);
            flash('success', 'API user status updated.');
        }
        $logger->log('admin', (int) $admin['id'], 'user_api_status_changed', 'Admin updated API access.', ['user_id' => $userId]);
        redirect($manageUrl);
    }

    if ($action === 'generate_key') {
        require_permission('users.manage');
        if (!auth()->confirmAdminPassword((int) $admin['id'], $adminPassword)) {
            flash('success', 'Admin password confirmation failed. API credentials were not rotated.');
            redirect($manageUrl);
        }

        $apiUser = db()->first('SELECT * FROM api_users WHERE user_id = :user_id', ['user_id' => $userId]);
        if ($apiUser) {
            $apiKey = 'gk_' . bin2hex(random_bytes(12));
            $secret = 'gs_' . bin2hex(random_bytes(24));
            $record = db()->first('SELECT * FROM api_keys WHERE api_user_id = :api_user_id', ['api_user_id' => $apiUser['id']]);
            if ($record) {
                db()->execute("UPDATE api_keys SET api_key = :api_key, secret_hash = :secret_hash, status = 'active' WHERE id = :id", [
                    'api_key' => $apiKey,
                    'secret_hash' => password_hash($secret, PASSWORD_DEFAULT),
                    'id' => $record['id'],
                ]);
            } else {
                db()->execute("INSERT INTO api_keys (api_user_id, api_key, secret_hash, status) VALUES (:api_user_id, :api_key, :secret_hash, 'active')", [
                    'api_user_id' => $apiUser['id'],
                    'api_key' => $apiKey,
                    'secret_hash' => password_hash($secret, PASSWORD_DEFAULT),
                ]);
            }
            $_SESSION['flash']['generated_api_secret'] = $secret;
            flash('success', 'API credentials generated successfully.');
            $logger->log('admin', (int) $admin['id'], 'user_api_key_generated', 'Admin generated API credentials.', ['user_id' => $userId]);
        }
        redirect($manageUrl);
    }

    if ($action === 'toggle_status') {
        require_permission('users.manage');
        $newStatus = ($_POST['status'] ?? 'inactive') === 'active' ? 'active' : 'inactive';
        if ($newStatus === 'inactive' && !auth()->confirmAdminPassword((int) $admin['id'], $adminPassword)) {
            flash('success', 'Admin password confirmation failed. User status was not changed.');
            redirect($manageUrl);
        }
        db()->execute('UPDATE users SET status = :status WHERE id = :id', ['status' => $newStatus, 'id' => $userId]);
        $logger->log('admin', (int) $admin['id'], 'user_status_changed', 'Admin changed user status.', ['user_id' => $userId, 'status' => $newStatus]);
        flash('success', 'User status updated.');
        redirect($manageUrl);
    }

    if ($action === 'set_tier') {
        require_permission('users.manage');
        $tier = strtoupper((string) ($_POST['tier'] ?? 'USER'));
        if (in_array($tier, ['USER', 'RESELLER', 'AGENT', 'API_RESELLER'], true)) {
            db()->execute('UPDATE users SET tier = :tier WHERE id = :id', ['tier' => $tier, 'id' => $userId]);
            $logger->log('admin', (int) $admin['id'], 'user_tier_changed', 'Admin changed user tier.', ['user_id' => $userId, 'tier' => $tier]);
            flash('success', 'User tier updated.');
        }
        redirect($manageUrl);
    }

    if ($action === 'reset_password') {
        require_permission('users.manage');
        if (!auth()->confirmAdminPassword((int) $admin['id'], $adminPassword)) {
            flash('success', 'Admin password confirmation failed. Reset link was not created.');
            redirect($manageUrl);
        }
        $reset = $userSecurity->createPasswordReset($userId, (int) $admin['id']);
        $resetUrl = absolute_url('user/reset-password.php?' . http_build_query([
            'reset' => (int) $reset['id'],
            'token' => (string) $reset['token'],
        ]));
        $sent = $mailService->sendPasswordReset((string) $reset['email'], $resetUrl, [
            'source' => 'admin',
            'reset_id' => (int) $reset['id'],
            'admin_id' => (int) $admin['id'],
            'user_id' => $userId,
        ]);
        flash('success', $sent
            ? 'Password reset link sent through the configured delivery channel.'
            : 'Password reset token created, but email delivery failed. Check server logs.'
        );
        redirect($manageUrl);
    }

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

        redirect($manageUrl);
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
    <?php if ($secret = flash('generated_api_secret')): ?><div class="notice notice-success">Generated API secret: <span class="font-mono text-sm"><?= e($secret); ?></span></div><?php endif; ?>

    <?php if (admin_can('users.manage')): ?>
        <section id="manage" class="surface-card p-6 admin-manage-section">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="eyebrow">Management</p>
                    <h2 class="mt-2 text-2xl font-black text-white">Manage User</h2>
                </div>
                <a class="secondary-action inline-flex items-center justify-center px-3 py-2 text-sm" href="<?= e(base_url('admin/users.php')); ?>">Back to Users</a>
            </div>

            <div class="admin-manage-grid mt-5">
                <div class="admin-manage-card">
                    <h3>Access</h3>
                    <form method="post" class="admin-action-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="status" value="<?= $user['status'] === 'active' ? 'inactive' : 'active'; ?>">
                        <?php if ($user['status'] === 'active'): ?>
                            <input class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm w-full" name="admin_password" type="password" placeholder="Confirm admin password" required>
                        <?php endif; ?>
                        <button class="action-button <?= $user['status'] === 'active' ? 'action-danger' : 'action-soft'; ?>" type="submit"><?= $user['status'] === 'active' ? 'Deactivate User' : 'Activate User'; ?></button>
                    </form>
                    <form method="post" class="admin-action-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="set_tier">
                        <select class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm w-full" name="tier">
                            <?php foreach (['USER', 'RESELLER', 'AGENT', 'API_RESELLER'] as $tier): ?>
                                <option value="<?= e($tier); ?>"<?= $user['tier'] === $tier ? ' selected' : ''; ?>><?= e($tier); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="action-button action-soft" type="submit">Save Tier</button>
                    </form>
                </div>

                <div class="admin-manage-card">
                    <h3>API Access</h3>
                    <form method="post" class="admin-action-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="toggle_api">
                        <input class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm w-full" name="admin_password" type="password" placeholder="Confirm admin password" required>
                        <button class="action-button action-soft" type="submit"><?= (int) $user['is_api_user'] === 1 ? 'Disable API Access' : 'Enable API Access'; ?></button>
                    </form>
                    <form method="post" class="admin-action-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="generate_key">
                        <input class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm w-full" name="admin_password" type="password" placeholder="Confirm admin password" required>
                        <button class="action-button action-soft" type="submit">Rotate API Credentials</button>
                    </form>
                </div>

                <div class="admin-manage-card">
                    <h3>Security</h3>
                    <form method="post" class="admin-action-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="reset_password">
                        <input class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm w-full" name="admin_password" type="password" placeholder="Confirm admin password" required>
                        <button class="action-button action-warning" type="submit">Create Reset Link</button>
                    </form>
                    <p class="text-xs text-slate-400">This issues a time-limited reset link instead of exposing a plaintext password.</p>
                </div>

                <div class="admin-manage-card">
                    <h3>Funding Account</h3>
                    <p class="text-xs text-slate-400">Active provider: <?= e(ucfirst($activeFundingProvider)); ?>. Current status: <?= e($fundingAssigned ? 'assigned' : $fundingStatus); ?>.</p>
                    <form method="post" class="admin-action-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="generate_funding_account">
                        <button class="action-button action-soft" type="submit">Retrieve/Generate Funding Account</button>
                    </form>
                    <form method="post" class="admin-action-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="generate_katpay_account">
                        <button class="action-button action-soft" type="submit">Retry KatPay</button>
                    </form>
                </div>
            </div>
        </section>
    <?php endif; ?>

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
