<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$pendingAdmin = auth()->pendingTwoFactorAdmin();
if (!$pendingAdmin) { redirect(base_url('admin/login.php')); }
$error = null;
$isEnrollment = (int) ($pendingAdmin['two_factor_enabled'] ?? 0) !== 1;
$secret = trim((string) ($pendingAdmin['two_factor_secret'] ?? ''));
if ($secret === '') {
    $secret = base32_encode_secret(random_bytes(20));
    db()->execute('UPDATE admins SET two_factor_secret = :secret WHERE id = :id', ['secret' => $secret, 'id' => (int) $pendingAdmin['id']]);
}
if (is_post()) {
    verify_csrf();
    $submittedCode = (string) ($_POST['code'] ?? '');
    $verified = verify_totp_code($secret, $submittedCode);
    $usedRecoveryCode = !$verified && !$isEnrollment && auth()->verifyAdminRecoveryCode((int) $pendingAdmin['id'], $submittedCode);
    if ($verified) {
        auth()->completeAdminTwoFactor((int) $pendingAdmin['id']);
        redirect(base_url($isEnrollment ? 'admin/recovery-codes.php' : 'admin/dashboard.php'));
    }
    if ($usedRecoveryCode) {
        $_SESSION['admin_2fa_attempts'] = 0;
        redirect(base_url('admin/verify-2fa.php'));
    }
    $attempts = (int) ($_SESSION['admin_2fa_attempts'] ?? 0) + 1;
    $_SESSION['admin_2fa_attempts'] = $attempts;
    if ($attempts >= 5) {
        auth()->clearPendingAdminTwoFactor('five_invalid_codes');
        flash('success', 'Too many invalid verification attempts. Sign in with your password and try again.');
        redirect(base_url('admin/login.php'));
    }
    $error = 'Invalid authentication or recovery code. ' . (5 - $attempts) . ' attempt(s) remaining.';
}
$provisioningUri = totp_provisioning_uri((string) $pendingAdmin['email'], $secret);
render_header('Admin Two-Factor Verification');
?>
<?php if ($isEnrollment): ?><script nonce="<?= e(csp_nonce()); ?>" src="<?= e(base_url('assets/js/vendor/qrcode-generator.js')); ?>"></script><?php endif; ?>
<div class="mx-auto max-w-xl rounded-2xl border border-white/10 bg-white/5 p-8">
    <h1 class="text-3xl font-black">Two-factor verification</h1>
    <?php if ($isEnrollment): ?>
        <p class="mt-3 text-slate-300">Set up your authenticator before continuing. Scan this QR code with Google Authenticator, Microsoft Authenticator, or another TOTP app.</p>
        <div id="admin-2fa-qr" class="mx-auto mt-6 grid place-items-center rounded-xl bg-white p-3" style="width: 256px; height: 256px;" data-qr-value="<?= e($provisioningUri); ?>" aria-label="Authenticator setup QR code"></div>
        <p class="mt-5 text-sm text-slate-300">If you cannot scan it, add a <strong>time-based</strong> account using this setup key:</p>
        <div class="mt-2 flex items-center gap-2 rounded-lg border border-white/10 bg-slate-900 p-3"><code class="min-w-0 flex-1 break-all text-sm text-cyan-300"><?= e($secret); ?></code><button class="secondary-action" type="button" data-copy-value="<?= e($secret); ?>">Copy</button></div>
    <?php else: ?>
        <p class="mt-3 text-slate-300">Enter the current 6-digit code from your authenticator app. You may also use one unused recovery code.</p>
    <?php endif; ?>
    <?php if (!empty($_SESSION['admin_2fa_reenrollment_required'])): ?><div class="notice notice-warning mt-6">A recovery code was used. You must enroll a new authenticator to continue.</div><?php endif; ?>
    <?php if ($error): ?><div class="notice notice-error mt-6"><?= e($error); ?></div><?php endif; ?>
    <form method="post" class="mt-6 grid gap-4" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <label class="grid gap-2"><span class="text-sm text-slate-300"><?= $isEnrollment ? 'Authentication code' : 'Authentication or recovery code'; ?></span><input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3 text-lg tracking-widest" name="code" inputmode="text" maxlength="16" autocomplete="one-time-code" required autofocus></label>
        <button class="rounded-lg bg-cyan-400 px-5 py-3 font-semibold text-slate-950" type="submit"><?= $isEnrollment ? 'Verify and save recovery codes' : 'Verify and continue'; ?></button>
    </form>
</div>
<?php if ($isEnrollment): ?><script nonce="<?= e(csp_nonce()); ?>">(function(){var t=document.getElementById('admin-2fa-qr');if(!t||typeof qrcode!=='function')return;var q=qrcode(0,'M');q.addData(t.dataset.qrValue);q.make();t.innerHTML=q.createSvgTag({scalable:true,margin:0,cellSize:6});var s=t.querySelector('svg');if(s){s.style.width='100%';s.style.height='100%';s.setAttribute('preserveAspectRatio','xMidYMid meet');}}());</script><?php endif; ?>
<?php render_footer(); ?>
