<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = (admin_can('security.manage') || admin_can('alerts.manage') || admin_can('roles.manage'))
    ? admin_user()
    : require_permission('security.manage');
if (!$admin) {
    redirect(base_url('admin/login.php'));
}

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $reason = trim((string) ($_POST['reason'] ?? ''));
    if ($reason === '') {
        flash('success', 'A reason is required for security actions.');
        redirect(base_url('admin/security.php'));
    }

    try {
        if ($action === 'fraud_status') {
            $status = (string) ($_POST['review_status'] ?? 'reviewing');
            if (!in_array($status, ['reviewing', 'dismissed', 'confirmed'], true)) {
                throw new RuntimeException('Unsupported fraud review status.');
            }
            db()->execute(
                'UPDATE fraud_events
                 SET review_status = :status, reviewed_by_admin_id = :admin_id, reviewed_at = NOW(), admin_notes = :notes
                 WHERE id = :id',
                [
                    'status' => $status,
                    'admin_id' => (int) $admin['id'],
                    'notes' => $reason,
                    'id' => (int) ($_POST['fraud_event_id'] ?? 0),
                ]
            );
            app(\GemData\Classes\ActivityLogger::class)->log('admin', (int) $admin['id'], 'fraud_event_reviewed', 'Admin reviewed fraud event.', [
                'fraud_event_id' => (int) ($_POST['fraud_event_id'] ?? 0),
                'status' => $status,
                'reason' => $reason,
            ]);
            flash('success', 'Fraud event updated.');
        } elseif ($action === 'suspend_user') {
            db()->execute('UPDATE users SET status = "inactive" WHERE id = :id', ['id' => (int) ($_POST['user_id'] ?? 0)]);
            app(\GemData\Classes\ActivityLogger::class)->log('admin', (int) $admin['id'], 'user_suspended_security', 'Admin suspended user from Security Center.', [
                'user_id' => (int) ($_POST['user_id'] ?? 0),
                'reason' => $reason,
            ]);
            flash('success', 'User suspended.');
        }
    } catch (Throwable $throwable) {
        flash('success', 'Security action failed: ' . $throwable->getMessage());
    }
    redirect(base_url('admin/security.php'));
}

$riskSummary = [
    'open_fraud' => (int) (db()->first('SELECT COUNT(*) AS total FROM fraud_events WHERE COALESCE(review_status, "open") = "open"')['total'] ?? 0),
    'high_risk' => (int) (db()->first('SELECT COUNT(*) AS total FROM fraud_events WHERE risk_level = "high" AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')['total'] ?? 0),
    'failed_today' => (int) (db()->first('SELECT COUNT(*) AS total FROM transactions WHERE status = "failed" AND DATE(created_at) = CURDATE()')['total'] ?? 0),
    'api_abuse' => (int) (db()->first('SELECT COUNT(*) AS total FROM api_rate_limits WHERE request_count >= 60 AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)')['total'] ?? 0),
];
$fraudEvents = db()->query(
    'SELECT fe.*, u.full_name, u.email, u.status AS user_status
     FROM fraud_events fe
     LEFT JOIN users u ON u.id = fe.user_id
     ORDER BY fe.id DESC LIMIT 40'
);
$duplicates = db()->query(
    'SELECT user_id, recipient, amount, COUNT(*) AS attempts, MAX(created_at) AS last_seen
     FROM transactions
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
     GROUP BY user_id, recipient, amount
     HAVING attempts > 1
     ORDER BY attempts DESC LIMIT 15'
);
$velocity = db()->query(
    'SELECT t.user_id, u.full_name, COUNT(*) AS attempts, MAX(t.created_at) AS last_seen
     FROM transactions t
     INNER JOIN users u ON u.id = t.user_id
     WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
     GROUP BY t.user_id
     HAVING attempts >= 4
     ORDER BY attempts DESC LIMIT 15'
);
$loginFailures = db()->tableExists('login_attempts')
    ? db()->query('SELECT * FROM login_attempts WHERE success = 0 ORDER BY id DESC LIMIT 20')
    : [];
$apiAbuse = db()->query(
    'SELECT arl.*, ak.api_key, u.full_name
     FROM api_rate_limits arl
     INNER JOIN api_keys ak ON ak.id = arl.api_key_id
     INNER JOIN api_users au ON au.id = ak.api_user_id
     INNER JOIN users u ON u.id = au.user_id
     ORDER BY arl.request_count DESC, arl.updated_at DESC LIMIT 20'
);
$webhookFailures = db()->tableExists('webhook_dead_letters')
    ? db()->query('SELECT * FROM webhook_dead_letters ORDER BY id DESC LIMIT 20')
    : db()->query('SELECT * FROM webhook_events WHERE processing_status = "failed" ORDER BY id DESC LIMIT 20');
$activity = db()->query('SELECT * FROM activity_logs ORDER BY id DESC LIMIT 35');

render_header('Security Center', 'admin');
?>
<div class="space-y-6">
    <?php if ($message = flash('success')): ?><div class="notice notice-success"><?= e($message); ?></div><?php endif; ?>
    <section class="surface-card p-6">
        <p class="eyebrow">Fraud & Security</p>
        <h1 class="mt-2 text-3xl font-black text-white">Security Center</h1>
        <p class="mt-2 text-slate-400">Review fraud events, duplicate patterns, API pressure, webhook failures, and sensitive admin activity.</p>
    </section>
    <div class="grid gap-4 md:grid-cols-4">
        <div class="surface-card p-5"><p class="text-slate-400">Open Fraud Events</p><p class="mt-2 text-3xl font-black text-white"><?= (int) $riskSummary['open_fraud']; ?></p></div>
        <div class="surface-card p-5"><p class="text-slate-400">High Risk This Week</p><p class="mt-2 text-3xl font-black text-white"><?= (int) $riskSummary['high_risk']; ?></p></div>
        <div class="surface-card p-5"><p class="text-slate-400">Failed Today</p><p class="mt-2 text-3xl font-black text-white"><?= (int) $riskSummary['failed_today']; ?></p></div>
        <div class="surface-card p-5"><p class="text-slate-400">API Abuse Windows</p><p class="mt-2 text-3xl font-black text-white"><?= (int) $riskSummary['api_abuse']; ?></p></div>
    </div>
    <section class="surface-card p-6">
        <h2 class="text-2xl font-black text-white">Fraud Event Review</h2>
        <div class="table-shell mt-4">
            <table>
                <thead><tr class="text-slate-400"><th>User</th><th>Event</th><th>Risk</th><th>Status</th><th>Description</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($fraudEvents as $event): ?>
                    <tr>
                        <td><div class="font-semibold text-white"><?= e($event['full_name'] ?? 'Unknown'); ?></div><div class="text-xs text-slate-400"><?= e($event['email'] ?? ''); ?></div></td>
                        <td><?= e($event['event_type']); ?></td>
                        <td><?= e($event['risk_level']); ?></td>
                        <td><?= e($event['review_status'] ?? 'open'); ?></td>
                        <td><span class="text-xs text-slate-400"><?= e($event['description']); ?></span></td>
                        <td>
                            <form method="post" class="grid gap-2">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="fraud_status">
                                <input type="hidden" name="fraud_event_id" value="<?= (int) $event['id']; ?>">
                                <select class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm" name="review_status">
                                    <option value="reviewing">Reviewing</option>
                                    <option value="confirmed">Confirm risk</option>
                                    <option value="dismissed">Dismiss</option>
                                </select>
                                <input class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm" name="reason" placeholder="Reason" required>
                                <button class="secondary-action" type="submit">Save</button>
                            </form>
                            <?php if (!empty($event['user_id']) && ($event['user_status'] ?? '') === 'active'): ?>
                                <form method="post" class="mt-2">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="suspend_user">
                                    <input type="hidden" name="user_id" value="<?= (int) $event['user_id']; ?>">
                                    <input type="hidden" name="reason" value="Suspended from fraud event <?= (int) $event['id']; ?>">
                                    <button class="secondary-action danger-inline" type="submit">Suspend user</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($fraudEvents === []): ?><tr><td colspan="6" class="text-slate-400">No fraud events recorded yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Duplicate Patterns</h2><div class="table-shell mt-4"><table><thead><tr class="text-slate-400"><th>User ID</th><th>Recipient</th><th>Amount</th><th>Attempts</th><th>Last Seen</th></tr></thead><tbody><?php foreach ($duplicates as $row): ?><tr><td><?= (int) $row['user_id']; ?></td><td><?= e($row['recipient']); ?></td><td><?= e(money($row['amount'])); ?></td><td><?= (int) $row['attempts']; ?></td><td><?= e($row['last_seen']); ?></td></tr><?php endforeach; ?><?php if ($duplicates === []): ?><tr><td colspan="5" class="text-slate-400">No duplicate pressure detected.</td></tr><?php endif; ?></tbody></table></div></section>
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Velocity Checks</h2><div class="table-shell mt-4"><table><thead><tr class="text-slate-400"><th>User</th><th>Attempts</th><th>Last Seen</th></tr></thead><tbody><?php foreach ($velocity as $row): ?><tr><td><?= e($row['full_name']); ?></td><td><?= (int) $row['attempts']; ?></td><td><?= e($row['last_seen']); ?></td></tr><?php endforeach; ?><?php if ($velocity === []): ?><tr><td colspan="3" class="text-slate-400">No high-velocity users right now.</td></tr><?php endif; ?></tbody></table></div></section>
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">API Abuse Monitor</h2><div class="table-shell mt-4"><table><thead><tr class="text-slate-400"><th>User</th><th>Key</th><th>Window</th><th>Requests</th></tr></thead><tbody><?php foreach ($apiAbuse as $row): ?><?php $masked = substr((string) $row['api_key'], 0, 8) . '...' . substr((string) $row['api_key'], -6); ?><tr><td><?= e($row['full_name']); ?></td><td class="font-mono text-xs"><?= e($masked); ?></td><td><?= e($row['window_key']); ?></td><td><?= (int) $row['request_count']; ?></td></tr><?php endforeach; ?></tbody></table></div></section>
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Webhook Failures</h2><div class="table-shell mt-4"><table><thead><tr class="text-slate-400"><th>Source</th><th>Status</th><th>Error</th><th>Created</th></tr></thead><tbody><?php foreach ($webhookFailures as $row): ?><tr><td><?= e($row['source']); ?></td><td><?= e($row['status'] ?? $row['processing_status'] ?? 'failed'); ?></td><td><span class="text-xs text-slate-400"><?= e($row['last_error'] ?? $row['event_key'] ?? ''); ?></span></td><td><?= e($row['created_at']); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
    </div>
    <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Admin Activity Logs</h2><div class="table-shell mt-4"><table><thead><tr class="text-slate-400"><th>Actor</th><th>Action</th><th>Description</th><th>Time</th></tr></thead><tbody><?php foreach ($activity as $row): ?><tr><td><?= e($row['actor_type'] . '#' . $row['actor_id']); ?></td><td><?= e($row['action']); ?></td><td><?= e($row['description']); ?></td><td><?= e($row['created_at']); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
</div>
<?php render_footer(); ?>
