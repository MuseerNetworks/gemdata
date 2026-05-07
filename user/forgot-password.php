<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (user()) {
    redirect(base_url('user/dashboard.php'));
}

$service = app(\GemData\Classes\UserSecurityService::class);
$message = null;

if (is_post()) {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $service->createSelfServicePasswordResetByEmail($email);
    }
    $message = 'If an active account matches that email, a password reset link will be sent through the configured delivery channel.';
}

render_header('Forgot Password');
?>
<div class="mx-auto max-w-4xl grid gap-8 lg:grid-cols-[0.95fr,1.05fr]">
    <section class="rounded-3xl border border-white/10 bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950/80 p-8 text-white">
        <p class="eyebrow">Account Recovery</p>
        <h1 class="mt-4 text-4xl font-black">Reset your password safely.</h1>
        <p class="mt-4 max-w-xl text-slate-300">Recovery links are single-use and time-limited. Use the same email tied to your GemData account.</p>
    </section>
    <section class="rounded-3xl border border-white/10 bg-white/5 p-8">
    <h1 class="text-3xl font-black">Forgot Password</h1>
    <p class="mt-4 text-slate-400">Enter your account email to start password recovery.</p>
    <?php if ($message): ?>
        <div class="notice notice-success mt-4"><?= e($message); ?></div>
    <?php endif; ?>
    <form method="post" class="mt-6 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <div>
            <label class="mb-2 block text-sm">Email</label>
            <input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="email" type="email" placeholder="you@example.com" required>
        </div>
        <button class="w-full rounded-lg bg-cyan-400 px-5 py-3 font-semibold text-slate-950" type="submit">Generate Reset Link</button>
    </form>
    <p class="mt-5 text-sm text-slate-400">
        Remembered it? <a class="text-cyan-300" href="<?= e(base_url('user/login.php')); ?>">Back to sign in</a>
    </p>
    </section>
</div>
<?php render_footer(); ?>
