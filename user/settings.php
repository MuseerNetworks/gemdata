<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();

render_header('Settings', 'user');
?>
<div class="section-stack">
    <section class="surface-card p-6" data-search-item data-search="settings preferences account profile notifications security">
        <p class="eyebrow">Settings</p>
        <h1 class="surface-section-title">User preferences and account posture</h1>
        <p class="surface-section-copy">Manage appearance and review your account posture while the platform keeps wallet and transaction controls aligned with the live system.</p>
    </section>

    <section class="surface-card p-6">
        <div class="mb-8 space-y-4" data-search-item data-search="theme light fintech warm cream cool glass dark mode appearance">
            <div class="flex flex-col gap-2">
                <h2 class="surface-section-title">Theme Personalization</h2>
                <p class="surface-section-copy">Choose the workspace style that fits your workflow. Your preference is saved in this browser automatically, and it can now be changed quickly from the profile menu too.</p>
            </div>
            <label class="theme-select">
                Theme Preset
                <select data-theme-select>
                    <option value="light-fintech">Light Fintech</option>
                    <option value="warm-cream">Warm Cream Fintech</option>
                    <option value="cool-glass">Cool Glass Light</option>
                    <option value="dark">Dark Mode</option>
                </select>
            </label>
            <div class="theme-picker">
                <button class="theme-pill" type="button" data-theme-option="light-fintech">
                    <strong>Light Fintech</strong>
                    <span>Bright operational surfaces with teal-blue accents.</span>
                </button>
                <button class="theme-pill" type="button" data-theme-option="warm-cream">
                    <strong>Warm Cream</strong>
                    <span>Soft premium neutrals with emerald highlights.</span>
                </button>
                <button class="theme-pill" type="button" data-theme-option="cool-glass">
                    <strong>Cool Glass</strong>
                    <span>Airy translucent surfaces with crisp cyan energy.</span>
                </button>
                <button class="theme-pill" type="button" data-theme-option="dark">
                    <strong>Dark Mode</strong>
                    <span>The refined slate fintech look for low-light sessions.</span>
                </button>
            </div>
        </div>

        <div class="settings-grid">
            <label data-search-item data-search="full name profile">
                Full Name
                <input value="<?= e($user['full_name']); ?>" readonly>
            </label>
            <label data-search-item data-search="email profile">
                Email Address
                <input value="<?= e($user['email']); ?>" readonly>
            </label>
            <label data-search-item data-search="phone profile">
                Phone Number
                <input value="<?= e($user['phone']); ?>" readonly>
            </label>
            <label data-search-item data-search="wallet balance">
                Wallet Balance
                <input value="<?= e(money($user['balance'] ?? 0)); ?>" readonly>
            </label>
            <label data-search-item data-search="notification alerts sms">
                Notification Preference
                <select>
                    <option selected>In-app alerts</option>
                    <option>Email summaries</option>
                    <option>SMS alerts</option>
                </select>
            </label>
            <label data-search-item data-search="theme dashboard appearance">
                Current Theme
                <input value="Managed from Theme Personalization above" readonly>
            </label>
            <label data-search-item data-search="security password session">
                Session Security
                <input value="Password-protected and session-based" readonly>
            </label>
            <label data-search-item data-search="api access reseller profile">
                API Access
                <input value="<?= (int) $user['is_api_user'] === 1 ? 'Enabled' : 'Pending admin approval'; ?>" readonly>
            </label>
        </div>
        <div class="mt-6 flex flex-wrap gap-3">
            <a class="primary-action inline-flex items-center justify-center" href="<?= e(base_url('user/api-keys.php')); ?>">Open Profile / API Access</a>
            <a class="secondary-action inline-flex items-center justify-center" href="<?= e(base_url('user/notifications.php')); ?>">Manage Alerts</a>
        </div>
    </section>
</div>
<?php render_footer(); ?>
