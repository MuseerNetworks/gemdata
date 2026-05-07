<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('roles.manage');
$adminService = admin_service();
if (is_post()) {
    verify_csrf();
    $invite = $adminService->createInvite((int) $admin['id'], (string) $_POST['email'], (int) $_POST['role_id'], client_ip());
    $_SESSION['flash']['invite_link'] = base_url('admin/accept-invite.php?email=' . urlencode((string) $_POST['email']) . '&token=' . urlencode($invite['token']));
    flash('success', 'Admin invite created successfully.');
    redirect(base_url('admin/invites.php'));
}
$roles = $adminService->roles();
$invites = $adminService->pendingInvites();
render_header('Invites', 'admin');
?>
<div class="space-y-6">
    <?php if ($message = flash('success')): ?><div class="notice notice-success"><?= e($message); ?></div><?php endif; ?>
    <?php if ($link = flash('invite_link')): ?><div class="notice notice-success">Invite link: <span class="font-mono text-xs"><?= e($link); ?></span></div><?php endif; ?>
    <section class="surface-card p-6"><h1 class="text-3xl font-black text-white">Create Admin Invite</h1><form method="post" class="mt-6 grid gap-4 md:grid-cols-3"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3 md:col-span-2" name="email" type="email" placeholder="invitee email"><select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="role_id"><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id']; ?>"><?= e($role['name']); ?></option><?php endforeach; ?></select><button class="primary-action md:col-span-3" type="submit">Generate Invite</button></form></section>
    <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Invite Log</h2><div class="table-shell mt-4"><table><thead><tr class="text-slate-400"><th>Email</th><th>Role</th><th>Expires</th><th>Status</th><th>Created By</th></tr></thead><tbody><?php foreach ($invites as $row): ?><tr><td><?= e($row['email']); ?></td><td><?= e($row['role_name']); ?></td><td><?= e($row['expires_at']); ?></td><td><?= $row['used_at'] ? 'Used' : 'Pending'; ?></td><td><?= e($row['created_by_name']); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
</div>
<?php render_footer(); ?>
