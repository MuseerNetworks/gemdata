<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$pendingAdmin = auth()->pendingTwoFactorAdmin();
if (!$pendingAdmin) {
    redirect(base_url('admin/login.php'));
}

$error = null;
$secret = trim((string) ($pendingAdmin['two_factor_secret'] ?? ''));
if ($secret === '') {
    $secret = base32_encode_secret(random_bytes(20));
    db()->execute('UPDATE admins SET two_factor_secret = :secret WHERE id = :id', [
        'secret' => $secret,
        'id' => (int) $pendingAdmin['id'],
    ]);
}

if (is_post()) {
    verify_csrf();
    if (verify_totp_code($secret, (string) ($_POST['code'] ?? ''))) {
        auth()->completeAdminTwoFactor((int) $pendingAdmin['id']);
        redirect(base_url('admin/dashboard.php'));
    }
    $error = 'Invalid two-factor code.';
}

render_header('Admin Two-Factor Verification');
?>
<div class="mx-auto max-w-xl rounded-2xl border border-white/10 bg-white/5 p-8">
    <h1 class="text-3xl font-black">Two-factor verification</h1>
    <p class="mt-3 text-slate-300">Enter the 6-digit code from your authenticator app.</p>
    <?php if ((int) ($pendingAdmin['two_factor_enabled'] ?? 0) !== 1): ?>
        <div class="notice notice-warning mt-6">
            Add this setup key to your authenticator app before continuing:
            <strong class="font-mono"><?= e($secret); ?></strong>
        </div>
    <?php endif; ?>
    <?php if ($error): ?><div class="notice notice-error mt-6"><?= e($error); ?></div><?php endif; ?>
    <form method="post" class="mt-6 grid gap-4">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <label class="grid gap-2">
            <span class="text-sm text-slate-300">Authentication code</span>
            <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3 text-lg tracking-widest" name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required>
        </label>
        <button class="rounded-lg bg-cyan-400 px-5 py-3 font-semibold text-slate-950" type="submit">Verify and continue</button>
    </form>
</div>
<?php render_footer(); ?>
