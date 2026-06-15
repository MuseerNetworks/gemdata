<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('transactions.view');
$transactionService = app(\GemData\Classes\TransactionService::class);
$ops = app(\GemData\Classes\AdminOpsService::class);
$pageKey = 'transactions';
$filterKeys = ['q', 'status', 'channel', 'service', 'provider_code', 'tier', 'failure_code', 'attempt_status', 'min_response_ms', 'date_from', 'date_to'];
$selectedViewId = max(0, (int) ($_GET['view_id'] ?? $_POST['view_id'] ?? 0));
$isSuperAdmin = (($admin['role_slug'] ?? '') === 'super_admin');

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $transactionId = (int) ($_POST['transaction_id'] ?? 0);
    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    $redirectQuery = query_except([]);

    if ($action === 'load_view') {
        $view = $ops->findSavedView((int) ($_POST['view_id'] ?? 0), (int) $admin['id'], $pageKey);
        if (!$view) {
            flash('success', 'Saved view was not found.');
            redirect(base_url('admin/transactions.php'));
        }
        $filters = json_decode_array($view['filters_json'] ?? '');
        redirect(base_url('admin/transactions.php?' . http_build_query(array_filter(array_merge($filters, ['view_id' => $view['id']]), static fn($value) => $value !== '' && $value !== null))));
    }

    if ($action === 'save_view') {
        $filters = [];
        foreach ($filterKeys as $key) {
            $filters[$key] = trim((string) ($_POST['filter_' . $key] ?? ''));
        }
        try {
            $viewId = $ops->saveView((int) $admin['id'], $pageKey, (string) ($_POST['view_name'] ?? ''), $filters, !empty($_POST['is_default']));
            flash('success', 'Saved view created successfully.');
            redirect(base_url('admin/transactions.php?' . http_build_query(array_filter(array_merge($filters, ['view_id' => $viewId]), static fn($value) => $value !== '' && $value !== null))));
        } catch (Throwable $throwable) {
            flash('success', 'Could not save view: ' . $throwable->getMessage());
            redirect(base_url('admin/transactions.php?' . http_build_query($redirectQuery)));
        }
    }

    if ($action === 'delete_view') {
        try {
            $ops->deleteView((int) ($_POST['view_id'] ?? 0), $admin, admin_can('roles.manage'));
            flash('success', 'Saved view deleted.');
        } catch (Throwable $throwable) {
            flash('success', 'Could not delete view: ' . $throwable->getMessage());
        }
        redirect(base_url('admin/transactions.php'));
    }

    if ($action === 'bulk_transactions') {
        require_permission('transactions.manage');
        try {
            $result = $ops->applyBulkTransactionAction($admin, (string) ($_POST['bulk_action'] ?? ''), is_array($_POST['transaction_ids'] ?? null) ? $_POST['transaction_ids'] : [], admin_can('transactions.manage'));
            $message = 'Bulk transaction review applied to ' . $result['affected'] . ' record(s).';
            if ($result['skipped'] > 0) {
                $message .= ' ' . $result['skipped'] . ' already acknowledged record(s) were skipped.';
            }
            flash('success', $message);
        } catch (Throwable $throwable) {
            flash('success', 'Bulk transaction action failed: ' . $throwable->getMessage());
        }
        redirect(base_url('admin/transactions.php?' . http_build_query($redirectQuery)));
    }

    if (!auth()->confirmAdminPassword((int) $admin['id'], $adminPassword)) {
        flash('success', 'Admin password confirmation failed. No transaction action was applied.');
        redirect(base_url('admin/transactions.php?' . http_build_query($redirectQuery)));
    }

    if ($action === 'retry') {
        require_permission('transactions.manage');
        try {
            $result = $transactionService->retryTransaction($transactionId, (int) $admin['id']);
            flash('success', 'Transaction re-queued. Result: ' . $result['status']);
        } catch (Throwable $throwable) {
            flash('success', 'Retry failed: ' . $throwable->getMessage());
        }
        redirect(base_url('admin/transactions.php?' . http_build_query($redirectQuery)));
    }

    if ($action === 'override') {
        require_permission('transactions.manage');
        try {
            $transactionService->overrideStatus($transactionId, (string) $_POST['status'], trim((string) $_POST['reason']), (int) $admin['id']);
            flash('success', 'Transaction cancellation completed successfully.');
        } catch (Throwable $throwable) {
            flash('success', 'Cancellation failed: ' . $throwable->getMessage());
        }
        redirect(base_url('admin/transactions.php?' . http_build_query($redirectQuery)));
    }

    if ($action === 'manual_refund') {
        require_permission('transactions.manage');
        try {
            $transactionService->manualRefund($transactionId, trim((string) $_POST['reason']), (int) $admin['id']);
            flash('success', 'Manual refund completed successfully.');
        } catch (Throwable $throwable) {
            flash('success', 'Manual refund failed: ' . $throwable->getMessage());
        }
        redirect(base_url('admin/transactions.php?' . http_build_query($redirectQuery)));
    }

    if ($action === 'force_success') {
        require_permission('transactions.manage');
        if (!$isSuperAdmin) {
            flash('success', 'Force success is restricted to Super Admin accounts.');
            redirect(base_url('admin/transactions.php?' . http_build_query($redirectQuery)));
        }
        try {
            $transactionService->forceSuccess($transactionId, trim((string) $_POST['reason']), (int) $admin['id']);
            flash('success', 'Transaction force-marked successful. Wallet balance was not changed.');
        } catch (Throwable $throwable) {
            flash('success', 'Force success failed: ' . $throwable->getMessage());
        }
        redirect(base_url('admin/transactions.php?' . http_build_query($redirectQuery)));
    }
}

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'channel' => trim((string) ($_GET['channel'] ?? '')),
    'service' => trim((string) ($_GET['service'] ?? '')),
    'provider_code' => trim((string) ($_GET['provider_code'] ?? '')),
    'tier' => trim((string) ($_GET['tier'] ?? '')),
    'failure_code' => trim((string) ($_GET['failure_code'] ?? '')),
    'attempt_status' => trim((string) ($_GET['attempt_status'] ?? '')),
    'min_response_ms' => trim((string) ($_GET['min_response_ms'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
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
    $conditions[] = '(u.full_name LIKE :q OR t.reference LIKE :q OR t.recipient LIKE :q OR s.name LIKE :q)';
    $params['q'] = '%' . $filters['q'] . '%';
}
foreach ($filters as $key => $value) {
    if ($value === '' || $key === 'q') {
        continue;
    }
    if (in_array($key, ['status', 'channel', 'provider_code', 'failure_code'], true)) {
        $conditions[] = "t.{$key} = :{$key}";
        $params[$key] = $value;
    } elseif ($key === 'attempt_status' && db()->tableExists('provider_transaction_attempts')) {
        $conditions[] = 'EXISTS (SELECT 1 FROM provider_transaction_attempts pta WHERE pta.transaction_id = t.id AND pta.status = :attempt_status)';
        $params['attempt_status'] = $value;
    } elseif ($key === 'min_response_ms' && db()->tableExists('provider_transaction_attempts')) {
        $conditions[] = 'EXISTS (SELECT 1 FROM provider_transaction_attempts pta WHERE pta.transaction_id = t.id AND pta.response_time_ms >= :min_response_ms)';
        $params['min_response_ms'] = max(0, (int) $value);
    } elseif ($key === 'service') {
        $conditions[] = 's.slug = :service';
        $params['service'] = $value;
    } elseif ($key === 'tier') {
        $conditions[] = 'u.tier = :tier';
        $params['tier'] = $value;
    } elseif ($key === 'date_from') {
        $conditions[] = 'DATE(t.created_at) >= :date_from';
        $params['date_from'] = $value;
    } elseif ($key === 'date_to') {
        $conditions[] = 'DATE(t.created_at) <= :date_to';
        $params['date_to'] = $value;
    }
}
$where = implode(' AND ', $conditions);

$countRow = db()->first(
    "SELECT COUNT(*) AS total
     FROM transactions t
     INNER JOIN users u ON u.id = t.user_id
     INNER JOIN services s ON s.id = t.service_id
     WHERE {$where}",
    $params
);
$pagination = pagination_meta((int) ($countRow['total'] ?? 0), $page, $perPage);

$baseQuery = "SELECT t.*, u.full_name, u.tier, u.user_type, s.name AS service_name, s.slug AS service_slug
              FROM transactions t
              INNER JOIN users u ON u.id = t.user_id
              INNER JOIN services s ON s.id = t.service_id
              WHERE {$where}
              ORDER BY t.id DESC";

$rows = db()->query($baseQuery . " LIMIT {$pagination['offset']}, {$pagination['per_page']}", $params);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportRows = db()->query($baseQuery, $params);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=transactions-export.csv');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['User', 'Tier', 'Reference', 'Service', 'Provider', 'Channel', 'Status', 'Amount', 'Profit', 'Created At']);
    foreach ($exportRows as $row) {
        fputcsv($out, [$row['full_name'], $row['tier'], $row['reference'], $row['service_name'], $row['provider_code'], $row['channel'], $row['status'], $row['amount'], $row['profit_amount'], $row['created_at']]);
    }
    fclose($out);
    exit;
}

$eventsByTransaction = [];
$attemptsByTransaction = [];
$providerLogsByTransaction = [];
if ($rows !== []) {
    $ids = implode(',', array_map(static fn(array $row): string => (string) (int) $row['id'], $rows));
    foreach (db()->query("SELECT * FROM transaction_events WHERE transaction_id IN ({$ids}) ORDER BY id DESC") as $event) {
        $eventsByTransaction[(int) $event['transaction_id']][] = $event;
    }
    if (db()->tableExists('provider_transaction_attempts')) {
        foreach (db()->query("SELECT * FROM provider_transaction_attempts WHERE transaction_id IN ({$ids}) ORDER BY transaction_id DESC, attempt_number ASC, id ASC") as $attempt) {
            $attemptsByTransaction[(int) $attempt['transaction_id']][] = $attempt;
        }
    }
    if (db()->tableExists('provider_api_logs')) {
        foreach (db()->query("SELECT * FROM provider_api_logs WHERE transaction_id IN ({$ids}) ORDER BY transaction_id DESC, id DESC") as $log) {
            $providerLogsByTransaction[(int) $log['transaction_id']][] = $log;
        }
    }
}

$services = db()->query('SELECT slug, name FROM services ORDER BY name');
$providers = db()->query('SELECT code, name FROM provider_accounts ORDER BY priority_order, name');
$savedViews = $ops->savedViews((int) $admin['id'], $pageKey);
$paginationQuery = array_filter(array_merge($filters, ['view_id' => $selectedViewId ?: null]), static fn($value) => $value !== '' && $value !== null);
$hasVisiblePendingTransactions = false;
foreach ($rows as $row) {
    if (($row['status'] ?? '') === 'pending') {
        $hasVisiblePendingTransactions = true;
        break;
    }
}

render_header('Transactions', 'admin');
?>
<div class="space-y-6">
    <?php if ($message = flash('success')): ?><div class="notice notice-success"><?= e($message); ?></div><?php endif; ?>

    <section class="surface-card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="eyebrow">Operations</p>
                <h1 class="mt-2 text-3xl font-black text-white">Transactions</h1>
                <p class="mt-2 text-slate-400">Review live flows, save common filter views, and manage pending transactions that finalize after queued processing.</p>
            </div>
            <a class="secondary-action inline-flex items-center justify-center" href="<?= e(base_url('admin/transactions.php?' . http_build_query(array_filter(array_merge($filters, ['view_id' => $selectedViewId ?: null, 'export' => 'csv']), static fn($value) => $value !== '' && $value !== null)))); ?>">Export CSV</a>
        </div>

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
            <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="q" placeholder="Search ref, user, recipient, service" value="<?= e($filters['q']); ?>">
            <div class="grid grid-cols-2 gap-3">
                <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="date_from" type="date" value="<?= e($filters['date_from']); ?>">
                <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="date_to" type="date" value="<?= e($filters['date_to']); ?>">
            </div>
            <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="service">
                <option value="">All services</option>
                <?php foreach ($services as $service): ?>
                    <option value="<?= e($service['slug']); ?>"<?= $filters['service'] === $service['slug'] ? ' selected' : ''; ?>><?= e($service['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="grid grid-cols-2 gap-3">
                <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="status"><option value="">All statuses</option><?php foreach (['pending','processing','successful','failed','refunded','reversed','disputed'] as $status): ?><option value="<?= e($status); ?>"<?= $filters['status'] === $status ? ' selected' : ''; ?>><?= e(ucfirst($status)); ?></option><?php endforeach; ?></select>
                <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="channel"><option value="">All channels</option><?php foreach (['web','api'] as $channel): ?><option value="<?= e($channel); ?>"<?= $filters['channel'] === $channel ? ' selected' : ''; ?>><?= e(strtoupper($channel)); ?></option><?php endforeach; ?></select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="tier"><option value="">All tiers</option><?php foreach (['USER','RESELLER','SMART','API_RESELLER'] as $tier): ?><option value="<?= e($tier); ?>"<?= $filters['tier'] === $tier ? ' selected' : ''; ?>><?= e($tier); ?></option><?php endforeach; ?></select>
                <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="provider_code">
                    <option value="">All providers</option>
                    <?php foreach ($providers as $provider): ?>
                        <option value="<?= e($provider['code']); ?>"<?= $filters['provider_code'] === $provider['code'] ? ' selected' : ''; ?>><?= e($provider['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-3 gap-3 xl:col-span-3">
                <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="failure_code" placeholder="Failure code" value="<?= e($filters['failure_code']); ?>">
                <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="attempt_status">
                    <option value="">Attempt status</option>
                    <?php foreach (['pending','processing','successful','failed','skipped'] as $attemptStatus): ?>
                        <option value="<?= e($attemptStatus); ?>"<?= $filters['attempt_status'] === $attemptStatus ? ' selected' : ''; ?>><?= e(ucfirst($attemptStatus)); ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="min_response_ms" type="number" min="0" step="1" placeholder="Min response ms" value="<?= e($filters['min_response_ms']); ?>">
            </div>
            <div class="flex gap-3 xl:col-span-2">
                <button class="primary-action" type="submit">Apply filters</button>
                <a class="secondary-action inline-flex items-center justify-center" href="<?= e(base_url('admin/transactions.php')); ?>">Reset</a>
            </div>
        </form>
    </section>

    <form method="post" id="bulk-transactions-form" class="space-y-4" data-bulk-form>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <input type="hidden" name="action" value="bulk_transactions">
    </form>
    <section class="bulk-action-bar" data-bulk-bar hidden>
        <div class="bulk-action-copy"><strong data-selected-count>0</strong> transactions selected</div>
        <div class="bulk-action-controls">
            <select form="bulk-transactions-form" name="bulk_action" class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2">
                <option value="acknowledge">Mark as reviewed</option>
            </select>
            <button form="bulk-transactions-form" class="primary-action" type="submit">Apply to selected</button>
        </div>
    </section>

    <section class="surface-card p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="text-sm text-slate-400">
                    Showing page <?= (int) $pagination['page']; ?> of <?= (int) $pagination['total_pages']; ?> |
                    <?= (int) $pagination['total']; ?> transactions
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <?php if ($hasVisiblePendingTransactions): ?>
                        <span class="inline-flex items-center gap-2 rounded-full border border-amber-400/25 bg-amber-500/10 px-3 py-1.5 text-xs font-bold text-amber-200" data-pending-auto-refresh>
                            <span class="h-2 w-2 rounded-full bg-amber-300"></span>
                            Auto-refreshing pending rows
                        </span>
                    <?php endif; ?>
                    <label class="select-page-toggle"><input type="checkbox" data-select-page data-bulk-target="bulk-transactions-form"> Select page</label>
                </div>
            </div>
            <div class="table-shell mt-4">
                <table>
                    <thead>
                        <tr class="text-slate-400">
                            <th></th><th>User</th><th>Reference</th><th>Service</th><th>Provider</th><th>Status</th><th>Amount</th><th>Profit</th><th>Commission</th><th>Review</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rows === []): ?>
                            <tr><td colspan="10" class="text-slate-400">No transactions matched the current filters.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $rowEvents = $eventsByTransaction[(int) $row['id']] ?? [];
                            $hasRefundEvent = false;
                            foreach ($rowEvents as $event) {
                                if (($event['event_type'] ?? '') === 'transaction_refunded') {
                                    $hasRefundEvent = true;
                                    break;
                                }
                            }
                            $canRetry = admin_can('transactions.manage') && $row['status'] === 'failed' && !$hasRefundEvent;
                            $canManualRefund = admin_can('transactions.manage') && in_array($row['status'], ['pending', 'failed'], true) && !$hasRefundEvent;
                            $canForceSuccess = $isSuperAdmin && admin_can('transactions.manage') && in_array($row['status'], ['pending', 'failed'], true) && !$hasRefundEvent;
                            ?>
                            <tr>
                                <td class="align-top pt-5"><input form="bulk-transactions-form" type="checkbox" name="transaction_ids[]" value="<?= (int) $row['id']; ?>" data-bulk-item></td>
                                <td>
                                    <div class="font-semibold text-white"><?= e($row['full_name']); ?></div>
                                    <div class="text-xs text-slate-400"><?= e($row['tier']); ?></div>
                                    <?php
                                    $utBadge = match($row['user_type'] ?? 'smart') {
                                        'reseller' => 'text-yellow-400',
                                        'api'      => 'text-blue-400',
                                        default    => 'text-slate-500',
                                    };
                                    ?>
                                    <div class="text-xs <?= $utBadge ?>"><?= e(strtoupper((string)($row['user_type'] ?? 'smart'))); ?></div>
                                </td>
                                <td class="font-mono text-xs">
                                    <div class="text-white"><?= e($row['reference']); ?></div>
                                    <div class="text-slate-500"><?= e($row['created_at']); ?></div>
                                </td>
                                <td>
                                    <div class="font-semibold text-white"><?= e($row['service_name']); ?></div>
                                    <div class="text-xs text-slate-400"><?= e(strtoupper((string) $row['channel'])); ?> | <?= e($row['recipient'] ?? '-'); ?></div>
                                </td>
                                <td><?= e($row['provider_code'] ?? 'unassigned'); ?></td>
                                <td><span class="status-chip status-<?= e($row['status']); ?>"><?= e(ucfirst($row['status'])); ?></span></td>
                                <td><?= e(money($row['selling_price'] > 0 ? $row['selling_price'] : $row['amount'])); ?></td>
                                <td><?= e(money($row['profit_amount'])); ?></td>
                                <td>
                                    <?php if ((float)($row['commission_amount'] ?? 0) > 0): ?>
                                        <span class="text-green-400 font-semibold"><?= e(money($row['commission_amount'])); ?></span>
                                        <div class="text-xs text-slate-500"><?= e($row['pricing_source'] ?? ''); ?></div>
                                    <?php else: ?>
                                        <span class="text-slate-600">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="w-[26rem]">
                                    <details class="admin-inline-drawer">
                                        <summary>Review actions</summary>
                                        <div class="admin-drawer-grid">
                                            <div class="admin-action-group">
                                                <h3>Recovery</h3>
                                                <?php if (admin_can('transactions.manage')): ?>
                                                    <form method="post" class="space-y-2">
                                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                        <input type="hidden" name="transaction_id" value="<?= (int) $row['id']; ?>">
                                                        <input type="hidden" name="action" value="retry">
                                                        <input class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm" name="admin_password" type="password" placeholder="Confirm admin password" required<?= !$canRetry ? ' disabled' : ''; ?>>
                                                        <button class="action-button action-soft" type="submit"<?= !$canRetry ? ' disabled' : ''; ?>>Queue failed retry</button>
                                                        <?php if ($hasRefundEvent): ?><p class="text-xs text-amber-300">Refunded transactions cannot be retried.</p><?php endif; ?>
                                                    </form>
                                                    <form method="post" class="space-y-2">
                                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                        <input type="hidden" name="transaction_id" value="<?= (int) $row['id']; ?>">
                                                        <input type="hidden" name="action" value="override">
                                                        <input type="hidden" name="status" value="failed">
                                                        <input class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm" name="reason" placeholder="Cancellation reason" required<?= $row['status'] !== 'pending' ? ' disabled' : ''; ?>>
                                                        <input class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm" name="admin_password" type="password" placeholder="Confirm admin password" required<?= $row['status'] !== 'pending' ? ' disabled' : ''; ?>>
                                                        <button class="action-button action-warning" type="submit"<?= $row['status'] !== 'pending' ? ' disabled' : ''; ?>>Cancel and refund</button>
                                                    </form>
                                                    <form method="post" class="space-y-2">
                                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                        <input type="hidden" name="transaction_id" value="<?= (int) $row['id']; ?>">
                                                        <input type="hidden" name="action" value="manual_refund">
                                                        <input class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm" name="reason" placeholder="Refund reason" required<?= !$canManualRefund ? ' disabled' : ''; ?>>
                                                        <input class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm" name="admin_password" type="password" placeholder="Confirm admin password" required<?= !$canManualRefund ? ' disabled' : ''; ?>>
                                                        <button class="action-button action-warning" type="submit"<?= !$canManualRefund ? ' disabled' : ''; ?>>Manual refund</button>
                                                    </form>
                                                    <?php if ($isSuperAdmin): ?>
                                                        <form method="post" class="space-y-2 rounded-xl border border-red-400/30 bg-red-500/10 p-3">
                                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                            <input type="hidden" name="transaction_id" value="<?= (int) $row['id']; ?>">
                                                            <input type="hidden" name="action" value="force_success">
                                                            <p class="text-xs text-red-200">Risk warning: force success changes only transaction status. It does not credit, debit, or refund wallet balance.</p>
                                                            <input class="rounded-lg border border-red-300/30 bg-slate-950 px-3 py-2 text-sm" name="reason" placeholder="Force-success reason" required<?= !$canForceSuccess ? ' disabled' : ''; ?>>
                                                            <input class="rounded-lg border border-red-300/30 bg-slate-950 px-3 py-2 text-sm" name="admin_password" type="password" placeholder="Confirm Super Admin password" required<?= !$canForceSuccess ? ' disabled' : ''; ?>>
                                                            <button class="action-button action-danger" type="submit"<?= !$canForceSuccess ? ' disabled' : ''; ?>>Force success</button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="admin-action-group">
                                                <h3>Provider attempts</h3>
                                                <div class="space-y-2">
                                                    <?php foreach ($attemptsByTransaction[(int) $row['id']] ?? [] as $attempt): ?>
                                                        <div class="rounded-lg border border-white/10 bg-slate-900/40 p-3 text-sm">
                                                            <div class="flex items-center justify-between gap-2">
                                                                <strong class="text-white">Attempt #<?= (int) $attempt['attempt_number']; ?> | <?= e($attempt['provider_code'] ?? 'provider'); ?></strong>
                                                                <span class="status-chip status-<?= e($attempt['status']); ?>"><?= e(ucfirst((string) $attempt['status'])); ?></span>
                                                            </div>
                                                            <div class="mt-2 grid gap-1 text-xs text-slate-300">
                                                                <span>Routing: <?= e($attempt['routing_mode'] ?? '-'); ?></span>
                                                                <span>Response: <?= $attempt['response_time_ms'] !== null ? e((string) $attempt['response_time_ms']) . 'ms' : '-'; ?></span>
                                                                <span>Provider ref: <?= e($attempt['provider_reference'] ?? '-'); ?></span>
                                                                <?php if (!empty($attempt['error_message'])): ?><span class="text-amber-300">Error: <?= e($attempt['error_message']); ?></span><?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($attemptsByTransaction[(int) $row['id']])): ?>
                                                        <p class="text-sm text-slate-400">No provider attempts recorded yet.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="admin-action-group">
                                                <h3>Provider logs</h3>
                                                <div class="space-y-2">
                                                    <?php foreach (array_slice($providerLogsByTransaction[(int) $row['id']] ?? [], 0, 3) as $log): ?>
                                                        <div class="rounded-lg border border-white/10 bg-slate-900/40 p-3 text-sm">
                                                            <div class="flex items-center justify-between gap-2">
                                                                <strong class="text-white"><?= e(ucfirst((string) $log['direction'])); ?> <?= e((string) ($log['http_status'] ?? '')); ?></strong>
                                                                <span class="text-xs text-slate-400"><?= e($log['created_at']); ?></span>
                                                            </div>
                                                            <div class="mt-1 text-xs text-slate-300"><?= e($log['endpoint'] ?? ''); ?><?= $log['response_time_ms'] !== null ? ' | ' . e((string) $log['response_time_ms']) . 'ms' : ''; ?></div>
                                                            <?php if (!empty($log['error_message'])): ?><p class="mt-1 text-xs text-amber-300"><?= e($log['error_message']); ?></p><?php endif; ?>
                                                            <?php if (!empty($log['redacted_payload'])): ?><?php $payloadPreview = (string) $log['redacted_payload']; ?><pre class="mt-2 max-h-28 overflow-auto whitespace-pre-wrap rounded-lg bg-slate-950 p-2 text-xs text-slate-300"><?= e(strlen($payloadPreview) > 700 ? substr($payloadPreview, 0, 700) . '...' : $payloadPreview); ?></pre><?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($providerLogsByTransaction[(int) $row['id']])): ?>
                                                        <p class="text-sm text-slate-400">No provider API logs recorded yet.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="admin-action-group">
                                                <h3>Timeline</h3>
                                                <div class="space-y-2">
                                                    <?php foreach ($eventsByTransaction[(int) $row['id']] ?? [] as $event): ?>
                                                        <div class="rounded-lg border border-white/10 bg-slate-900/40 p-3 text-sm">
                                                            <div class="flex items-center justify-between gap-2">
                                                                <strong class="text-white"><?= e($event['event_type']); ?></strong>
                                                                <span class="text-xs text-slate-400"><?= e($event['created_at']); ?></span>
                                                            </div>
                                                            <p class="mt-1 text-slate-300"><?= e($event['notes'] ?? ''); ?></p>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($eventsByTransaction[(int) $row['id']])): ?>
                                                        <p class="text-sm text-slate-400">No timeline events recorded yet.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-6">
                <?php render_pagination($pagination, 'admin/transactions.php', $paginationQuery); ?>
            </div>
        </section>
</div>
<?php if ($hasVisiblePendingTransactions): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var intervalMs = 15000;
    window.setTimeout(function refreshPendingTransactions() {
        var active = document.activeElement;
        var isEditing = active && active.matches && active.matches('input, select, textarea');
        var hasSelection = document.querySelector('input[name="transaction_ids[]"]:checked') !== null;
        var drawerOpen = document.querySelector('.admin-inline-drawer[open]') !== null;

        if (!document.hidden && !isEditing && !hasSelection && !drawerOpen) {
            window.location.reload();
            return;
        }

        window.setTimeout(refreshPendingTransactions, intervalMs);
    }, intervalMs);
});
</script>
<?php endif; ?>
<?php render_footer(); ?>
