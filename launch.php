<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$destination = base_url();
if (admin_user()) {
    $destination = base_url('admin/dashboard.php');
} elseif (user()) {
    $destination = base_url('user/dashboard.php');
}

$iconSrc = base_url('assets/brand/icon.png') . '?v=' . GEMDATA_BRAND_VERSION;
$fallbackUrl = $destination;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Launching GemData</title>
    <meta name="description" content="GemData is preparing your secure VTU workspace.">
    <meta name="theme-color" content="#1B4DFF">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="GemData">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="GemData">
    <meta name="msapplication-TileColor" content="#1B4DFF">
    <meta name="msapplication-TileImage" content="/assets/brand/ms-tile-150.png">
    <meta http-equiv="refresh" content="4;url=<?= e($fallbackUrl); ?>">
    <link rel="manifest" href="<?= e(base_url('manifest.json')); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(base_url('assets/brand/favicon-32x32.png')); ?>?v=20260522a">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(base_url('assets/brand/apple-touch-icon.png')); ?>?v=20260522a">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:500,600,700,800,900&display=swap" rel="stylesheet">
    <style>
        :root {
            --gd-launch-bg: #f4f8fc;
            --gd-launch-surface: rgba(255, 255, 255, 0.88);
            --gd-launch-text: #0f172a;
            --gd-launch-muted: #64748b;
            --gd-launch-primary: #1B4DFF;
            --gd-launch-primary-soft: rgba(27, 77, 255, 0.12);
            --gd-launch-border: rgba(148, 163, 184, 0.20);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --gd-launch-bg: #0f172a;
                --gd-launch-surface: rgba(15, 23, 42, 0.86);
                --gd-launch-text: #f8fafc;
                --gd-launch-muted: #cbd5e1;
                --gd-launch-primary-soft: rgba(27, 77, 255, 0.20);
                --gd-launch-border: rgba(148, 163, 184, 0.22);
            }
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            margin: 0;
            min-height: 100dvh;
            display: grid;
            place-items: center;
            padding: max(1.5rem, env(safe-area-inset-top)) max(1.5rem, env(safe-area-inset-right)) max(1.5rem, env(safe-area-inset-bottom)) max(1.5rem, env(safe-area-inset-left));
            background:
                radial-gradient(circle at 50% 28%, var(--gd-launch-primary-soft), transparent 34%),
                var(--gd-launch-bg);
            color: var(--gd-launch-text);
            font-family: "Plus Jakarta Sans", Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .launch-screen {
            width: min(100%, 24rem);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            animation: launchFadeIn 520ms ease both;
        }

        .launch-logo {
            width: clamp(5.5rem, 20vw, 7.25rem);
            height: clamp(5.5rem, 20vw, 7.25rem);
            display: grid;
            place-items: center;
            border: 1px solid var(--gd-launch-border);
            border-radius: 1.8rem;
            background: var(--gd-launch-surface);
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.14);
            animation: launchLogoScale 1400ms ease-in-out infinite alternate;
        }

        .launch-logo img {
            width: 68%;
            height: 68%;
            object-fit: contain;
        }

        h1 {
            margin: 1.2rem 0 0;
            font-size: clamp(2rem, 8vw, 2.8rem);
            line-height: 1;
            font-weight: 900;
            letter-spacing: 0;
        }

        .launch-slogan {
            min-height: 1.5em;
            margin: 0.7rem 0 0;
            color: var(--gd-launch-muted);
            font-size: clamp(0.92rem, 3.8vw, 1.02rem);
            font-weight: 700;
        }

        .launch-dots {
            display: inline-flex;
            gap: 0.42rem;
            margin-top: 1.25rem;
        }

        .launch-dots span {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 999px;
            background: var(--gd-launch-primary);
            opacity: 0.35;
            animation: launchDotPulse 1000ms ease-in-out infinite;
        }

        .launch-dots span:nth-child(2) {
            animation-delay: 140ms;
        }

        .launch-dots span:nth-child(3) {
            animation-delay: 280ms;
        }

        @keyframes launchFadeIn {
            from {
                opacity: 0;
                transform: translateY(0.45rem);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes launchLogoScale {
            from {
                transform: scale(1);
            }
            to {
                transform: scale(1.03);
            }
        }

        @keyframes launchDotPulse {
            0%,
            80%,
            100% {
                opacity: 0.35;
                transform: translateY(0);
            }
            40% {
                opacity: 1;
                transform: translateY(-0.18rem);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .launch-screen,
            .launch-logo,
            .launch-dots span {
                animation: none !important;
            }
        }
    </style>
</head>
<body>
    <main class="launch-screen" role="status" aria-live="polite" data-continue-url="<?= e($destination); ?>">
        <div class="launch-logo" aria-hidden="true">
            <img src="<?= e($iconSrc); ?>" alt="">
        </div>
        <h1>GemData</h1>
        <p class="launch-slogan" data-launch-message>Fast &bull; Secure &bull; Reliable</p>
        <span class="launch-dots" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
        </span>
    </main>
    <script nonce="<?= e(csp_nonce()); ?>">
        (function () {
            var screen = document.querySelector('[data-continue-url]');
            if (!screen) {
                return;
            }
            var destination = screen.getAttribute('data-continue-url') || '/';
            var message = document.querySelector('[data-launch-message]');
            var startedAt = Date.now();
            var minimumDisplayMs = 900;
            window.setTimeout(function () {
                if (message) {
                    message.textContent = 'Preparing your dashboard...';
                }
            }, 4500);

            var continueToApp = function () {
                var elapsed = Date.now() - startedAt;
                var delay = Math.max(0, minimumDisplayMs - elapsed);
                window.setTimeout(function () {
                    window.location.replace(destination);
                }, delay);
            };

            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                window.requestAnimationFrame(function () {
                    window.requestAnimationFrame(continueToApp);
                });
            } else {
                document.addEventListener('DOMContentLoaded', function () {
                    window.requestAnimationFrame(function () {
                        window.requestAnimationFrame(continueToApp);
                    });
                }, { once: true });
            }
        }());
    </script>
</body>
</html>
