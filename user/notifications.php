<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$notifications = app(\GemData\Classes\NotificationService::class);

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $notifId = (int) ($_POST['notification_id'] ?? 0);

    if ($action === 'mark_read' && $notifId > 0) {
        $notifications->markAsRead($notifId, (int) $user['id']);
    } elseif ($action === 'mark_all_read') {
        $notifications->markAllAsRead((int) $user['id']);
    } elseif ($action === 'delete' && $notifId > 0) {
        $notifications->deleteNotification($notifId, (int) $user['id']);
    }

    flash('success', 'Notification updated.');
    redirect(base_url('user/notifications.php'));
}

$rows = $notifications->getForUser((int) $user['id'], 100);
$unreadCount = $notifications->unreadCount((int) $user['id']);

render_header('Notifications', 'user');
?>
<div class="surface-card p-6">
    <div class="flex items-center justify-between">
        <div>
            <p class="eyebrow">Notifications</p>
            <h1 class="surface-section-title">Notifications</h1>
        </div>
        <?php if ($unreadCount > 0): ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="mark_all_read">
                <button class="secondary-action text-sm" type="submit">Mark all as read</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="mt-6 space-y-3">
        <?php if (empty($rows)): ?>
            <div class="rounded-xl border border-white/10 bg-slate-900/40 p-6 text-center text-slate-400">
                <p class="text-lg font-semibold">No notifications yet</p>
                <p class="mt-2 text-sm">You'll see system alerts, transaction updates, and admin announcements here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
                <?php $isUnread = (int) $row['is_read'] === 0; ?>
                <article class="rounded-xl border p-4 <?= $isUnread ? 'border-cyan-400/20 bg-cyan-500/5' : 'border-white/10 bg-slate-900/70'; ?>" data-search-item data-search="<?= e($row['title'] . ' ' . $row['message'] . ' ' . $row['type']); ?>">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-3">
                                <?php if ($isUnread): ?>
                                    <span class="h-2 w-2 shrink-0 rounded-full bg-cyan-400"></span>
                                <?php endif; ?>
                                <h2 class="font-semibold <?= $isUnread ? 'text-white' : 'text-slate-300'; ?>"><?= e($row['title']); ?></h2>
                                <span class="rounded-full px-2 py-0.5 text-xs font-bold
                                    <?= match($row['type']) {
                                        'success' => 'bg-emerald-500/20 text-emerald-300',
                                        'warning' => 'bg-amber-500/20 text-amber-300',
                                        'error' => 'bg-rose-500/20 text-rose-300',
                                        default => 'bg-cyan-500/20 text-cyan-300',
                                    }; ?>"><?= e(ucfirst($row['type'])); ?></span>
                            </div>
                            <p class="mt-2 text-slate-300"><?= e($row['message']); ?></p>
                            <p class="mt-2"><span class="timestamp" title="<?= e($row['created_at']); ?>"><?= e(human_datetime((string) $row['created_at'])); ?></span></p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <?php if ($isUnread): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?= (int) $row['id']; ?>">
                                    <button class="rounded-lg border border-white/10 px-2.5 py-1 text-xs font-semibold text-slate-400 hover:text-white" type="submit" title="Mark as read">✓</button>
                                </form>
                            <?php endif; ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="notification_id" value="<?= (int) $row['id']; ?>">
                                <button class="rounded-lg border border-white/10 px-2.5 py-1 text-xs font-semibold text-rose-400 hover:bg-rose-500/10" type="submit" title="Delete" onclick="return confirm('Delete this notification?')">✕</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php render_footer(); ?>
