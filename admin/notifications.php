<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('alerts.manage');
$notifications = app(\GemData\Classes\NotificationService::class);

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'send_notification') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        $type = trim((string) ($_POST['type'] ?? 'info'));
        $target = trim((string) ($_POST['target'] ?? 'all'));

        if ($title === '' || $message === '') {
            flash('success', 'Title and message are required.');
            redirect(base_url('admin/notifications.php'));
        }

        $allowedTypes = ['info', 'success', 'warning', 'error'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'info';
        }

        $count = 0;
        if ($target === 'all') {
            $count = $notifications->createForAllUsers($title, $message, $type, (int) $admin['id']);
        } elseif ($target === 'specific') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            if ($userId > 0) {
                $notifications->create($userId, $title, $message, $type);
                $count = 1;
            }
        } elseif (in_array($target, ['USER', 'RESELLER', 'AGENT', 'API_RESELLER'], true)) {
            $count = $notifications->createForUsersByTier($target, $title, $message, $type);
        }

        // Also store as broadcast for admin records
        db()->execute(
            'INSERT INTO broadcasts (title, message, target_scope, type, channel, status, created_by_admin_id, sent_at)
             VALUES (:title, :message, :target_scope, :type, :channel, :status, :created_by_admin_id, NOW())',
            [
                'title' => $title,
                'message' => $message,
                'target_scope' => $target === 'all' ? 'all_users' : ($target === 'specific' ? 'user_' . ($_POST['user_id'] ?? '0') : 'tier_' . $target),
                'type' => $type,
                'channel' => 'in_app',
                'status' => 'sent',
                'created_by_admin_id' => (int) $admin['id'],
            ]
        );

        app(\GemData\Classes\ActivityLogger::class)->log('admin', (int) $admin['id'], 'notification_sent', 'Sent notification to ' . $count . ' users.', ['title' => $title, 'target' => $target]);
        flash('success', 'Notification sent to ' . $count . ' user(s).');
        redirect(base_url('admin/notifications.php'));
    }

    if ($action === 'delete_broadcast') {
        $broadcastId = (int) ($_POST['broadcast_id'] ?? 0);
        if ($broadcastId > 0) {
            db()->execute('DELETE FROM broadcasts WHERE id = :id', ['id' => $broadcastId]);
            flash('success', 'Broadcast deleted.');
        }
        redirect(base_url('admin/notifications.php'));
    }
}

$broadcasts = db()->query(
    'SELECT b.*, a.full_name AS admin_name
     FROM broadcasts b
     INNER JOIN admins a ON a.id = b.created_by_admin_id
     ORDER BY b.id DESC LIMIT 30'
);

$searchQuery = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));

render_header('Notifications', 'admin');
?>
<div class="space-y-6">
    <?php if ($message = flash('success')): ?><div class="notice notice-success"><?= e($message); ?></div><?php endif; ?>

    <section class="surface-card p-6">
        <h1 class="text-3xl font-black text-white">Send Notification</h1>
        <p class="mt-2 text-slate-400">Send notifications to all users, specific users, or by account tier.</p>
        <form method="post" class="mt-6 grid gap-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <input type="hidden" name="action" value="send_notification">
            <div class="grid gap-4 md:grid-cols-2">
                <label class="space-y-2">
                    <span class="text-sm font-semibold text-slate-300">Title</span>
                    <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3 w-full" name="title" placeholder="Notification title" required>
                </label>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="space-y-2">
                        <span class="text-sm font-semibold text-slate-300">Type</span>
                        <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3 w-full" name="type">
                            <option value="info">Info</option>
                            <option value="success">Success</option>
                            <option value="warning">Warning</option>
                            <option value="error">Critical</option>
                        </select>
                    </label>
                    <label class="space-y-2">
                        <span class="text-sm font-semibold text-slate-300">Target</span>
                        <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3 w-full" name="target" id="notification-target" onchange="document.getElementById('user-id-field').style.display = this.value === 'specific' ? 'block' : 'none'">
                            <option value="all">All Users</option>
                            <option value="specific">Specific User (by ID)</option>
                            <option value="USER">Tier: User</option>
                            <option value="RESELLER">Tier: Reseller</option>
                            <option value="AGENT">Tier: Agent</option>
                            <option value="API_RESELLER">Tier: API Reseller</option>
                        </select>
                    </label>
                </div>
            </div>
            <div id="user-id-field" style="display: none;">
                <label class="space-y-2">
                    <span class="text-sm font-semibold text-slate-300">User ID</span>
                    <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3 w-full" name="user_id" placeholder="Enter user ID" type="number">
                </label>
            </div>
            <label class="space-y-2">
                <span class="text-sm font-semibold text-slate-300">Message</span>
                <textarea class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3 w-full" name="message" rows="4" placeholder="Notification message" required></textarea>
            </label>
            <div>
                <button class="rounded-lg bg-cyan-400 px-6 py-3 font-semibold text-slate-950" type="submit">Send Notification</button>
            </div>
        </form>
    </section>

    <section class="surface-card p-6">
        <h2 class="text-2xl font-black text-white">Recent Broadcasts</h2>
        <p class="mt-2 text-slate-400">History of admin-sent notifications and announcements.</p>
        <div class="space-y-3 mt-4">
            <?php if (empty($broadcasts)): ?>
                <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4 text-slate-400">No broadcasts sent yet.</div>
            <?php else: ?>
                <?php foreach ($broadcasts as $row): ?>
                    <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4" data-search-item data-search="<?= e($row['title'] . ' ' . $row['message'] . ' ' . ($row['type'] ?? 'info')); ?>">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-3">
                                    <strong class="text-white"><?= e($row['title']); ?></strong>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-bold
                                        <?= match($row['type'] ?? 'info') {
                                            'success' => 'bg-emerald-500/20 text-emerald-300',
                                            'warning' => 'bg-amber-500/20 text-amber-300',
                                            'error', 'critical' => 'bg-rose-500/20 text-rose-300',
                                            default => 'bg-cyan-500/20 text-cyan-300',
                                        }; ?>"><?= e(ucfirst($row['type'] ?? 'info')); ?></span>
                                </div>
                                <p class="mt-2 text-sm text-slate-300"><?= e($row['message']); ?></p>
                                <p class="mt-1 text-xs text-slate-500"><?= e($row['admin_name']); ?> · <?= e($row['target_scope']); ?> · <?= e($row['sent_at'] ?? $row['created_at']); ?></p>
                            </div>
                            <form method="post" class="shrink-0">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete_broadcast">
                                <input type="hidden" name="broadcast_id" value="<?= (int) $row['id']; ?>">
                                <button class="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-rose-400 hover:bg-rose-500/10" type="submit" onclick="return confirm('Delete this broadcast?')">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php render_footer(); ?>
