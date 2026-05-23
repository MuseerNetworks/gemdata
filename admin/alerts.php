<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = (admin_can('alerts.manage') || admin_can('cms.manage')) ? admin_user() : require_permission('alerts.manage');
if (!$admin) {
    redirect(base_url('admin/login.php'));
}

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'broadcast');
    try {
        if ($action === 'broadcast') {
            db()->execute(
                'INSERT INTO broadcasts (title, message, target_scope, channel, status, created_by_admin_id, sent_at)
                 VALUES (:title, :message, :target_scope, :channel, :status, :admin_id, NOW())',
                [
                    'title' => trim((string) $_POST['title']),
                    'message' => trim((string) $_POST['message']),
                    'target_scope' => trim((string) ($_POST['target_scope'] ?? 'all_users')),
                    'channel' => 'in_app',
                    'status' => 'sent',
                    'admin_id' => (int) $admin['id'],
                ]
            );
        } elseif ($action === 'announcement') {
            db()->execute(
                'INSERT INTO announcements (title, body, audience, status, created_by_admin_id, published_at)
                 VALUES (:title, :body, :audience, :status, :admin_id, :published_at)',
                [
                    'title' => trim((string) $_POST['title']),
                    'body' => trim((string) $_POST['message']),
                    'audience' => trim((string) ($_POST['target_scope'] ?? 'all_users')),
                    'status' => (string) ($_POST['status'] ?? 'draft'),
                    'admin_id' => (int) $admin['id'],
                    'published_at' => ($_POST['status'] ?? '') === 'published' ? date('Y-m-d H:i:s') : null,
                ]
            );
        } elseif ($action === 'banner') {
            db()->execute(
                'INSERT INTO homepage_banners (title, subtitle, cta_label, cta_url, status, created_by_admin_id)
                 VALUES (:title, :subtitle, :cta_label, :cta_url, :status, :admin_id)',
                [
                    'title' => trim((string) $_POST['title']),
                    'subtitle' => trim((string) ($_POST['subtitle'] ?? '')),
                    'cta_label' => trim((string) ($_POST['cta_label'] ?? '')),
                    'cta_url' => trim((string) ($_POST['cta_url'] ?? '')),
                    'status' => (string) ($_POST['status'] ?? 'draft'),
                    'admin_id' => (int) $admin['id'],
                ]
            );
        } elseif ($action === 'promo') {
            db()->execute(
                'INSERT INTO promo_codes (code, description, discount_type, discount_value, status, starts_at, ends_at, created_by_admin_id)
                 VALUES (:code, :description, :discount_type, :discount_value, :status, :starts_at, :ends_at, :admin_id)',
                [
                    'code' => strtoupper(trim((string) ($_POST['code'] ?? ''))),
                    'description' => trim((string) ($_POST['description'] ?? '')),
                    'discount_type' => in_array($_POST['discount_type'] ?? 'percent', ['percent', 'fixed'], true) ? $_POST['discount_type'] : 'percent',
                    'discount_value' => max(0, (float) ($_POST['discount_value'] ?? 0)),
                    'status' => (string) ($_POST['status'] ?? 'draft'),
                    'starts_at' => trim((string) ($_POST['starts_at'] ?? '')) ?: null,
                    'ends_at' => trim((string) ($_POST['ends_at'] ?? '')) ?: null,
                    'admin_id' => (int) $admin['id'],
                ]
            );
        } elseif ($action === 'campaign') {
            db()->execute(
                'INSERT INTO campaign_drafts (title, channel, audience, message, status, scheduled_at, created_by_admin_id)
                 VALUES (:title, :channel, :audience, :message, :status, :scheduled_at, :admin_id)',
                [
                    'title' => trim((string) $_POST['title']),
                    'channel' => in_array($_POST['channel'] ?? 'in_app', ['push', 'sms', 'email', 'in_app'], true) ? $_POST['channel'] : 'in_app',
                    'audience' => trim((string) ($_POST['target_scope'] ?? 'all_users')),
                    'message' => trim((string) $_POST['message']),
                    'status' => (string) ($_POST['status'] ?? 'draft'),
                    'scheduled_at' => trim((string) ($_POST['scheduled_at'] ?? '')) ?: null,
                    'admin_id' => (int) $admin['id'],
                ]
            );
        } elseif ($action === 'referral_setting') {
            db()->execute(
                'INSERT INTO referral_settings (setting_key, setting_value, updated_by_admin_id)
                 VALUES (:setting_key, :setting_value, :admin_id)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by_admin_id = VALUES(updated_by_admin_id), updated_at = NOW()',
                [
                    'setting_key' => trim((string) ($_POST['setting_key'] ?? '')),
                    'setting_value' => trim((string) ($_POST['setting_value'] ?? '')),
                    'admin_id' => (int) $admin['id'],
                ]
            );
        } elseif ($action === 'user_notice') {
            db()->execute(
                'INSERT INTO user_notices (user_id, title, message, status, created_by_admin_id)
                 VALUES (:user_id, :title, :message, :status, :admin_id)',
                [
                    'user_id' => (int) ($_POST['user_id'] ?? 0) ?: null,
                    'title' => trim((string) $_POST['title']),
                    'message' => trim((string) $_POST['message']),
                    'status' => (string) ($_POST['status'] ?? 'active'),
                    'admin_id' => (int) $admin['id'],
                ]
            );
        }
        app(\GemData\Classes\ActivityLogger::class)->log('admin', (int) $admin['id'], 'cms_operation_saved', 'Admin saved CMS/operations item.', ['action' => $action]);
        flash('success', 'Operation saved successfully.');
    } catch (Throwable $throwable) {
        flash('success', 'Operation failed: ' . $throwable->getMessage());
    }
    redirect(base_url('admin/alerts.php'));
}

$fraudEvents = db()->safeQuery('SELECT fe.*, u.full_name FROM fraud_events fe LEFT JOIN users u ON u.id = fe.user_id ORDER BY fe.id DESC LIMIT 25');
$broadcasts = db()->query('SELECT b.*, a.full_name AS admin_name FROM broadcasts b INNER JOIN admins a ON a.id = b.created_by_admin_id ORDER BY b.id DESC LIMIT 20');
$announcements = db()->tableExists('announcements') ? db()->query('SELECT * FROM announcements ORDER BY id DESC LIMIT 10') : [];
$banners = db()->tableExists('homepage_banners') ? db()->query('SELECT * FROM homepage_banners ORDER BY id DESC LIMIT 10') : [];
$promos = db()->tableExists('promo_codes') ? db()->query('SELECT * FROM promo_codes ORDER BY id DESC LIMIT 10') : [];
$campaigns = db()->tableExists('campaign_drafts') ? db()->query('SELECT * FROM campaign_drafts ORDER BY id DESC LIMIT 10') : [];
$referralSettings = db()->tableExists('referral_settings') ? db()->query('SELECT * FROM referral_settings ORDER BY setting_key') : [];
$notices = db()->tableExists('user_notices') ? db()->query('SELECT un.*, u.full_name FROM user_notices un LEFT JOIN users u ON u.id = un.user_id ORDER BY un.id DESC LIMIT 10') : [];
$users = db()->query('SELECT id, full_name, email FROM users ORDER BY full_name LIMIT 200');

render_header('Alerts', 'admin');
?>
<div class="space-y-6">
    <?php if ($message = flash('success')): ?><div class="notice notice-success"><?= e($message); ?></div><?php endif; ?>
    <section class="surface-card p-6">
        <h1 class="text-3xl font-black text-white">CMS & Operations Tools</h1>
        <p class="mt-2 text-slate-400">Broadcasts, announcements, homepage banners, promos, campaigns, referral settings, and user notices.</p>
        <form method="post" class="mt-6 grid gap-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <input type="hidden" name="action" value="broadcast">
            <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="title" placeholder="broadcast title">
            <textarea class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="message" rows="4" placeholder="message"></textarea>
            <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="target_scope"><option value="all_users">All users</option><option value="api_users">API users</option></select>
            <button class="primary-action" type="submit">Send Broadcast</button>
        </form>
    </section>
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Announcement</h2><form method="post" class="mt-4 grid gap-3"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="action" value="announcement"><input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="title" placeholder="Title"><textarea class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="message" placeholder="Body"></textarea><select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="status"><option value="draft">Draft</option><option value="published">Published</option></select><button class="secondary-action" type="submit">Save Announcement</button></form></section>
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Homepage Banner</h2><form method="post" class="mt-4 grid gap-3"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="action" value="banner"><input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="title" placeholder="Title"><input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="subtitle" placeholder="Subtitle"><input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="cta_label" placeholder="CTA label"><input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="cta_url" placeholder="CTA URL"><select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="status"><option value="draft">Draft</option><option value="active">Active</option></select><button class="secondary-action" type="submit">Save Banner</button></form></section>
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Promo Code</h2><form method="post" class="mt-4 grid gap-3"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="action" value="promo"><input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="code" placeholder="Code"><input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="description" placeholder="Description"><select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="discount_type"><option value="percent">Percent</option><option value="fixed">Fixed</option></select><input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="discount_value" type="number" step="0.01" placeholder="Value"><select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="status"><option value="draft">Draft</option><option value="active">Active</option></select><button class="secondary-action" type="submit">Save Promo</button></form></section>
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Campaign Draft</h2><form method="post" class="mt-4 grid gap-3"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="action" value="campaign"><input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="title" placeholder="Title"><select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="channel"><option value="in_app">In-app</option><option value="sms">SMS</option><option value="email">Email</option><option value="push">Push</option></select><textarea class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="message" placeholder="Message"></textarea><input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="scheduled_at" type="datetime-local"><button class="secondary-action" type="submit">Save Campaign</button></form></section>
    </div>
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Referral Settings</h2><form method="post" class="mt-4 grid gap-3"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="action" value="referral_setting"><input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="setting_key" placeholder="setting key e.g. default_bonus"><input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="setting_value" placeholder="value"><button class="secondary-action" type="submit">Save Setting</button></form><div class="mt-4 space-y-2"><?php foreach ($referralSettings as $row): ?><div class="rounded-xl border border-white/10 bg-slate-900/40 p-3 text-sm"><strong class="text-white"><?= e($row['setting_key']); ?></strong><span class="ml-2 text-slate-400"><?= e($row['setting_value']); ?></span></div><?php endforeach; ?></div></section>
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">User Notice</h2><form method="post" class="mt-4 grid gap-3"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="action" value="user_notice"><select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="user_id"><option value="0">All users</option><?php foreach ($users as $user): ?><option value="<?= (int) $user['id']; ?>"><?= e($user['full_name'] . ' - ' . $user['email']); ?></option><?php endforeach; ?></select><input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="title" placeholder="Title"><textarea class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="message" placeholder="Notice"></textarea><button class="secondary-action" type="submit">Save Notice</button></form></section>
    </div>
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Fraud Events</h2><div class="space-y-3 mt-4"><?php foreach ($fraudEvents as $row): ?><div class="rounded-xl border border-white/10 bg-slate-900/40 p-4"><div class="flex items-center justify-between"><strong class="text-white"><?= e($row['event_type']); ?></strong><span class="text-xs text-slate-400"><?= e($row['risk_level']); ?></span></div><p class="mt-2 text-sm text-slate-300"><?= e($row['description']); ?></p><p class="mt-1 text-xs text-slate-500"><?= e(($row['full_name'] ?? 'Unknown user') . ' · ' . $row['created_at']); ?></p></div><?php endforeach; ?></div></section>
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Recent Broadcasts</h2><div class="space-y-3 mt-4"><?php foreach ($broadcasts as $row): ?><div class="rounded-xl border border-white/10 bg-slate-900/40 p-4"><div class="flex items-center justify-between"><strong class="text-white"><?= e($row['title']); ?></strong><span class="text-xs text-slate-400"><?= e($row['sent_at'] ?? $row['created_at']); ?></span></div><p class="mt-2 text-sm text-slate-300"><?= e($row['message']); ?></p><p class="mt-1 text-xs text-slate-500"><?= e($row['admin_name']); ?> · <?= e($row['target_scope']); ?></p></div><?php endforeach; ?></div></section>
    </div>
    <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Recent CMS Items</h2><div class="grid gap-4 md:grid-cols-3 mt-4"><div><h3 class="font-bold text-white">Announcements</h3><?php foreach ($announcements as $row): ?><p class="text-sm text-slate-400"><?= e($row['title'] . ' · ' . $row['status']); ?></p><?php endforeach; ?></div><div><h3 class="font-bold text-white">Banners</h3><?php foreach ($banners as $row): ?><p class="text-sm text-slate-400"><?= e($row['title'] . ' · ' . $row['status']); ?></p><?php endforeach; ?></div><div><h3 class="font-bold text-white">Promos</h3><?php foreach ($promos as $row): ?><p class="text-sm text-slate-400"><?= e($row['code'] . ' · ' . $row['status']); ?></p><?php endforeach; ?></div><div><h3 class="font-bold text-white">Campaigns</h3><?php foreach ($campaigns as $row): ?><p class="text-sm text-slate-400"><?= e($row['title'] . ' · ' . $row['status']); ?></p><?php endforeach; ?></div><div><h3 class="font-bold text-white">Notices</h3><?php foreach ($notices as $row): ?><p class="text-sm text-slate-400"><?= e($row['title'] . ' · ' . ($row['full_name'] ?? 'All users')); ?></p><?php endforeach; ?></div></div></section>
</div>
<?php render_footer(); ?>
