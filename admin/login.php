<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (admin_user()) {
    redirect(base_url('admin/dashboard.php'));
}

$adminCount = (int) (db()->first('SELECT COUNT(*) AS total FROM admins')['total'] ?? 0);
$error = null;
$success = flash('success');
if (is_post()) {
    verify_csrf();
    if (auth()->loginAdmin(strtolower(trim($_POST['email'] ?? '')), $_POST['password'] ?? '')) {
        $currentAdmin = auth()->admin();
        if ($currentAdmin && (int) ($currentAdmin['force_password_change'] ?? 0) === 1) {
            redirect(base_url('admin/change-password.php'));
        }
        redirect(base_url('admin/dashboard.php'));
    }
    $error = 'Invalid admin credentials.';
}

render_header('Admin Login');
?>
<div class="mx-auto max-w-lg rounded-2xl border border-white/10 bg-white/5 p-8">
    <h1 class="text-3xl font-black">Admin Login</h1>
    <?php if ($success): ?>
        <div class="notice notice-success mt-4"><?= e($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="notice notice-error mt-4"><?= e($error); ?></div>
    <?php endif; ?>
    <form method="post" class="mt-6 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="email" type="email" placeholder="Email">
        <input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="password" type="password" placeholder="Password">
        <button class="w-full rounded-lg bg-cyan-400 px-5 py-3 font-semibold text-slate-950" type="submit">Enter Admin Panel</button>
    </form>
    <?php if ($adminCount === 0): ?>
        <div class="mt-6 rounded-2xl border border-cyan-200/60 bg-cyan-50/80 p-4 text-sm text-slate-700">
            No admin account exists yet.
            <a class="font-semibold text-cyan-700" href="<?= e(base_url('admin/register.php')); ?>">Create the first admin account</a>
        </div>
    <?php endif; ?>
</div>
<?php render_footer(); ?>
