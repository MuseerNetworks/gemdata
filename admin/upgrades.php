<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('upgrades.manage');
$upgradeSvc = app(\GemData\Classes\UpgradeRequestService::class);

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $note = trim((string) ($_POST['note'] ?? ''));

    try {
        if ($action === 'approve') {
            $upgradeSvc->approve($requestId, (int) $admin['id'], $note);
            flash('success', 'Upgrade request approved.');
        } elseif ($action === 'reject') {
            $upgradeSvc->reject($requestId, (int) $admin['id'], $note);
            flash('success', 'Upgrade request rejected.');
        } elseif ($action === 'needs_info') {
            $upgradeSvc->requestMoreInfo($requestId, (int) $admin['id'], $note);
            flash('success', 'Request marked as needing more information.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }

    redirect(base_url('admin/upgrades.php?' . http_build_query(query_except([]))));
}

$filterStatus = (string) ($_GET['status'] ?? 'pending');
$requests = $upgradeSvc->listAll(100, 0);
if ($filterStatus !== 'all') {
    $requests = array_values(array_filter($requests, static fn(array $row): bool => ($row['status'] ?? '') === $filterStatus));
}
$pendingCount = count($upgradeSvc->listPending());

render_header('Upgrade Requests', 'admin');
?>
<div class="dashboard-stack">
    <section class="surface-card p-6">
        <div class="dashboard-section-header dashboard-section-header-start">
            <div>
                <p class="eyebrow">Role Progression</p>
                <h1 class="surface-section-title">Upgrade Requests</h1>
                <p class="surface-section-copy">Review Smart to Reseller and Reseller to API access requests.</p>
            </div>
            <div class="role-stat-card role-stat-card-inline">
                <span>Pending</span>
                <strong><?= (int) $pendingCount; ?></strong>
            </div>
        </div>

        <?php if ($message = flash('success')): ?>
            <div class="notice notice-success mt-4"><?= e($message); ?></div>
        <?php endif; ?>
        <?php if ($message = flash('error')): ?>
            <div class="notice notice-error mt-4"><?= e($message); ?></div>
        <?php endif; ?>

        <div class="filter-pills mt-5">
            <?php foreach (['pending' => 'Pending', 'needs_info' => 'Needs Info', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $status => $label): ?>
                <a class="<?= $filterStatus === $status ? 'is-active' : ''; ?>" href="<?= e(base_url('admin/upgrades.php?status=' . urlencode($status))); ?>"><?= e($label); ?></a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="surface-card p-0">
        <?php if ($requests === []): ?>
            <div class="empty-state-card">
                <strong>No upgrade requests found</strong>
                <span>Requests will appear here when users submit an upgrade form.</span>
            </div>
        <?php else: ?>
            <div class="table-shell">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Upgrade</th>
                            <th>Submitted Details</th>
                            <th>Status</th>
                            <th>Review</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><span class="timestamp"><?= e(human_datetime((string) $request['created_at'])); ?></span></td>
                                <td>
                                    <strong><?= e((string) $request['full_name']); ?></strong>
                                    <div class="timestamp"><?= e((string) $request['email']); ?></div>
                                </td>
                                <td>
                                    <span class="role-pill role-pill-muted"><?= e(ucfirst((string) $request['from_type'])); ?></span>
                                    <span class="timestamp">to</span>
                                    <span class="role-pill"><?= e(ucfirst((string) $request['to_type'])); ?></span>
                                </td>
                                <td>
                                    <?php if (($request['to_type'] ?? '') === 'reseller'): ?>
                                        <strong><?= e((string) ($request['business_name'] ?? 'Business not supplied')); ?></strong>
                                        <div class="timestamp"><?= e((string) ($request['phone'] ?? $request['user_phone'] ?? '')); ?></div>
                                        <div><?= e((string) ($request['reason'] ?? '')); ?></div>
                                    <?php else: ?>
                                        <strong><?= e((string) ($request['website_url'] ?? 'URL not supplied')); ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-chip status-<?= e((string) $request['status']); ?>"><?= e(ucfirst(str_replace('_', ' ', (string) $request['status']))); ?></span>
                                    <?php if (!empty($request['admin_note'])): ?>
                                        <div class="timestamp"><?= e((string) $request['admin_note']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (($request['status'] ?? '') === 'pending'): ?>
                                        <form class="upgrade-review-form" method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="request_id" value="<?= (int) $request['id']; ?>">
                                            <textarea name="note" rows="2" placeholder="Review note"></textarea>
                                            <div>
                                                <button class="primary-action" name="action" value="approve" type="submit">Approve</button>
                                                <button class="secondary-action" name="action" value="needs_info" type="submit">More Info</button>
                                                <button class="danger-action" name="action" value="reject" type="submit">Reject</button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <span class="timestamp">Reviewed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php render_footer(); ?>
