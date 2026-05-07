<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('alerts.manage');
if (is_post()) {
    verify_csrf();
    db()->execute('INSERT INTO broadcasts (title, message, target_scope, channel, status, created_by_admin_id, sent_at) VALUES (:title, :message, :target_scope, :channel, :status, :created_by_admin_id, NOW())', [
        'title' => trim((string) $_POST['title']),
        'message' => trim((string) $_POST['message']),
        'target_scope' => trim((string) ($_POST['target_scope'] ?? 'all_users')),
        'channel' => 'in_app',
        'status' => 'sent',
        'created_by_admin_id' => (int) $admin['id'],
    ]);
    flash('success', 'Broadcast stored successfully.');
    redirect(base_url('admin/alerts.php'));
}
$fraudEvents = db()->query('SELECT fe.*, u.full_name FROM fraud_events fe LEFT JOIN users u ON u.id = fe.user_id ORDER BY fe.id DESC LIMIT 25');
$broadcasts = db()->query('SELECT b.*, a.full_name AS admin_name FROM broadcasts b INNER JOIN admins a ON a.id = b.created_by_admin_id ORDER BY b.id DESC LIMIT 20');
render_header('Alerts', 'admin');
?>
<div class="space-y-6">
    <?php if ($message = flash('success')): ?><div class="notice notice-success"><?= e($message); ?></div><?php endif; ?>
    <section class="surface-card p-6"><h1 class="text-3xl font-black text-white">Broadcast & Alerts</h1><form method="post" class="mt-6 grid gap-4"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="title" placeholder="broadcast title"><textarea class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="message" rows="4" placeholder="message"></textarea><select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="target_scope"><option value="all_users">All users</option><option value="api_users">API users</option></select><button class="primary-action" type="submit">Send Broadcast</button></form></section>
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Fraud Events</h2><div class="space-y-3 mt-4"><?php foreach ($fraudEvents as $row): ?><div class="rounded-xl border border-white/10 bg-slate-900/40 p-4"><div class="flex items-center justify-between"><strong class="text-white"><?= e($row['event_type']); ?></strong><span class="text-xs text-slate-400"><?= e($row['risk_level']); ?></span></div><p class="mt-2 text-sm text-slate-300"><?= e($row['description']); ?></p><p class="mt-1 text-xs text-slate-500"><?= e(($row['full_name'] ?? 'Unknown user') . ' · ' . $row['created_at']); ?></p></div><?php endforeach; ?></div></section>
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Recent Broadcasts</h2><div class="space-y-3 mt-4"><?php foreach ($broadcasts as $row): ?><div class="rounded-xl border border-white/10 bg-slate-900/40 p-4"><div class="flex items-center justify-between"><strong class="text-white"><?= e($row['title']); ?></strong><span class="text-xs text-slate-400"><?= e($row['sent_at'] ?? $row['created_at']); ?></span></div><p class="mt-2 text-sm text-slate-300"><?= e($row['message']); ?></p><p class="mt-1 text-xs text-slate-500"><?= e($row['admin_name']); ?> · <?= e($row['target_scope']); ?></p></div><?php endforeach; ?></div></section>
    </div>
</div>
<?php render_footer(); ?>
