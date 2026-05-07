<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$service = app(\GemData\Classes\UserSecurityService::class);
$resetId = (int) ($_GET['reset'] ?? $_POST['reset'] ?? 0);
$queryToken = trim((string) ($_GET['token'] ?? ''));
if ($resetId > 0 && $queryToken !== '') {
    $_SESSION['password_reset_tokens'][$resetId] = $queryToken;
    redirect(base_url('user/reset-password.php?reset=' . $resetId));
}
$token = trim((string) ($_SESSION['password_reset_tokens'][$resetId] ?? $_POST['token'] ?? ''));
$reset = ($resetId > 0 && $token !== '') ? $service->validatePasswordReset($resetId, $token) : null;
$error = null;

if (is_post()) {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['password_confirmation'] ?? '');
    if (!$reset) {
        $error = 'This reset link is invalid or expired.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
        $error = 'Password must be at least 8 characters and include uppercase, lowercase, and a number.';
    } else {
        try {
            $service->consumePasswordReset($resetId, $token, $password);
            unset($_SESSION['password_reset_tokens'][$resetId]);
            flash('success', 'Password reset completed. You can now sign in.');
            redirect(base_url('user/login.php'));
        } catch (Throwable $throwable) {
            $error = $throwable->getMessage();
        }
    }
}

render_header('Reset Password');
?>
<div class="mx-auto max-w-4xl grid gap-8 lg:grid-cols-[0.92fr,1.08fr]">
    <section class="rounded-3xl border border-white/10 bg-gradient-to-br from-cyan-950 via-slate-900 to-indigo-950/80 p-8 text-white">
        <p class="eyebrow">Password Reset</p>
        <h1 class="mt-4 text-4xl font-black">Create a new secure password.</h1>
        <ul class="mt-6 space-y-3 text-sm text-slate-300">
            <li>Use at least 8 characters.</li>
            <li>Include uppercase, lowercase, and a number.</li>
            <li>The link becomes unusable after one successful reset.</li>
        </ul>
    </section>
    <section class="rounded-3xl border border-white/10 bg-white/5 p-8">
    <h1 class="text-3xl font-black">Reset Password</h1>
    <?php if ($error): ?>
        <div class="notice notice-error mt-4"><?= e($error); ?></div>
    <?php endif; ?>
    <?php if (!$reset): ?>
        <div class="notice notice-error mt-4">This reset link is invalid or has expired.</div>
    <?php else: ?>
        <p class="mt-4 text-slate-400">Create a new password for <?= e($reset['email']); ?>.</p>
        <form method="post" class="mt-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <input type="hidden" name="reset" value="<?= (int) $resetId; ?>">
            <div>
                <label class="mb-2 block text-sm">New Password</label>
                <input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="password" type="password" placeholder="Create a new password" required>
            </div>
            <div>
                <label class="mb-2 block text-sm">Confirm Password</label>
                <input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="password_confirmation" type="password" placeholder="Repeat the new password" required>
            </div>
            <button class="w-full rounded-lg bg-cyan-400 px-5 py-3 font-semibold text-slate-950" type="submit">Save New Password</button>
        </form>
    <?php endif; ?>
    </section>
</div>
<?php render_footer(); ?>
