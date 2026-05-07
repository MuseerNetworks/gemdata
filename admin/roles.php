<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('roles.manage');
$adminService = admin_service();
if (is_post()) {
    verify_csrf();
    $adminService->assignRole((int) $_POST['admin_id'], (int) $_POST['role_id'], (int) $admin['id']);
    flash('success', 'Admin role updated.');
    redirect(base_url('admin/roles.php'));
}
$roles = $adminService->roles();
$admins = $adminService->listAdmins();
$permissions = db()->query('SELECT p.permission_key, p.label, r.slug AS role_slug FROM role_permissions rp INNER JOIN admin_permissions p ON p.id = rp.permission_id INNER JOIN admin_roles r ON r.id = rp.role_id ORDER BY r.id, p.permission_key');
$roleMatrix = [];
foreach ($permissions as $row) { $roleMatrix[$row['role_slug']][] = $row['permission_key']; }
render_header('Roles & Invites', 'admin');
?>
<div class="space-y-6">
    <?php if ($message = flash('success')): ?><div class="notice notice-success"><?= e($message); ?></div><?php endif; ?>
    <section class="surface-card p-6"><div class="flex items-center justify-between"><h1 class="text-3xl font-black text-white">Admin Roles</h1><a class="secondary-action inline-flex items-center justify-center" href="<?= e(base_url('admin/invites.php')); ?>">Open Invites</a></div><div class="table-shell mt-6"><table><thead><tr class="text-slate-400"><th>Name</th><th>Email</th><th>Role</th><th>Update</th></tr></thead><tbody><?php foreach ($admins as $row): ?><tr><td><?= e($row['full_name']); ?></td><td><?= e($row['email']); ?></td><td><?= e($row['role_name'] ?? 'Unassigned'); ?></td><td><form method="post" class="flex gap-2"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="admin_id" value="<?= (int) $row['id']; ?>"><select class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm" name="role_id"><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id']; ?>"<?= (int) $row['role_id'] === (int) $role['id'] ? ' selected' : ''; ?>><?= e($role['name']); ?></option><?php endforeach; ?></select><button class="rounded-lg border border-white/10 px-3 py-2 text-sm font-semibold" type="submit">Save</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
    <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Permission Matrix</h2><div class="grid gap-4 md:grid-cols-3 mt-5"><?php foreach ($roles as $role): ?><div class="rounded-xl border border-white/10 bg-slate-900/40 p-4"><p class="font-semibold text-white"><?= e($role['name']); ?></p><div class="mt-3 space-y-2"><?php foreach ($roleMatrix[$role['slug']] ?? [] as $permission): ?><div class="text-sm text-slate-300"><?= e($permission); ?></div><?php endforeach; ?></div></div><?php endforeach; ?></div></section>
</div>
<?php render_footer(); ?>
