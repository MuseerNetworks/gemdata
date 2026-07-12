<?php
/**
 * GemData Logo System — v2 (Real PNG, cache-busted)
 * Uses the REAL uploaded brand image. Version string forces browser cache refresh.
 */

// Increment this when brand assets change — busts all browser caches
define('GEMDATA_BRAND_VERSION', '20260522a');

function gemdata_logo(
    string $variant = 'full',
    string $theme   = 'auto',
    string $class   = '',
    string $alt     = 'GemData'
): string {
    $v       = GEMDATA_BRAND_VERSION;
    $iconSrc = base_url('assets/brand/icon.png') . '?v=' . $v;
    $cls     = $class ? ' ' . htmlspecialchars($class, ENT_QUOTES) : '';

    if ($variant === 'icon') {
        return '<img src="' . e($iconSrc) . '"'
             . ' alt="' . e($alt) . '"'
             . ' class="gemdata-brand-icon' . $cls . '"'
             . ' width="40" height="40"'
             . ' loading="eager"'
             . ' decoding="async"'
             . ' onerror="this.style.display=\'none\'"'
             . '>';
    }

    if ($variant === 'wordmark') {
        return '<span class="gemdata-wordmark' . $cls . '">'
             . '<strong>GemData</strong>'
             . '<small>Trust In Data</small>'
             . '</span>';
    }

    // Full brand block: icon image + text
    return '<span class="gemdata-logo-full' . $cls . '">'
         . '<img src="' . e($iconSrc) . '"'
         . '     alt="' . e($alt) . '"'
         . '     class="gemdata-brand-icon"'
         . '     width="40" height="40"'
         . '     loading="eager"'
         . '     decoding="async"'
         . '     onerror="this.style.display=\'none\'">'
         . '<span class="gemdata-logo-text">'
         . '<span class="gemdata-logo-name">GemData</span>'
         . '<span class="gemdata-logo-tag">Trust In Data</span>'
         . '</span>'
         . '</span>';
}

function render_gemdata_splash(): void
{
    ?>
    <style>
        .gd-app-splash{position:fixed;inset:0;z-index:9999;display:grid;place-items:center;min-height:100dvh;padding:clamp(1.5rem,4vw,3rem);background:radial-gradient(circle at 50% 28%,color-mix(in srgb,var(--gd-primary-soft,rgba(27,77,255,.10)) 62%,transparent),transparent 34%),var(--gd-bg,#f4f8fc);color:var(--gd-text,#0f172a);opacity:1;pointer-events:auto;transition:opacity 200ms ease}.gd-app-splash.is-exiting{opacity:0;pointer-events:none}.gd-app-splash-inner{display:flex;width:min(100%,22rem);flex-direction:column;align-items:center;justify-content:center;text-align:center;animation:gdSplashFadeIn 520ms ease both}.gd-app-splash-logo{display:grid;place-items:center;width:clamp(5.25rem,18vw,7rem);height:clamp(5.25rem,18vw,7rem);border:1px solid var(--gd-border,rgba(148,163,184,.18));border-radius:1.75rem;background:color-mix(in srgb,var(--gd-surface-elevated,#fff) 92%,transparent);box-shadow:0 22px 56px rgba(15,23,42,.14);animation:gdSplashLogoScale 1400ms ease-in-out infinite alternate}.gd-app-splash-logo-img{width:68%;height:68%;object-fit:contain}.gd-app-splash-name{margin:1.2rem 0 0;color:var(--gd-text,#0f172a);font-family:"Plus Jakarta Sans",Inter,system-ui,sans-serif;font-size:clamp(1.9rem,7vw,2.7rem);font-weight:900;line-height:1}.gd-app-splash-slogan{min-height:1.5em;margin:.7rem 0 0;color:var(--gd-text-muted,#64748b);font-size:clamp(.9rem,3.6vw,1rem);font-weight:700;letter-spacing:0}.gd-app-splash-dots{display:inline-flex;align-items:center;justify-content:center;gap:.42rem;margin-top:1.25rem}.gd-app-splash-dots span{width:.5rem;height:.5rem;border-radius:999px;background:var(--gd-primary-strong,#1B4DFF);opacity:.35;animation:gdSplashDotPulse 1000ms ease-in-out infinite}.gd-app-splash-dots span:nth-child(2){animation-delay:140ms}.gd-app-splash-dots span:nth-child(3){animation-delay:280ms}@keyframes gdSplashFadeIn{from{opacity:0;transform:translateY(.45rem)}to{opacity:1;transform:translateY(0)}}@keyframes gdSplashLogoScale{from{transform:scale(1)}to{transform:scale(1.03)}}@keyframes gdSplashDotPulse{0%,80%,100%{opacity:.35;transform:translateY(0)}40%{opacity:1;transform:translateY(-.18rem)}}@media (prefers-reduced-motion:reduce){.gd-app-splash,.gd-app-splash-inner,.gd-app-splash-logo,.gd-app-splash-dots span{animation:none!important;transition-duration:1ms!important}}
    </style>
    <div class="gd-app-splash" data-app-splash role="status" aria-live="polite" aria-label="GemData is loading" style="display:none">
        <div class="gd-app-splash-inner">
            <div class="gd-app-splash-logo" aria-hidden="true">
                <?= gemdata_logo('icon', 'auto', 'gd-app-splash-logo-img', ''); ?>
            </div>
            <p class="gd-app-splash-name">GemData</p>
            <p class="gd-app-splash-slogan" data-app-splash-message>Fast • Secure • Reliable</p>
            <span class="gd-app-splash-dots" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
            </span>
        </div>
    </div>
    <script nonce="<?= e(csp_nonce()); ?>">
        (function () {
            var splash = document.querySelector('[data-app-splash]');
            if (!splash || splash.dataset.fallbackBound === 'true') {
                return;
            }
            splash.dataset.fallbackBound = 'true';
            var isStandalone = false;
            try {
                isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            } catch (error) {
                isStandalone = window.navigator.standalone === true;
            }
            var storageKey = 'gemdata-pwa-splash-seen';
            var hasSeenSplash = false;
            try {
                hasSeenSplash = window.sessionStorage.getItem(storageKey) === '1';
            } catch (error) {
                hasSeenSplash = false;
            }
            if (!isStandalone || hasSeenSplash) {
                splash.remove();
                return;
            }
            try {
                window.sessionStorage.setItem(storageKey, '1');
            } catch (error) {
            }
            splash.dataset.splashActive = 'true';
            splash.style.display = '';
            var message = splash.querySelector('[data-app-splash-message]');
            var slowTimer = window.setTimeout(function () {
                if (message && !splash.classList.contains('is-exiting')) {
                    message.textContent = 'Preparing your dashboard…';
                }
            }, 4500);
            var done = false;
            var hideSplash = function () {
                if (done || !splash.isConnected) {
                    return;
                }
                done = true;
                window.clearTimeout(slowTimer);
                window.requestAnimationFrame(function () {
                    window.requestAnimationFrame(function () {
                        splash.classList.add('is-exiting');
                        splash.setAttribute('aria-hidden', 'true');
                        window.setTimeout(function () {
                            if (splash && splash.isConnected) {
                                splash.remove();
                            }
                        }, 220);
                    });
                });
            };
            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                hideSplash();
            } else {
                document.addEventListener('DOMContentLoaded', hideSplash, { once: true });
            }
        }());
    </script>
    <?php
}
