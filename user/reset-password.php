<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$service = app(\GemData\Classes\UserSecurityService::class);
$resetId = (int) ($_GET['reset'] ?? $_POST['reset'] ?? 0);
$token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? $_SESSION['password_reset_tokens'][$resetId] ?? ''));
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
<div class="gd-auth-wrap gd-auth-grid gd-auth-grid-two">
    <section class="gd-auth-panel">
        <p class="eyebrow">Password Reset</p>
        <h1 class="gd-auth-title">Create a new secure password.</h1>
        <ul class="mt-6 space-y-3 text-sm text-white/80">
            <li>Use at least 8 characters.</li>
            <li>Include uppercase, lowercase, and a number.</li>
            <li>The link becomes unusable after one successful reset.</li>
        </ul>
    </section>
    <section class="gd-auth-card">
    <h1 class="gd-auth-title">Reset Password</h1>
    <?php if ($error): ?>
        <div class="notice notice-error mt-4"><?= e($error); ?></div>
    <?php endif; ?>
    <?php if (!$reset): ?>
        <div class="notice notice-error mt-4">This reset link is invalid or has expired.</div>
    <?php else: ?>
        <p class="gd-auth-copy">Create a new password for <?= e($reset['email']); ?>.</p>
        <form method="post" class="mt-6 gd-form-grid" data-loading-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <input type="hidden" name="reset" value="<?= (int) $resetId; ?>">
            <input type="hidden" name="token" value="<?= e($token); ?>">
            <div class="gd-field">
                <label>New Password</label>
                <div class="password-field">
                    <input class="gd-input" name="password" type="password" placeholder="Create a new password" autocomplete="new-password" required>
                    <button class="password-toggle" type="button" data-password-toggle aria-label="Show password"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg></button>
                </div>
            </div>
            <div class="gd-field">
                <label>Confirm Password</label>
                <div class="password-field">
                    <input class="gd-input" name="password_confirmation" type="password" placeholder="Repeat the new password" autocomplete="new-password" required>
                    <button class="password-toggle" type="button" data-password-toggle aria-label="Show password"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg></button>
                </div>
            </div>
            <button class="gd-auth-button w-full" type="submit" data-loading-label="Saving password...">Save New Password</button>
        </form>
    <?php endif; ?>
    </section>
</div>
<?php render_footer(); ?>
