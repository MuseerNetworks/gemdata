<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = (admin_can('users.manage') || admin_can('api.manage')) ? admin_user() : require_permission('api.manage');
if (!$admin) {
    redirect(base_url('admin/login.php'));
}

$db = db();
$apiCredentials = app(\GemData\Classes\ApiCredentialService::class);
$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);

    try {
        if ($action === 'activate') {
            $existing = $db->first('SELECT id FROM api_users WHERE user_id = :uid LIMIT 1', ['uid' => $userId]);
            if (!$existing) {
                $db->execute(
                    'INSERT INTO api_users (user_id, status, created_by_admin_id) VALUES (:uid, :s, :aid)',
                    ['uid' => $userId, 's' => 'active', 'aid' => $admin['id']]
                );
                $apiUserId = $db->lastInsertId();
            } else {
                $db->execute('UPDATE api_users SET status = "active" WHERE user_id = :uid', ['uid' => $userId]);
                $apiUserId = (int) $existing['id'];
            }
            $db->execute(
                'UPDATE users SET user_type = "api", tier = "API_RESELLER", is_api_user = 1 WHERE id = :uid',
                ['uid' => $userId]
            );
            $apiCredentials->ensureActiveKey($apiUserId);
            $success = 'API user activated. An active API key is ready.';
        } elseif ($action === 'deactivate') {
            $db->execute('UPDATE api_users SET status = "inactive" WHERE user_id = :uid', ['uid' => $userId]);
            $success = 'API user deactivated.';
        } elseif ($action === 'revoke_key') {
            $keyId = (int) ($_POST['key_id'] ?? 0);
            $db->execute('UPDATE api_keys SET status = "inactive" WHERE id = :id', ['id' => $keyId]);
            $success = 'API key revoked.';
        } elseif ($action === 'regenerate_key') {
            $apiUserId = (int) ($_POST['api_user_id'] ?? 0);
            $credential = $apiCredentials->generateForApiUser($apiUserId);
            app(\GemData\Classes\ActivityLogger::class)->log('admin', (int) $admin['id'], 'api_key_regenerated', 'Admin regenerated API key.', ['api_user_id' => $apiUserId]);
            $_SESSION['flash']['generated_api_secret'] = (string) $credential['secret'];
            $success = 'API key regenerated. Copy the one-time secret shown below.';
        } elseif ($action === 'update_limits') {
            $apiUserId = (int) ($_POST['api_user_id'] ?? 0);
            $db->execute(
                'UPDATE api_users SET rate_limit_per_minute = :rate_limit, monthly_limit = :monthly_limit, billing_status = :billing_status WHERE id = :id',
                [
                    'rate_limit' => max(1, (int) ($_POST['rate_limit_per_minute'] ?? 60)),
                    'monthly_limit' => max(0, (int) ($_POST['monthly_limit'] ?? 0)),
                    'billing_status' => in_array($_POST['billing_status'] ?? 'active', ['active', 'paused'], true) ? $_POST['billing_status'] : 'active',
                    'id' => $apiUserId,
                ]
            );
            app(\GemData\Classes\ActivityLogger::class)->log('admin', (int) $admin['id'], 'api_limits_updated', 'Admin updated API user limits.', ['api_user_id' => $apiUserId]);
            $success = 'API limits updated.';
        } elseif ($action === 'add_whitelist') {
            $db->execute(
                'INSERT INTO api_ip_whitelists (api_user_id, ip_address, status, notes, created_by_admin_id)
                 VALUES (:api_user_id, :ip_address, "active", :notes, :admin_id)
                 ON DUPLICATE KEY UPDATE status = "active", notes = VALUES(notes)',
                [
                    'api_user_id' => (int) ($_POST['api_user_id'] ?? 0),
                    'ip_address' => trim((string) ($_POST['ip_address'] ?? '')),
                    'notes' => trim((string) ($_POST['notes'] ?? '')),
                    'admin_id' => (int) $admin['id'],
                ]
            );
            $success = 'IP whitelist updated.';
        } elseif ($action === 'save_webhook') {
            $secretPreview = 'whsec_' . substr(bin2hex(random_bytes(10)), -8);
            $db->execute(
                'INSERT INTO api_webhook_configs (api_user_id, webhook_url, status, secret_preview, created_by_admin_id)
                 VALUES (:api_user_id, :webhook_url, :status, :secret_preview, :admin_id)',
                [
                    'api_user_id' => (int) ($_POST['api_user_id'] ?? 0),
                    'webhook_url' => trim((string) ($_POST['webhook_url'] ?? '')),
                    'status' => in_array($_POST['status'] ?? 'active', ['active', 'inactive'], true) ? $_POST['status'] : 'active',
                    'secret_preview' => $secretPreview,
                    'admin_id' => (int) $admin['id'],
                ]
            );
            $success = 'Webhook config saved.';
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

$filterStatus = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$where = ["u.user_type = 'api'"];
$params = [];

if ($filterStatus !== '') {
    $where[] = 'au.status = :status';
    $params['status'] = $filterStatus;
}
if ($search !== '') {
    $where[] = '(u.full_name LIKE :q OR u.email LIKE :q OR u.phone LIKE :q)';
    $params['q'] = '%' . $search . '%';
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$apiUsers = $db->safeQuery(
    "SELECT u.id, u.full_name, u.email, u.phone, u.user_type, u.created_at AS registered_at,
            au.id AS api_user_id, au.status AS api_status, au.rate_limit_per_minute, au.monthly_limit, au.billing_status, au.created_at AS activated_at,
            (SELECT COUNT(*) FROM api_keys ak WHERE ak.api_user_id = au.id AND ak.status = 'active') AS active_keys,
            (SELECT ak.api_key FROM api_keys ak WHERE ak.api_user_id = au.id AND ak.status = 'active' ORDER BY ak.id DESC LIMIT 1) AS active_api_key,
            (SELECT COUNT(*) FROM transactions t WHERE t.user_id = u.id AND t.channel = 'api') AS total_api_txns,
            (SELECT COALESCE(SUM(t2.amount),0) FROM transactions t2 WHERE t2.user_id = u.id AND t2.channel = 'api' AND t2.status = 'successful') AS api_volume,
            w.balance AS wallet_balance
       FROM users u
  LEFT JOIN api_users au ON au.user_id = u.id
  LEFT JOIN wallets w ON w.user_id = u.id
       {$whereSQL}
      ORDER BY u.created_at DESC",
    $params
);

$candidates = $db->safeQuery(
    "SELECT id, full_name, email FROM users WHERE user_type != 'api' ORDER BY full_name LIMIT 200"
);
$requestLogs = $db->tableExists('api_request_logs') ? $db->safeQuery(
    'SELECT arl.*, u.full_name
     FROM api_request_logs arl
     LEFT JOIN users u ON u.id = arl.user_id
     ORDER BY arl.id DESC LIMIT 25'
) : [];
$usageRows = $db->tableExists('api_usage_records') ? $db->safeQuery(
    'SELECT aur.*, u.full_name
     FROM api_usage_records aur
     INNER JOIN api_users au ON au.id = aur.api_user_id
     INNER JOIN users u ON u.id = au.user_id
     ORDER BY aur.usage_date DESC, aur.request_count DESC LIMIT 20'
) : [];
$webhookRows = $db->tableExists('api_webhook_configs') ? $db->safeQuery(
    'SELECT awc.*, u.full_name
     FROM api_webhook_configs awc
     INNER JOIN api_users au ON au.id = awc.api_user_id
     INNER JOIN users u ON u.id = au.user_id
     ORDER BY awc.id DESC LIMIT 20'
) : [];

render_header('API User Management', 'admin');
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Developer Accounts</p>
        <h1>API User Management</h1>
        <p>Activate, monitor, and manage B2B API users.</p>
    </div>
    <span class="badge bg-gem-blueLt text-gem-blue"><?= count($apiUsers); ?> API users</span>
</div>

<?php if ($error): ?><div class="notice notice-error mb-4"><?= e($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="notice notice-success mb-4"><?= e($success); ?></div><?php endif; ?>
<?php if ($secret = flash('generated_api_secret')): ?><div class="notice notice-success mb-4">New API secret: <span class="font-mono text-sm"><?= e($secret); ?></span>. It will not be shown again.</div><?php endif; ?>

<section class="surface-card p-5">
    <div class="dashboard-section-header dashboard-section-header-start">
        <div>
            <h2 class="surface-section-title">Activate API Access</h2>
            <p class="surface-section-copy">Promote an eligible user into API access without changing wallet or transaction history.</p>
        </div>
    </div>
    <form method="POST" class="mt-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]" data-loading-form>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <input type="hidden" name="action" value="activate">
        <select name="user_id" class="form-select" required>
            <option value="">Choose a user...</option>
            <?php foreach ($candidates as $c): ?>
                <option value="<?= (int) $c['id']; ?>"><?= e($c['full_name']); ?> - <?= e($c['email']); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary" type="submit" data-loading-label="Activating...">Activate API Access</button>
    </form>
    <div class="alert alert-info mt-4">
        This sets the account type to API and enables API key generation. API users keep GemData wholesale pricing controls.
    </div>
</section>

<section class="surface-card p-5">
    <form method="GET" class="grid gap-3 md:grid-cols-[minmax(0,1fr)_180px_auto_auto]">
        <input type="text" name="q" class="form-control" placeholder="Search name, email, phone" value="<?= e($search); ?>">
        <select name="status" class="form-select">
            <option value="">All Status</option>
            <option value="active"<?= $filterStatus === 'active' ? ' selected' : ''; ?>>Active</option>
            <option value="inactive"<?= $filterStatus === 'inactive' ? ' selected' : ''; ?>>Inactive</option>
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="<?= e(base_url('admin/api-users.php')); ?>" class="btn btn-outline-secondary">Clear</a>
    </form>

    <?php if ($apiUsers === []): ?>
        <div class="mt-5 rounded-2xl border border-gem-border bg-gem-gray p-8 text-center text-gem-muted">
            No API users yet. Activate one above.
        </div>
    <?php else: ?>
        <div class="table-responsive mt-5">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Status</th>
                        <th>Active Keys</th>
                        <th>Limits</th>
                        <th>Wallet</th>
                        <th>API Volume</th>
                        <th>Txns</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($apiUsers as $u): ?>
                    <?php $isActive = ($u['api_status'] ?? 'inactive') === 'active'; ?>
                    <tr>
                        <td>
                            <div class="font-bold text-gem-text"><?= e($u['full_name']); ?></div>
                            <div class="text-[12px] text-gem-muted"><?= e($u['email']); ?></div>
                            <div class="text-[12px] text-gem-muted"><?= e((string) $u['phone']); ?></div>
                        </td>
                        <td><span class="badge <?= $isActive ? 'bg-green-50 text-gem-green' : 'bg-slate-100 text-gem-muted'; ?>"><?= e($u['api_status'] ?? 'inactive'); ?></span></td>
                        <td>
                            <?php $maskedKey = !empty($u['active_api_key']) ? substr((string) $u['active_api_key'], 0, 10) . '...' . substr((string) $u['active_api_key'], -6) : 'No active key'; ?>
                            <div><?= (int) $u['active_keys']; ?></div>
                            <div class="font-mono text-[11px] text-gem-muted"><?= e($maskedKey); ?></div>
                        </td>
                        <td><div><?= (int) ($u['rate_limit_per_minute'] ?? 60); ?>/min</div><div class="text-[11px] text-gem-muted"><?= (int) ($u['monthly_limit'] ?? 0); ?> monthly</div></td>
                        <td><?= e(money((float) $u['wallet_balance'])); ?></td>
                        <td class="text-success"><?= e(money((float) $u['api_volume'])); ?></td>
                        <td><?= number_format((int) $u['total_api_txns']); ?></td>
                        <td>
                            <div class="flex flex-wrap gap-2">
                                <form method="POST" data-loading-form>
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id']; ?>">
                                    <input type="hidden" name="action" value="<?= $isActive ? 'deactivate' : 'activate'; ?>">
                                    <button class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success'; ?>" type="submit" data-loading-label="Saving..."<?= $isActive ? " onclick=\"return confirm('Deactivate this API user?')\"" : ''; ?>>
                                        <?= $isActive ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                <form method="POST" data-loading-form>
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="api_user_id" value="<?= (int) $u['api_user_id']; ?>">
                                    <input type="hidden" name="action" value="regenerate_key">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit" data-loading-label="Regenerating...">Regenerate Key</button>
                                </form>
                                <a href="<?= e(base_url('admin/user-detail.php?user_id=' . (int) $u['id'])); ?>" class="btn btn-sm btn-outline-secondary">View</a>
                            </div>
                            <details class="mt-3">
                                <summary class="text-[12px] font-bold text-gem-blue cursor-pointer">API controls</summary>
                                <div class="mt-3 grid gap-3">
                                    <form method="POST" class="grid gap-2">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="update_limits">
                                        <input type="hidden" name="api_user_id" value="<?= (int) $u['api_user_id']; ?>">
                                        <input class="form-control" name="rate_limit_per_minute" type="number" min="1" value="<?= (int) ($u['rate_limit_per_minute'] ?? 60); ?>" placeholder="Rate/min">
                                        <input class="form-control" name="monthly_limit" type="number" min="0" value="<?= (int) ($u['monthly_limit'] ?? 0); ?>" placeholder="Monthly limit">
                                        <select class="form-select" name="billing_status"><option value="active">Billing active</option><option value="paused"<?= ($u['billing_status'] ?? '') === 'paused' ? ' selected' : ''; ?>>Billing paused</option></select>
                                        <button class="btn btn-sm btn-primary" type="submit">Save Limits</button>
                                    </form>
                                    <form method="POST" class="grid gap-2">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="add_whitelist">
                                        <input type="hidden" name="api_user_id" value="<?= (int) $u['api_user_id']; ?>">
                                        <input class="form-control" name="ip_address" placeholder="IP whitelist">
                                        <input class="form-control" name="notes" placeholder="Notes">
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Add IP</button>
                                    </form>
                                    <form method="POST" class="grid gap-2">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="save_webhook">
                                        <input type="hidden" name="api_user_id" value="<?= (int) $u['api_user_id']; ?>">
                                        <input class="form-control" name="webhook_url" type="url" placeholder="Webhook URL">
                                        <select class="form-select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Save Webhook</button>
                                    </form>
                                </div>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<div class="grid gap-6 xl:grid-cols-3 mt-6">
    <section class="surface-card p-5 xl:col-span-2">
        <h2 class="surface-section-title">Recent API Requests</h2>
        <div class="table-responsive mt-4"><table class="table"><thead><tr><th>User</th><th>Endpoint</th><th>Status</th><th>IP</th><th>Time</th></tr></thead><tbody><?php foreach ($requestLogs as $row): ?><tr><td><?= e($row['full_name'] ?? 'Unknown'); ?></td><td class="font-mono text-[11px]"><?= e($row['method'] . ' ' . $row['endpoint']); ?></td><td><?= e($row['request_status']); ?></td><td><?= e($row['ip_address'] ?? ''); ?></td><td><?= e($row['created_at']); ?></td></tr><?php endforeach; ?><?php if ($requestLogs === []): ?><tr><td colspan="5" class="text-gem-muted">No API request logs yet.</td></tr><?php endif; ?></tbody></table></div>
    </section>
    <section class="surface-card p-5">
        <h2 class="surface-section-title">Usage Metering</h2>
        <div class="mt-4 space-y-3"><?php foreach ($usageRows as $row): ?><div class="rounded-xl border border-gem-border bg-gem-gray p-3"><div class="font-bold text-gem-text"><?= e($row['full_name']); ?></div><div class="text-[12px] text-gem-muted"><?= e($row['usage_date']); ?> · <?= (int) $row['request_count']; ?> requests · <?= e(money($row['volume_amount'])); ?></div></div><?php endforeach; ?><?php if ($usageRows === []): ?><p class="text-[13px] text-gem-muted">No usage records yet.</p><?php endif; ?></div>
    </section>
</div>
<section class="surface-card p-5 mt-6">
    <h2 class="surface-section-title">Webhook Configs</h2>
    <div class="table-responsive mt-4"><table class="table"><thead><tr><th>User</th><th>URL</th><th>Status</th><th>Secret Preview</th><th>Created</th></tr></thead><tbody><?php foreach ($webhookRows as $row): ?><tr><td><?= e($row['full_name']); ?></td><td class="font-mono text-[11px]"><?= e($row['webhook_url']); ?></td><td><?= e($row['status']); ?></td><td><?= e($row['secret_preview'] ?? 'masked'); ?></td><td><?= e($row['created_at']); ?></td></tr><?php endforeach; ?><?php if ($webhookRows === []): ?><tr><td colspan="5" class="text-gem-muted">No webhook configs yet.</td></tr><?php endif; ?></tbody></table></div>
</section>

<?php render_footer(); ?>
