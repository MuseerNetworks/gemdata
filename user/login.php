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
    <title>User Login | <?= e(config('app.name')); ?></title>
    <meta name="theme-color" content="#1B4DFF">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="GemData">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="GemData">
    <meta name="msapplication-TileColor" content="#1B4DFF">
    <meta name="msapplication-TileImage" content="/assets/brand/ms-tile-150.png">
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
<body class="gd-login-page" data-app-section="auth" data-page-key="login">
    <a class="gd-login-back" href="<?= e(base_url('index.php')); ?>" aria-label="Back to home">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        <span>Back to Home</span>
    </a>

    <main class="gd-login-shell">
        <section class="gd-auth-card gd-login-card" aria-labelledby="login-title">
            <div class="gd-login-brand">
                <span class="gd-login-logo"><?= gemdata_logo('icon', '38', 'rounded', 'GemData'); ?></span>
                <span><?= e(config('app.name')); ?></span>
            </div>

            <h1 id="login-title" class="gd-login-title">Welcome Back</h1>
            <p class="gd-login-subtitle">Enter your details to access your GemData wallet.</p>

            <?php if ($message = flash('success')): ?>
                <div class="notice notice-success gd-login-notice"><?= e($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error gd-login-notice"><?= e($error); ?></div>
            <?php endif; ?>

            <form method="post" class="gd-form-grid gd-login-form" data-loading-form>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <div class="gd-field">
                    <label>Email Address</label>
                    <input class="gd-input" name="email" type="email" value="<?= e($email); ?>" placeholder="you@example.com" autocomplete="email" required>
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

                <div class="gd-login-forgot">
                    <a href="<?= e(base_url('user/forgot-password.php')); ?>">Forgot Password?</a>
                </div>

                <button class="gd-auth-button gd-login-submit" type="submit" data-loading-label="Signing in...">Sign In</button>
            </form>

            <div class="gd-login-footer">
                Don't have an account?
                <a href="<?= e(base_url('user/register.php')); ?>">Create Account</a>
            </div>
        </section>
    </main>
</body>
</html>
