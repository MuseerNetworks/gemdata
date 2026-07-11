<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (admin_user()) {
    redirect(base_url('admin/dashboard.php'));
}

$adminCount = (int) (db()->first('SELECT COUNT(*) AS total FROM admins')['total'] ?? 0);
$error = null;
$success = flash('success');
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
if (is_post()) {
    verify_csrf();
    if (auth()->loginAdmin($email, $_POST['password'] ?? '')) {
        if (auth()->pendingTwoFactorAdmin()) {
            redirect(base_url('admin/verify-2fa.php'));
        }
        $currentAdmin = auth()->admin();
        if ($currentAdmin && (int) ($currentAdmin['force_password_change'] ?? 0) === 1) {
            redirect(base_url('admin/change-password.php'));
        }
        redirect(base_url('admin/dashboard.php'));
    }
    $error = 'Invalid admin credentials.';
}

$siteCssPath = dirname(__DIR__) . '/assets/css/site.css';
$siteJsPath = dirname(__DIR__) . '/assets/js/app.js';
$siteCssVersion = is_file($siteCssPath) ? (string) filemtime($siteCssPath) : '1';
$siteJsVersion = is_file($siteJsPath) ? (string) filemtime($siteJsPath) : '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | <?= e(config('app.name')); ?></title>
    <meta name="theme-color" content="#1B4DFF">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="GemData">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="GemData">
    <link rel="manifest" href="<?= e(base_url('manifest.json')); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(base_url('assets/brand/favicon-32x32.png')); ?>?v=20260522a">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800,900&display=swap" rel="stylesheet">
    <script nonce="<?= e(csp_nonce()); ?>">
        (function () {
            var theme = localStorage.getItem('gemdata-theme') || 'light-fintech';
            document.documentElement.setAttribute('data-theme', theme);
        }());
    </script>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/site.css') . '?v=' . $siteCssVersion); ?>">
    <script nonce="<?= e(csp_nonce()); ?>" defer src="<?= e(base_url('assets/js/app.js') . '?v=' . $siteJsVersion); ?>"></script>
</head>
<body class="gd-login-page gd-admin-login-page" data-app-section="auth" data-page-key="admin-login">
    <?php render_gemdata_splash(); ?>
    <a class="gd-login-back" href="<?= e(base_url('index.php')); ?>" aria-label="Back to home">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        <span>Back to Home</span>
    </a>

    <main class="gd-login-shell gd-admin-login-shell">
        <section class="gd-auth-card gd-login-card gd-admin-login-card" aria-labelledby="admin-login-title">
            <div class="gd-login-brand">
                <span class="gd-login-logo"><?= gemdata_logo('icon', '38', 'rounded', 'GemData'); ?></span>
                <span><?= e(config('app.name')); ?></span>
            </div>

            <p class="gd-auth-eyebrow">Operations Center</p>
            <h1 id="admin-login-title" class="gd-login-title">Admin Login</h1>
            <p class="gd-login-subtitle">Sign in to manage providers, transactions, wallets, and platform operations.</p>

            <?php if ($success): ?>
                <div class="notice notice-success gd-login-notice"><?= e($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error gd-login-notice"><?= e($error); ?></div>
            <?php endif; ?>

            <form method="post" class="gd-form-grid gd-login-form" data-loading-form>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <div class="gd-field">
                    <label>Email Address</label>
                    <input class="gd-input" name="email" type="email" value="<?= e($email); ?>" placeholder="admin@example.com" autocomplete="email" required>
                </div>
                <div class="gd-field">
                    <label>Password</label>
                    <div class="password-field">
                        <input class="gd-input" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required>
                        <button class="password-toggle" type="button" data-password-toggle aria-label="Show password">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </button>
                    </div>
                </div>

                <button class="gd-auth-button gd-login-submit" type="submit" data-loading-label="Entering...">Enter Admin Panel</button>
            </form>

            <?php if ($adminCount === 0): ?>
                <div class="gd-login-footer">
                    No admin account exists yet.
                    <a href="<?= e(base_url('admin/register.php')); ?>">Create the first admin account</a>
                </div>
            <?php else: ?>
                <div class="gd-login-footer">
                    User access?
                    <a href="<?= e(base_url('user/login.php')); ?>">Go to User Login</a>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
