<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('users.view');
$ops = app(\GemData\Classes\AdminOpsService::class);
$pageKey = 'users';

$filterKeys = ['q', 'status', 'tier', 'api'];
$selectedViewId = max(0, (int) ($_GET['view_id'] ?? $_POST['view_id'] ?? 0));

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);
    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    $redirectQuery = query_except([]);

    if ($action === 'load_view') {
        $view = $ops->findSavedView((int) ($_POST['view_id'] ?? 0), (int) $admin['id'], $pageKey);
        if (!$view) {
            flash('success', 'Saved view was not found.');
            redirect(base_url('admin/users.php'));
        }
        $filters = json_decode_array($view['filters_json'] ?? '');
        redirect(base_url('admin/users.php?' . http_build_query(array_filter(array_merge($filters, ['view_id' => $view['id']]), static fn($value) => $value !== '' && $value !== null))));
    }

    if ($action === 'save_view') {
        $filters = [];
        foreach ($filterKeys as $key) {
            $filters[$key] = trim((string) ($_POST['filter_' . $key] ?? ''));
        }
        try {
            $viewId = $ops->saveView((int) $admin['id'], $pageKey, (string) ($_POST['view_name'] ?? ''), $filters, !empty($_POST['is_default']));
            flash('success', 'Saved view created successfully.');
            redirect(base_url('admin/users.php?' . http_build_query(array_filter(array_merge($filters, ['view_id' => $viewId]), static fn($value) => $value !== '' && $value !== null))));
        } catch (Throwable $throwable) {
            flash('success', 'Could not save view: ' . $throwable->getMessage());
            redirect(base_url('admin/users.php?' . http_build_query($redirectQuery)));
        }
    }

    if ($action === 'delete_view') {
        try {
            $ops->deleteView((int) ($_POST['view_id'] ?? 0), $admin, admin_can('roles.manage'));
            flash('success', 'Saved view deleted.');
        } catch (Throwable $throwable) {
            flash('success', 'Could not delete view: ' . $throwable->getMessage());
        }
        redirect(base_url('admin/users.php'));
    }

    if ($action === 'bulk_users') {
        require_permission('users.manage');
        $bulkAction = (string) ($_POST['bulk_action'] ?? '');
        $userIds = $_POST['user_ids'] ?? [];
        if ($bulkAction === 'deactivate' && !auth()->confirmAdminPassword((int) $admin['id'], $adminPassword)) {
            flash('success', 'Admin password confirmation failed. Bulk user action was not applied.');
            redirect(base_url('admin/users.php?' . http_build_query($redirectQuery)));
        }
        try {
            $result = $ops->applyBulkUserAction($admin, $bulkAction, is_array($userIds) ? $userIds : [], ['tier' => $_POST['bulk_tier'] ?? ''], admin_can('users.manage'));
            flash('success', 'Bulk user action applied to ' . $result['affected'] . ' record(s).');
        } catch (Throwable $throwable) {
            flash('success', 'Bulk action failed: ' . $throwable->getMessage());
        }
        redirect(base_url('admin/users.php?' . http_build_query($redirectQuery)));
    }
}

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'tier' => trim((string) ($_GET['tier'] ?? '')),
    'api' => trim((string) ($_GET['api'] ?? '')),
];

$hasExplicitFilters = false;
foreach ($filterKeys as $key) {
    if ($filters[$key] !== '') {
        $hasExplicitFilters = true;
        break;
    }
}

if ($selectedViewId > 0 && !$hasExplicitFilters) {
    $savedView = $ops->findSavedView($selectedViewId, (int) $admin['id'], $pageKey);
    if ($savedView) {
        $filters = array_merge($filters, json_decode_array($savedView['filters_json'] ?? ''));
    }
} elseif (!$hasExplicitFilters) {
    $defaultView = $ops->defaultSavedView((int) $admin['id'], $pageKey);
    if ($defaultView) {
        $selectedViewId = (int) $defaultView['id'];
        $filters = array_merge($filters, json_decode_array($defaultView['filters_json'] ?? ''));
    }
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$conditions = ['1=1'];
$params = [];
if ($filters['q'] !== '') {
    $conditions[] = '(u.full_name LIKE :q OR u.email LIKE :q OR u.phone LIKE :q)';
    $params['q'] = '%' . $filters['q'] . '%';
}
if ($filters['status'] !== '') {
    $conditions[] = 'u.status = :status';
    $params['status'] = $filters['status'];
}
if ($filters['tier'] !== '') {
    $conditions[] = 'u.tier = :tier';
    $params['tier'] = $filters['tier'];
}
if ($filters['api'] === 'enabled') {
    $conditions[] = 'u.is_api_user = 1';
}
if ($filters['api'] === 'disabled') {
    $conditions[] = 'u.is_api_user = 0';
}
$where = implode(' AND ', $conditions);

$countRow = db()->first(
    "SELECT COUNT(*) AS total
     FROM users u
     LEFT JOIN api_users au ON au.user_id = u.id
     WHERE {$where}",
    $params
);
$pagination = pagination_meta((int) ($countRow['total'] ?? 0), $page, $perPage);

$rows = db()->query(
    "SELECT u.*, w.balance, au.status AS api_status, ak.api_key, COALESCE(cl.total_commission, 0) AS total_commission
     FROM users u
     LEFT JOIN wallets w ON w.user_id = u.id
     LEFT JOIN api_users au ON au.user_id = u.id
     LEFT JOIN api_keys ak ON ak.api_user_id = au.id
     LEFT JOIN (
         SELECT user_id, SUM(commission_amount) AS total_commission
         FROM commission_logs
         GROUP BY user_id
     ) cl ON cl.user_id = u.id
     WHERE {$where}
     ORDER BY u.id DESC
     LIMIT {$pagination['offset']}, {$pagination['per_page']}",
    $params
);

$savedViews = $ops->savedViews((int) $admin['id'], $pageKey);
$paginationQuery = array_filter(array_merge($filters, ['view_id' => $selectedViewId ?: null]), static fn($value) => $value !== '' && $value !== null);

render_header('Users', 'admin');
?>
<div class="space-y-6">
    <section class="surface-card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="eyebrow">Operations</p>
                <h1 class="mt-2 text-3xl font-black text-white">User Management</h1>
                <p class="mt-2 text-slate-400">Search, filter, save common views, and manage users without loading the entire table into one page.</p>
            </div>
            <?php if ($message = flash('success')): ?><div class="notice notice-success max-w-xl"><?= e($message); ?></div><?php endif; ?>
        </div>
        <?php if ($secret = flash('generated_api_secret')): ?><div class="notice notice-success mt-4">Generated API secret: <span class="font-mono text-sm"><?= e($secret); ?></span></div><?php endif; ?>

        <div class="saved-view-bar mt-6">
            <form method="post" class="saved-view-load">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="load_view">
                <select name="view_id" class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3">
                    <option value="">Saved views</option>
                    <?php foreach ($savedViews as $view): ?>
                        <option value="<?= (int) $view['id']; ?>"<?= $selectedViewId === (int) $view['id'] ? ' selected' : ''; ?>><?= e($view['name']); ?><?= (int) $view['is_default'] === 1 ? ' (default)' : ''; ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="secondary-action" type="submit">Load view</button>
            </form>
            <form method="post" class="saved-view-save">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="save_view">
                <?php foreach ($filters as $key => $value): ?>
                    <input type="hidden" name="filter_<?= e($key); ?>" value="<?= e($value); ?>">
                <?php endforeach; ?>
                <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="view_name" placeholder="Save current filters as..." required>
                <label class="saved-view-toggle"><input type="checkbox" name="is_default" value="1"> Set default</label>
                <button class="primary-action" type="submit">Save view</button>
            </form>
            <?php if ($selectedViewId > 0): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="delete_view">
                    <input type="hidden" name="view_id" value="<?= (int) $selectedViewId; ?>">
                    <button class="secondary-action danger-inline" type="submit">Delete view</button>
                </form>
            <?php endif; ?>
        </div>

        <form method="get" class="admin-filter-bar mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <?php if ($selectedViewId > 0): ?><input type="hidden" name="view_id" value="<?= (int) $selectedViewId; ?>"><?php endif; ?>
            <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="q" placeholder="Search name, email, phone" value="<?= e($filters['q']); ?>">
            <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="status">
                <option value="">All statuses</option>
                <?php foreach (['active', 'inactive'] as $status): ?>
                    <option value="<?= e($status); ?>"<?= $filters['status'] === $status ? ' selected' : ''; ?>><?= e(ucfirst($status)); ?></option>
                <?php endforeach; ?>
            </select>
            <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="tier">
                <option value="">All tiers</option>
                <?php foreach (['USER', 'RESELLER', 'AGENT', 'API_RESELLER'] as $tier): ?>
                    <option value="<?= e($tier); ?>"<?= $filters['tier'] === $tier ? ' selected' : ''; ?>><?= e($tier); ?></option>
                <?php endforeach; ?>
            </select>
            <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="api">
                <option value="">All API states</option>
                <option value="enabled"<?= $filters['api'] === 'enabled' ? ' selected' : ''; ?>>API enabled</option>
                <option value="disabled"<?= $filters['api'] === 'disabled' ? ' selected' : ''; ?>>API disabled</option>
            </select>
            <div class="flex gap-3">
                <button class="primary-action flex-1" type="submit">Apply</button>
                <a class="secondary-action inline-flex items-center justify-center" href="<?= e(base_url('admin/users.php')); ?>">Reset</a>
            </div>
        </form>
    </section>

    <form method="post" id="bulk-users-form" class="space-y-4" data-bulk-form>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <input type="hidden" name="action" value="bulk_users">
    </form>
    <section class="bulk-action-bar" data-bulk-bar hidden>
        <div class="bulk-action-copy"><strong data-selected-count>0</strong> users selected</div>
        <div class="bulk-action-controls">
            <select form="bulk-users-form" name="bulk_action" class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2" data-bulk-action-select>
                <option value="">Choose bulk action</option>
                <option value="activate">Activate users</option>
                <option value="deactivate">Deactivate users</option>
                <option value="set_tier">Change tier</option>
            </select>
            <select form="bulk-users-form" name="bulk_tier" class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2" data-bulk-tier-select hidden>
                <?php foreach (['USER', 'RESELLER', 'AGENT', 'API_RESELLER'] as $tier): ?>
                    <option value="<?= e($tier); ?>"><?= e($tier); ?></option>
                <?php endforeach; ?>
            </select>
            <input form="bulk-users-form" class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2" name="admin_password" type="password" placeholder="Confirm admin password" data-bulk-password hidden>
            <button form="bulk-users-form" class="primary-action" type="submit">Apply to selected</button>
        </div>
    </section>

    <section class="surface-card p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="text-sm text-slate-400">
                    Showing page <?= (int) $pagination['page']; ?> of <?= (int) $pagination['total_pages']; ?> |
                    <?= (int) $pagination['total']; ?> users
                </div>
                <label class="select-page-toggle"><input type="checkbox" data-select-page data-bulk-target="bulk-users-form"> Select page</label>
            </div>
            <div class="table-shell mt-4">
                <table>
                    <thead>
                    <tr class="text-slate-400">
                        <th></th><th>User</th><th>Status</th><th>Tier</th><th>Wallet</th><th>API</th><th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="7" class="text-slate-400">No users matched the current filters.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="align-top pt-5"><input form="bulk-users-form" type="checkbox" name="user_ids[]" value="<?= (int) $row['id']; ?>" data-bulk-item></td>
                            <td>
                                <div class="font-semibold text-white"><?= e($row['full_name']); ?></div>
                                <div class="text-sm text-slate-400"><?= e($row['email']); ?></div>
                                <div class="text-xs text-slate-500"><?= e($row['phone']); ?></div>
                            </td>
                            <td><span class="status-chip status-<?= e($row['status']); ?>"><?= e(ucfirst($row['status'])); ?></span></td>
                            <td><span class="status-chip status-neutral"><?= e($row['tier']); ?></span></td>
                            <td>
                                <div class="font-semibold text-white"><?= e(money($row['balance'] ?? 0)); ?></div>
                                <div class="text-xs text-slate-400">Commission <?= e(money($row['total_commission'] ?? 0)); ?></div>
                            </td>
                            <td>
                                <?php if ((int) $row['is_api_user'] === 1): ?>
                                    <span class="status-chip status-success">Enabled</span>
                                    <div class="mt-2 font-mono text-xs text-slate-400"><?= e($row['api_key'] ?? 'pending key'); ?></div>
                                <?php else: ?>
                                    <span class="status-chip status-neutral">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td class="admin-actions-cell">
                                <div class="admin-user-action-links flex flex-wrap gap-2">
                                    <a class="secondary-action inline-flex items-center justify-center px-3 py-2 text-sm" href="<?= e(base_url('admin/user-detail.php?user_id=' . $row['id'])); ?>">Details</a>
                                    <a class="secondary-action inline-flex items-center justify-center px-3 py-2 text-sm" href="<?= e(base_url('admin/wallet.php?user_id=' . $row['id'])); ?>">Wallet</a>
                                    <?php if (admin_can('users.manage')): ?>
                                        <a class="secondary-action inline-flex items-center justify-center px-3 py-2 text-sm" href="<?= e(base_url('admin/user-detail.php?user_id=' . $row['id'] . '#manage')); ?>">Manage</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-6">
                <?php render_pagination($pagination, 'admin/users.php', $paginationQuery); ?>
            </div>
        </section>
</div>
<?php render_footer(); ?>
