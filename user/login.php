<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (user()) {
    redirect(base_url('user/dashboard.php'));
}

$error = null;
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
if (is_post()) {
    verify_csrf();
    if (auth()->loginUser($email, $_POST['password'] ?? '')) {
        redirect(base_url('user/dashboard.php'));
    }
    $error = 'Invalid credentials.';
}

render_header('User Login');
?>
<div class="mx-auto max-w-lg">
    <section class="rounded-3xl border border-white/10 bg-white/5 p-8 shadow-lg">
        <p class="eyebrow">Welcome back</p>
        <h1 class="surface-section-title">User Login</h1>
        <p class="surface-section-copy">Sign in to continue with wallet funding, quick purchases, and transaction tracking.</p>
        <?php if ($message = flash('success')): ?>
            <div class="notice notice-success mt-4"><?= e($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="notice notice-error mt-4"><?= e($error); ?></div>
        <?php endif; ?>
        <form method="post" class="mt-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <div>
                <label class="mb-2 block text-sm">Email</label>
                <input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="email" type="email" value="<?= e($email); ?>" placeholder="you@example.com" required>
            </div>
            <div>
                <label class="mb-2 block text-sm">Password</label>
                <input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="password" type="password" placeholder="Enter your password" required>
            </div>
            <button class="w-full rounded-lg bg-cyan-400 px-5 py-3 font-semibold text-slate-950" type="submit">Sign In</button>
        </form>
        <div class="mt-5 text-center text-sm text-slate-400">
            Forgot your password?
            <a class="text-cyan-300" href="<?= e(base_url('user/forgot-password.php')); ?>">Reset it here</a>
        </div>
        <div class="mt-4 text-center text-sm text-slate-400">
            New to GemData?
            <a class="text-cyan-300" href="<?= e(base_url('user/register.php')); ?>">Create your account</a>
        </div>
    </section>
</div>
<?php render_footer(); ?>
