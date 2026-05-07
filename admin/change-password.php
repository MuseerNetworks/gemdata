<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = auth()->requireAdmin();
if (!$admin) {
    redirect(base_url('admin/login.php'));
}

$error = null;
if (is_post()) {
    verify_csrf();
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirmation'] ?? '';
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/', $password)) {
        $error = 'Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        db()->execute(
            'UPDATE admins SET password_hash = :password_hash, force_password_change = 0 WHERE id = :id',
            ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $admin['id']]
        );
        app(\GemData\Classes\ActivityLogger::class)->log('admin', (int) $admin['id'], 'admin_password_changed', 'Admin changed password.');
        flash('success', 'Password updated successfully.');
        redirect(base_url('admin/dashboard.php'));
    }
}

render_header('Change Admin Password');
?>
<div class="mx-auto max-w-xl rounded-2xl border border-white/10 bg-white/5 p-8">
    <h1 class="text-3xl font-black">Change Default Admin Password</h1>
    <p class="mt-2 text-slate-300">This account is seeded with a temporary password and must be updated before using the admin panel.</p>
    <?php if ($error): ?>
        <div class="notice notice-error mt-4"><?= e($error); ?></div>
    <?php endif; ?>
    <form method="post" class="mt-6 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="password" type="password" placeholder="New password">
        <input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="password_confirmation" type="password" placeholder="Confirm password">
        <button class="w-full rounded-lg bg-cyan-400 px-5 py-3 font-semibold text-slate-950" type="submit">Update Password</button>
    </form>
</div>
<?php render_footer(); ?>
