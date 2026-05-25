<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (admin_user()) {
    redirect(base_url('admin/dashboard.php'));
}

if (user()) {
    redirect(base_url('user/dashboard.php'));
}

$siteCssPath = __DIR__ . '/assets/css/site.css';
$siteJsPath = __DIR__ . '/assets/js/app.js';
$siteCssVersion = is_file($siteCssPath) ? (string) filemtime($siteCssPath) : (string) time();
$siteJsVersion = is_file($siteJsPath) ? (string) filemtime($siteJsPath) : (string) time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GemData | Fast, Secure &amp; Affordable VTU Platform</title>
    <meta name="description" content="GemData — Nigeria's fastest VTU platform for airtime, data bundles, electricity bills, cable TV subscriptions, and reseller operations.">
    <link rel="canonical" href="<?= e(rtrim(app_origin(), '/') . '/'); ?>">
    <!-- Favicon — PNG first -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(base_url('assets/brand/favicon-32x32.png')); ?>?v=20260522a">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= e(base_url('assets/brand/favicon-16x16.png')); ?>?v=20260522a">
    <link rel="icon" type="image/png" sizes="48x48" href="<?= e(base_url('assets/brand/favicon-48x48.png')); ?>?v=20260522a">
    <link rel="shortcut icon" type="image/png" href="<?= e(base_url('assets/brand/favicon-32x32.png')); ?>?v=20260522a">
    <!-- Apple / iOS -->
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(base_url('assets/brand/apple-touch-icon.png')); ?>?v=20260522a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="GemData">
    <!-- Android / Chrome / PWA -->
    <link rel="manifest" href="<?= e(base_url('manifest.json')); ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="GemData">
    <meta name="theme-color" content="#1d4ed8">
    <!-- Windows -->
    <meta name="msapplication-TileColor" content="#1d4ed8">
    <meta name="msapplication-TileImage" content="/assets/brand/ms-tile-150.png">
    <meta name="msapplication-config" content="/browserconfig.xml">
    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="GemData">
    <meta property="og:title" content="GemData | Fast, Secure &amp; Affordable VTU Platform">
    <meta property="og:description" content="Nigeria's fastest VTU platform for airtime, data, electricity bills, and reseller operations.">
    <meta property="og:image" content="<?= e(rtrim(app_origin(),'/')); ?>/assets/brand/og-image.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="<?= e(rtrim(app_origin(), '/') . '/'); ?>">
    <!-- Twitter / X -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@GemDataNG">
    <meta name="twitter:title" content="GemData — Trust In Data">
    <meta name="twitter:description" content="Fast, secure VTU for airtime, data, electricity, and reseller operations in Nigeria.">
    <meta name="twitter:image" content="<?= e(rtrim(app_origin(),'/')); ?>/assets/brand/og-image.png">
    <!-- Fonts -->
    <link rel="dns-prefetch" href="//cdn.tailwindcss.com">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900&display=swap" rel="stylesheet">
    <script nonce="<?= e(csp_nonce()); ?>">
        (function () {
            var theme = localStorage.getItem('gemdata-theme') || 'light-fintech';
            document.documentElement.setAttribute('data-theme', theme);
        }());
    </script>
    <script nonce="<?= e(csp_nonce()); ?>" src="https://cdn.tailwindcss.com"></script>
    <script nonce="<?= e(csp_nonce()); ?>">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        gem: {
                            50: '#EEF2FF',
                            100: '#DBE4FF',
                            500: '#1B4DFF',
                            600: '#1B4DFF',
                            700: '#1238CC',
                            900: '#0F172A',
                            teal: '#00C6AE',
                            border: '#E2E8F0',
                        }
                    },
                    boxShadow: {
                        soft: '0 18px 44px rgba(15, 23, 42, 0.08)',
                        float: '0 4px 24px rgba(27, 77, 255, 0.16)',
                    },
                    borderRadius: {
                        '4xl': '2rem',
                    }
                }
            }
        };
    </script>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/site.css') . '?v=' . $siteCssVersion); ?>">
    <script nonce="<?= e(csp_nonce()); ?>" defer src="<?= e(base_url('assets/js/app.js') . '?v=' . $siteJsVersion); ?>"></script>
    <script nonce="<?= e(csp_nonce()); ?>">
        document.addEventListener('DOMContentLoaded', function () {
            var toggle = document.querySelector('[data-public-nav-toggle]');
            var panel = document.querySelector('[data-public-nav-panel]');
            if (!toggle || !panel) {
                return;
            }
            toggle.addEventListener('click', function () {
                panel.classList.toggle('hidden');
            });
        });
    </script>
    <script nonce="<?= e(csp_nonce()); ?>">
        // Service Worker registration for landing page PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
                    .catch(function (err) { console.warn('SW registration failed', err); });
            });
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-900 antialiased" data-app-section="guest" data-page-key="landing">
    <div class="gd-landing-shell min-h-screen bg-[radial-gradient(circle_at_top_left,_rgba(27,77,255,0.10),_transparent_28%),radial-gradient(circle_at_right,_rgba(0,198,174,0.10),_transparent_24%),linear-gradient(180deg,#F8FAFC_0%,#F8FBFF_52%,#EEF2FF_100%)]">
        <header class="sticky top-0 z-50 border-b border-slate-200/70 bg-white/80 backdrop-blur-xl">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <a href="<?= e(base_url()); ?>" class="flex items-center gap-3 lp-brand-link">
                    <span class="lp-brand-icon" style="display:flex;align-items:center;width:2.75rem;height:2.75rem;flex-shrink:0">
                        <?= gemdata_logo('icon', 'light', 'lp-logo-icon', 'GemData'); ?>
                    </span>
                    <span>
                        <span class="block text-lg font-black tracking-tight" style="color:#1d4ed8">GemData</span>
                        <span class="block text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Trust In Data</span>
                    </span>
                </a>
                <nav class="hidden items-center gap-8 text-sm font-semibold text-slate-600 md:flex">
                    <a class="transition hover:text-slate-950" href="#home">Home</a>
                    <a class="transition hover:text-slate-950" href="#services">Services</a>
                    <a class="transition hover:text-slate-950" href="#about">About</a>
                    <a class="transition hover:text-slate-950" href="<?= e(base_url('docs/api.php')); ?>">API</a>
                    <a class="transition hover:text-slate-950" href="<?= e(base_url('user/login.php')); ?>">Login</a>
                    <a class="rounded-full bg-gem-600 px-5 py-3 text-white shadow-float transition hover:bg-gem-700" href="<?= e(base_url('user/register.php')); ?>">Register</a>
                </nav>
                <button class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-900 shadow-soft md:hidden" type="button" data-public-nav-toggle aria-label="Toggle navigation">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
            <div class="hidden border-t border-slate-200/70 bg-white/90 px-4 py-4 md:hidden" data-public-nav-panel>
                <nav class="grid gap-3 text-sm font-semibold text-slate-700">
                    <a class="rounded-2xl border border-slate-200 bg-white px-4 py-3" href="#home">Home</a>
                    <a class="rounded-2xl border border-slate-200 bg-white px-4 py-3" href="#services">Services</a>
                    <a class="rounded-2xl border border-slate-200 bg-white px-4 py-3" href="#about">About</a>
                    <a class="rounded-2xl border border-slate-200 bg-white px-4 py-3" href="<?= e(base_url('docs/api.php')); ?>">API</a>
                    <a class="rounded-2xl border border-slate-200 bg-white px-4 py-3" href="<?= e(base_url('user/login.php')); ?>">Login</a>
                    <a class="rounded-2xl bg-gem-600 px-4 py-3 text-white" href="<?= e(base_url('user/register.php')); ?>">Register</a>
                </nav>
            </div>
        </header>

        <main>
            <section id="home" class="gd-landing-section gd-landing-hero mx-auto max-w-7xl px-4 pb-20 pt-12 sm:px-6 lg:px-8 lg:pb-28 lg:pt-20">
                <div class="grid items-center gap-12 lg:grid-cols-[1.05fr,0.95fr]">
                    <div>
                        <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-4 py-2 text-xs font-bold uppercase tracking-[0.22em] text-gem-700">Nigeria-ready VTU platform</span>
                        <h1 class="gd-landing-hero-title mt-6 max-w-3xl font-black tracking-tight text-slate-950">Fast, secure, and repeat-friendly VTU for everyday users and resellers.</h1>
                        <p class="gd-landing-hero-copy mt-6 max-w-2xl text-lg leading-8 text-slate-600">
                            Sell SME data, airtime, electricity, cable TV, exam PINs, and developer API services from one polished GemData Workspace built to feel trustworthy at every step.
                        </p>
                        <div class="gd-landing-hero-actions mt-8 flex flex-wrap gap-4">
                            <a class="gd-landing-hero-primary rounded-full bg-gem-600 px-6 py-3.5 text-sm font-bold text-white shadow-float transition hover:bg-gem-700" href="<?= e(base_url('user/register.php')); ?>">Get Started</a>
                            <a class="gd-landing-hero-secondary rounded-full border border-slate-300 bg-white px-6 py-3.5 text-sm font-bold text-slate-900 shadow-soft transition hover:border-slate-400" href="<?= e(base_url('user/login.php')); ?>">Login</a>
                            <a class="gd-landing-hero-secondary rounded-full border border-gem-100 bg-gem-50 px-6 py-3.5 text-sm font-bold text-gem-700 transition hover:bg-white" href="#download">Download App</a>
                        </div>
                        <div class="mt-10 grid gap-4 sm:grid-cols-3">
                            <div class="rounded-3xl border border-white/70 bg-white/80 p-5 shadow-soft">
                                <p class="text-2xl font-black text-slate-950">MTN, Airtel, Glo, 9mobile</p>
                                <p class="mt-2 text-sm text-slate-600">Network-ready airtime and data delivery.</p>
                            </div>
                            <div class="rounded-3xl border border-white/70 bg-white/80 p-5 shadow-soft">
                                <p class="text-2xl font-black text-slate-950">Tracked wallet funding</p>
                                <p class="mt-2 text-sm text-slate-600">Requests are verified before wallet credit to build confidence.</p>
                            </div>
                            <div class="rounded-3xl border border-white/70 bg-white/80 p-5 shadow-soft">
                                <p class="text-2xl font-black text-slate-950">API for resellers</p>
                                <p class="mt-2 text-sm text-slate-600">Authenticated JSON endpoints for business scale.</p>
                            </div>
                        </div>
                        <div class="mt-8 flex flex-wrap items-center gap-3 text-sm font-semibold text-slate-600">
                            <span class="rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-emerald-700">Wallet-safe funding flow</span>
                            <span class="rounded-full border border-gem-100 bg-gem-50 px-4 py-2 text-gem-700">Real-time transaction visibility</span>
                            <span class="rounded-full border border-indigo-200 bg-indigo-50 px-4 py-2 text-gem-700">Optimized for Nigerian VTU buyers</span>
                        </div>
                    </div>

                    <div class="relative">
                        <div class="absolute -left-6 top-8 hidden h-28 w-28 rounded-full bg-gem-100/50 blur-3xl lg:block"></div>
                        <div class="absolute -right-8 bottom-6 hidden h-32 w-32 rounded-full bg-indigo-300/30 blur-3xl lg:block"></div>
                        <div class="relative mx-auto max-w-md rounded-[2rem] border border-white/70 bg-white/85 p-5 shadow-soft backdrop-blur">
                            <div class="gd-landing-device rounded-[1.7rem] bg-slate-950 p-5 text-white shadow-2xl">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.22em] text-blue-200">GemData Workspace</p>
                                        <h2 class="mt-2 text-2xl font-black">NGN 12,480.00</h2>
                                    </div>
                                    <span class="rounded-full bg-emerald-400/15 px-3 py-1 text-xs font-bold text-emerald-300">All services active</span>
                                </div>
                                <div class="mt-6 grid grid-cols-3 gap-3">
                                    <div class="rounded-2xl bg-white/5 p-3">
                                        <p class="text-xs text-slate-400">Today</p>
                                        <p class="mt-2 text-lg font-bold">24 txns</p>
                                    </div>
                                    <div class="rounded-2xl bg-white/5 p-3">
                                        <p class="text-xs text-slate-400">Commission</p>
                                        <p class="mt-2 text-lg font-bold">NGN 820</p>
                                    </div>
                                    <div class="rounded-2xl bg-white/5 p-3">
                                        <p class="text-xs text-slate-400">API</p>
                                        <p class="mt-2 text-lg font-bold">Ready</p>
                                    </div>
                                </div>
                                <div class="gd-landing-quick-card mt-6 rounded-3xl p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-bold">Quick Purchase</p>
                                            <p class="mt-1 text-xs text-slate-500">MTN 1GB SME</p>
                                        </div>
                                        <span class="gd-landing-quick-price rounded-full px-3 py-1 text-xs font-bold">NGN 620</span>
                                    </div>
                                    <div class="mt-4 grid grid-cols-2 gap-3">
                                        <button class="gd-landing-quick-button gd-landing-quick-button-primary rounded-2xl px-4 py-3 text-left text-sm font-semibold">Buy Airtime</button>
                                        <button class="gd-landing-quick-button gd-landing-quick-button-secondary rounded-2xl px-4 py-3 text-left text-sm font-semibold">Fund Wallet</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="services" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-14">
                <div class="mb-10 text-center">
                    <span class="text-sm font-bold uppercase tracking-[0.22em] text-gem-700">Services</span>
                    <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Everything you need in one place</h2>
                </div>
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-4xl border border-white/80 bg-white p-6 shadow-soft">
                        <div class="flex h-14 w-14 items-center justify-center rounded-3xl bg-blue-50 text-sky-700">
                            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 9a8 8 0 0 1 16 0"/><path d="M7 12a5 5 0 0 1 10 0"/><path d="M10 15a2 2 0 0 1 4 0"/><circle cx="12" cy="18" r="1" fill="currentColor" stroke="none"/></svg>
                        </div>
                        <h3 class="mt-5 text-xl font-black text-slate-950">Data Bundles</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-600">SME and direct plans for MTN, Airtel, Glo, and 9mobile with fast delivery.</p>
                    </article>
                    <article class="rounded-4xl border border-white/80 bg-white p-6 shadow-soft">
                        <div class="flex h-14 w-14 items-center justify-center rounded-3xl bg-rose-50 text-rose-700">
                            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="7" y="2.5" width="10" height="19" rx="2"/><path d="M10 5.5h4"/><path d="M10.5 18h3"/></svg>
                        </div>
                        <h3 class="mt-5 text-xl font-black text-slate-950">Airtime VTU</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-600">Top up any Nigerian line instantly with a wallet-backed purchase flow.</p>
                    </article>
                    <article class="rounded-4xl border border-white/80 bg-white p-6 shadow-soft">
                        <div class="flex h-14 w-14 items-center justify-center rounded-3xl bg-amber-50 text-amber-700">
                            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 2 5 14h6l-1 8 8-12h-6z"/></svg>
                        </div>
                        <h3 class="mt-5 text-xl font-black text-slate-950">Bills Payment</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-600">Handle electricity token payments and cable TV renewals in one dashboard.</p>
                    </article>
                    <article class="rounded-4xl border border-white/80 bg-white p-6 shadow-soft">
                        <div class="flex h-14 w-14 items-center justify-center rounded-3xl bg-indigo-50 text-gem-700">
                            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="5" width="16" height="14" rx="2"/><path d="M8 9h8M8 13h8"/></svg>
                        </div>
                        <h3 class="mt-5 text-xl font-black text-slate-950">API Integration</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-600">Developer-ready reseller API with wallet, transaction, and status visibility.</p>
                    </article>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <div class="mb-8 flex items-end justify-between gap-4">
                    <div>
                        <span class="text-sm font-bold uppercase tracking-[0.22em] text-gem-700">Sample pricing</span>
                        <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Popular data cards</h2>
                    </div>
                    <a class="text-sm font-bold text-gem-700" href="<?= e(base_url('user/register.php')); ?>">Open your wallet</a>
                </div>
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                    <?php
                    $plans = [
                        ['name' => 'MTN 1GB SME', 'price' => '₦620', 'validity' => '30 days', 'tone' => 'bg-yellow-50 text-yellow-700'],
                        ['name' => 'Airtel 1GB', 'price' => '₦650', 'validity' => '30 days', 'tone' => 'bg-red-50 text-red-700'],
                        ['name' => 'Glo 1GB', 'price' => '₦640', 'validity' => '30 days', 'tone' => 'bg-emerald-50 text-emerald-700'],
                        ['name' => '9mobile 1GB', 'price' => '₦670', 'validity' => '30 days', 'tone' => 'bg-green-50 text-green-700'],
                    ];
                    ?>
                    <?php foreach ($plans as $plan): ?>
                        <article class="rounded-4xl border border-white/80 bg-white p-6 shadow-soft">
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold <?= e($plan['tone']); ?>"><?= e($plan['validity']); ?></span>
                            <h3 class="mt-5 text-xl font-black text-slate-950"><?= e($plan['name']); ?></h3>
                            <p class="mt-2 text-3xl font-black text-slate-950"><?= e($plan['price']); ?></p>
                            <p class="mt-3 text-sm text-slate-600">Best for agents, retailers, and daily customer delivery.</p>
                            <a class="mt-6 inline-flex rounded-full bg-slate-950 px-5 py-3 text-sm font-bold text-white transition hover:bg-gem-600" href="<?= e(base_url('user/register.php')); ?>">Buy Now</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <div class="grid gap-5 rounded-[2rem] border border-white/80 bg-white/80 p-6 shadow-soft sm:grid-cols-2 xl:grid-cols-4">
                    <div><p class="text-4xl font-black text-slate-950">500K+</p><p class="mt-2 text-sm font-semibold text-slate-600">Transactions</p></div>
                    <div><p class="text-4xl font-black text-slate-950">10K+</p><p class="mt-2 text-sm font-semibold text-slate-600">Users</p></div>
                    <div><p class="text-4xl font-black text-slate-950">99.9%</p><p class="mt-2 text-sm font-semibold text-slate-600">Uptime</p></div>
                    <div><p class="text-4xl font-black text-slate-950">24/7</p><p class="mt-2 text-sm font-semibold text-slate-600">Support</p></div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8 lg:py-6">
                <div class="grid gap-5 rounded-[2rem] border border-slate-200/70 bg-white/85 p-6 shadow-soft lg:grid-cols-3">
                    <div class="rounded-3xl bg-slate-50 p-5">
                        <p class="text-sm font-bold uppercase tracking-[0.18em] text-gem-700">Why users stay</p>
                        <p class="mt-3 text-lg font-black text-slate-950">One wallet, one dashboard, one clear purchase flow.</p>
                    </div>
                    <div class="rounded-3xl bg-slate-50 p-5">
                        <p class="text-sm font-bold uppercase tracking-[0.18em] text-gem-700">Trust cue</p>
                        <p class="mt-3 text-base leading-7 text-slate-600">Funding requests are tracked and verified before wallet credit, so the money flow feels safer and easier to understand.</p>
                    </div>
                    <div class="rounded-3xl bg-slate-50 p-5">
                        <p class="text-sm font-bold uppercase tracking-[0.18em] text-gem-700">Fast repeat usage</p>
                        <p class="mt-3 text-base leading-7 text-slate-600">Frequent actions like buying data and airtime are designed for a short, repeatable flow.</p>
                    </div>
                </div>
            </section>

            <section id="download" class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
                <div class="gd-download-banner grid items-center gap-8 rounded-[2rem] px-6 py-8 text-white shadow-soft lg:grid-cols-[1fr,0.9fr] lg:px-10 lg:py-12">
                    <div>
                        <span class="text-sm font-bold uppercase tracking-[0.22em] text-blue-100">Mobile access</span>
                        <h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Download GemData App</h2>
                        <p class="mt-4 max-w-2xl text-base leading-8 text-slate-300">Monitor wallet balance, buy VTU services, and track delivery on the go with the GemData mobile experience.</p>
                        <a class="mt-8 inline-flex rounded-full bg-white px-6 py-3.5 text-sm font-bold text-gem-700 transition hover:bg-gem-50" href="<?= e(base_url('user/register.php')); ?>">Install App</a>
                    </div>
                    <div class="gd-app-mockup rounded-[1.75rem] p-5">
                        <div class="gd-app-panel rounded-[1.4rem] p-5">
                            <p class="gd-app-title text-sm font-bold">GemData App</p>
                            <div class="mt-5 space-y-4">
                                <div class="gd-app-card gd-app-balance rounded-3xl p-4">
                                    <p class="gd-app-label text-xs uppercase tracking-[0.18em]">Wallet balance</p>
                                    <p class="gd-app-value mt-2 text-2xl font-black">NGN 8,240.00</p>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="gd-app-card gd-app-mini gd-app-mini-data rounded-3xl p-4">
                                        <p class="gd-app-label text-xs font-bold uppercase tracking-[0.18em]">Data</p>
                                        <p class="gd-app-value mt-2 text-lg font-black">MTN 2GB</p>
                                    </div>
                                    <div class="gd-app-card gd-app-mini gd-app-mini-bills rounded-3xl p-4">
                                        <p class="gd-app-label text-xs font-bold uppercase tracking-[0.18em]">Bills</p>
                                        <p class="gd-app-value mt-2 text-lg font-black">EKEDC</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="about" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
                <div class="mb-10 text-center">
                    <span class="text-sm font-bold uppercase tracking-[0.22em] text-gem-700">Why GemData</span>
                    <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Trust is our only currency</h2>
                </div>
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                    <?php
                    $reasons = [
                        ['title' => 'Instant Delivery', 'copy' => 'Fast fulfillment for data, airtime, and bill requests.', 'icon' => 'delivery'],
                        ['title' => 'Secure Wallet', 'copy' => 'Structured wallet controls and auditable transaction records.', 'icon' => 'secure'],
                        ['title' => 'Affordable Pricing', 'copy' => 'Competitive pricing designed for agents and end users.', 'icon' => 'pricing'],
                        ['title' => 'Priority Support', 'copy' => 'Operational support and onboarding for resellers and partners.', 'icon' => 'support'],
                    ];
                    ?>
                    <?php foreach ($reasons as $reason): ?>
                        <article class="rounded-4xl border border-white/80 bg-white p-6 shadow-soft">
                            <div class="flex h-14 w-14 items-center justify-center rounded-3xl bg-slate-100 text-slate-700">
                                <?php if ($reason['icon'] === 'delivery'): ?>
                                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 2 5 14h6l-1 8 8-12h-6z"/></svg>
                                <?php elseif ($reason['icon'] === 'secure'): ?>
                                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l7 3v6c0 5-3.5 8-7 9-3.5-1-7-4-7-9V6l7-3z"/></svg>
                                <?php elseif ($reason['icon'] === 'pricing'): ?>
                                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 1v22"/><path d="M17 5.5c0-1.93-2.24-3.5-5-3.5S7 3.57 7 5.5 9.24 9 12 9s5 1.57 5 3.5S14.76 16 12 16s-5-1.57-5-3.5"/></svg>
                                <?php else: ?>
                                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16v10H7l-3 3z"/><path d="M8 10h8"/></svg>
                                <?php endif; ?>
                            </div>
                            <h3 class="mt-5 text-xl font-black text-slate-950"><?= e($reason['title']); ?></h3>
                            <p class="mt-3 text-sm leading-7 text-slate-600"><?= e($reason['copy']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200/70 bg-white/80">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[1fr,0.8fr,0.8fr,0.8fr] lg:px-8">
                <div>
                    <p class="text-xl font-black text-slate-950">GemData</p>
                    <p class="mt-3 max-w-xs text-sm leading-7 text-slate-600">Premium Nigerian VTU delivery for airtime, data, bills, and reseller API operations.</p>
                </div>
                <div>
                    <p class="text-sm font-black uppercase tracking-[0.18em] text-slate-500">Links</p>
                    <div class="mt-4 space-y-3 text-sm text-slate-600">
                        <a class="block hover:text-slate-950" href="#home">Home</a>
                        <a class="block hover:text-slate-950" href="#services">Services</a>
                        <a class="block hover:text-slate-950" href="<?= e(base_url('docs/api.php')); ?>">API</a>
                    </div>
                </div>
                <div>
                    <p class="text-sm font-black uppercase tracking-[0.18em] text-slate-500">Access</p>
                    <div class="mt-4 space-y-3 text-sm text-slate-600">
                        <a class="block hover:text-slate-950" href="<?= e(base_url('user/login.php')); ?>">Login</a>
                        <a class="block hover:text-slate-950" href="<?= e(base_url('user/register.php')); ?>">Register</a>
                        <a class="block hover:text-slate-950" href="<?= e(base_url('admin/login.php')); ?>">Admin</a>
                    </div>
                </div>
                <div>
                    <p class="text-sm font-black uppercase tracking-[0.18em] text-slate-500">Contact</p>
                    <div class="mt-4 space-y-3 text-sm text-slate-600">
                        <p><a href="mailto:support@gemdata.com.ng" class="hover:text-slate-950">support@gemdata.com.ng</a></p>
                        <p><a href="tel:+2348155568369" class="hover:text-slate-950">+2348155568369</a></p>
                        <div class="flex gap-3 pt-1 text-slate-500">
                            <a href="https://x.com/AYaseer10" target="_blank" rel="noopener noreferrer" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 transition hover:bg-slate-200" aria-label="Twitter / X">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 4.01c-.77.35-1.6.58-2.47.69a4.27 4.27 0 0 0 1.88-2.36 8.4 8.4 0 0 1-2.7 1.03 4.22 4.22 0 0 0-7.32 2.89c0 .33.04.65.11.96A11.97 11.97 0 0 1 3 3.86a4.22 4.22 0 0 0 1.31 5.63 4.18 4.18 0 0 1-1.91-.53v.05a4.22 4.22 0 0 0 3.39 4.14 4.27 4.27 0 0 1-1.9.07 4.23 4.23 0 0 0 3.95 2.93A8.47 8.47 0 0 1 2 18.58 11.94 11.94 0 0 0 8.48 20.5c7.78 0 12.03-6.44 12.03-12.02 0-.18 0-.37-.01-.55A8.54 8.54 0 0 0 22 4.01z"/></svg>
                            </a>
                            <a href="mailto:support@gemdata.com.ng" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 transition hover:bg-slate-200" aria-label="Email">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                            </a>
                            <a href="https://www.instagram.com/museernetworkslimited?utm_source=qr&igsh=cWhkaDdrenMxN2I5" target="_blank" rel="noopener noreferrer" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 transition hover:bg-slate-200" aria-label="Instagram">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="3.5"/><circle cx="17.5" cy="6.5" r=".8" fill="currentColor" stroke="none"/></svg>
                            </a>
                            <a href="https://wa.me/2349077513009" target="_blank" rel="noopener noreferrer" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 transition hover:bg-slate-200" aria-label="WhatsApp">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="border-t border-slate-200/70">
                <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 px-4 py-4 text-xs text-slate-500 sm:flex-row sm:px-6 lg:px-8">
                    <p>&copy; 2026 GemData. All Rights Reserved</p>
                    <p>Powered by Museer Networks Limited</p>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
