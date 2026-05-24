<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (user()) {
    redirect(base_url('user/dashboard.php'));
}

$service = app(\GemData\Classes\UserSecurityService::class);
$mailService = app(\GemData\Classes\MailService::class);
$message = null;

if (is_post()) {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $reset = $service->createSelfServicePasswordResetByEmail($email);
        if ($reset) {
            $resetUrl = absolute_url('user/reset-password.php?' . http_build_query([
                'reset' => (int) $reset['id'],
                'token' => (string) $reset['token'],
            ]));
            $mailService->sendPasswordReset((string) $reset['email'], $resetUrl, [
                'source' => 'self_service',
                'reset_id' => (int) $reset['id'],
            ]);
        }
    }
    $message = 'If an active account matches that email, a password reset link will be sent through the configured delivery channel.';
}

render_header('Forgot Password');
?>
<div class="gd-auth-wrap gd-auth-grid gd-auth-grid-two">
    <section class="gd-auth-panel">
        <p class="eyebrow">Account Recovery</p>
        <h1 class="gd-auth-title">Reset your password safely.</h1>
        <p class="gd-auth-copy max-w-xl">Recovery links are single-use and time-limited. Use the same email tied to your GemData account.</p>
    </section>
    <section class="gd-auth-card">
    <h1 class="gd-auth-title">Forgot Password</h1>
    <p class="gd-auth-copy">Enter your account email to start password recovery.</p>
    <?php if ($message): ?>
        <div class="notice notice-success mt-4"><?= e($message); ?></div>
    <?php endif; ?>
    <form method="post" class="mt-6 gd-form-grid" data-loading-form>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <div class="gd-field">
            <label>Email</label>
            <input class="gd-input" name="email" type="email" placeholder="you@example.com" autocomplete="email" required>
        </div>
        <button class="gd-auth-button w-full" type="submit" data-loading-label="Sending reset link...">Generate Reset Link</button>
    </form>
    <p class="mt-5 text-sm text-gem-muted">
        Remembered it? <a class="text-gem-blue font-bold" href="<?= e(base_url('user/login.php')); ?>">Back to sign in</a>
    </p>
    </section>
</div>
<?php render_footer(); ?>
