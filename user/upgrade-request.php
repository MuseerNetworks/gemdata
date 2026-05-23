<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$roles = app(\GemData\Classes\UserRoleManager::class);
$upgradeSvc = app(\GemData\Classes\UpgradeRequestService::class);
$role = $roles->roleFor($user);
$targetRole = $roles->nextRole($role);
$latest = $upgradeSvc->latestForUser((int) $user['id']);
$error = '';
$success = '';

if (is_post()) {
    try {
        verify_csrf();
        if ($targetRole === null) {
            throw new RuntimeException('Your account already has the highest dashboard access.');
        }
        if ($role === 'smart' && $targetRole === 'reseller') {
            $upgradeSvc->upgradeSmartToReseller((int) $user['id'], [
                'business_name' => $_POST['business_name'] ?? null,
                'phone' => $_POST['phone'] ?? null,
                'reseller_agreement' => $_POST['reseller_agreement'] ?? null,
            ]);
            $success = 'Your account has been upgraded to Reseller.';
            $user = db()->first('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => (int) $user['id']]) ?: $user;
            $role = $roles->roleFor($user);
            $targetRole = $roles->nextRole($role);
        } else {
            $upgradeSvc->request((int) $user['id'], $targetRole, [
                'business_name' => $_POST['business_name'] ?? null,
                'phone' => $_POST['phone'] ?? null,
                'reason' => $_POST['reason'] ?? null,
                'website_url' => $_POST['website_url'] ?? null,
                'api_agreement' => $_POST['api_agreement'] ?? null,
            ]);
            $success = 'Your API access request has been submitted for admin review.';
        }
        $latest = $upgradeSvc->latestForUser((int) $user['id']);
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$title = match ($role) {
    'api' => 'API User Active',
    'reseller' => 'Request API Access',
    default => 'Upgrade to Reseller',
};
$copy = match ($role) {
    'api' => 'Your developer tools and integration center are ready.',
    'reseller' => 'Connect your website/app and automate transactions.',
    default => 'Unlock better pricing, higher limits, bulk tools & business benefits.',
};

render_header($title, 'user');
?>
<div class="space-y-6">
    <div class="stagger-1">
        <h1 class="text-2xl font-extrabold text-gem-text"><?= e($title); ?></h1>
        <p class="text-[14px] text-gem-muted mt-0.5"><?= $role === 'smart' ? 'Smart users upgrade to Reseller instantly after accepting the agreement.' : 'API access is reviewed and approved by admin.'; ?></p>
    </div>

    <div class="activate-banner rounded-2xl flex flex-col sm:flex-row items-start sm:items-center gap-4 px-5 py-4 stagger-2">
        <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0 text-gem-yellow"><?= icon_svg('shield'); ?></div>
        <div class="flex-1">
            <div class="text-[14px] font-bold text-gem-text"><?= e($title); ?></div>
            <div class="text-[13px] text-gem-muted mt-0.5"><?= e($copy); ?></div>
        </div>
        <?php if ($role === 'api'): ?>
            <a href="<?= e(base_url('user/api-center.php')); ?>" class="flex items-center gap-2 border border-gem-yellow text-gem-orange text-[13px] font-bold px-4 py-2 rounded-xl hover:bg-amber-50 transition-colors flex-shrink-0">Open API Center <?= icon_svg('chevron'); ?></a>
        <?php endif; ?>
    </div>

    <?php if ($error !== ''): ?>
        <div class="bg-red-50 border border-red-100 text-gem-red rounded-2xl px-5 py-4 text-[13px] font-semibold"><?= e($error); ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="bg-green-50 border border-green-100 text-gem-green rounded-2xl px-5 py-4 text-[13px] font-semibold"><?= e($success); ?></div>
    <?php endif; ?>

    <section class="grid grid-cols-1 lg:grid-cols-5 gap-4 stagger-3">
        <article class="lg:col-span-3 bg-white rounded-2xl shadow-card border border-gem-border p-5">
            <?php if ($latest): ?>
                <div class="rounded-2xl bg-gem-gray border border-gem-border p-4 mb-5">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-600 text-[11px] font-semibold px-2.5 py-1 rounded-full"><?= e(ucfirst(str_replace('_', ' ', (string) $latest['status']))); ?></span>
                        <strong class="text-[13px] text-gem-text"><?= e(ucfirst((string) $latest['from_type'])); ?> to <?= e(ucfirst((string) $latest['to_type'])); ?></strong>
                    </div>
                    <div class="text-[12px] text-gem-muted">Submitted <?= e(human_datetime((string) $latest['created_at'])); ?></div>
                    <?php if (!empty($latest['admin_note'])): ?><p class="text-[13px] text-gem-muted mt-2"><?= e((string) $latest['admin_note']); ?></p><?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($role === 'api'): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <a class="rounded-2xl bg-gem-gray border border-gem-border p-4" href="<?= e(base_url('user/api-center.php')); ?>"><div class="text-[13px] font-bold text-gem-text">API Center</div><div class="text-[11px] text-gem-muted mt-1">Open developer dashboard</div></a>
                    <a class="rounded-2xl bg-gem-gray border border-gem-border p-4" href="<?= e(base_url('user/api-keys.php')); ?>"><div class="text-[13px] font-bold text-gem-text">API Keys</div><div class="text-[11px] text-gem-muted mt-1">Manage secure access</div></a>
                </div>
            <?php elseif (($latest['status'] ?? '') === 'pending'): ?>
                <div class="text-center py-8">
                    <div class="text-[15px] font-bold text-gem-text">Request under review</div>
                    <p class="text-[13px] text-gem-muted mt-1">Admin approval is required before your dashboard role changes.</p>
                </div>
            <?php else: ?>
                <form method="post" class="grid grid-cols-1 sm:grid-cols-2 gap-3" data-loading-form>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <?php if ($role === 'smart'): ?>
                        <label class="text-[12px] font-semibold text-gem-muted uppercase tracking-wider">Full Name
                            <input class="mt-1.5 w-full rounded-xl bg-gem-gray border border-gem-border px-4 py-3 text-[13px] text-gem-text" name="full_name" value="<?= e((string) ($user['full_name'] ?? '')); ?>" readonly>
                        </label>
                        <label class="text-[12px] font-semibold text-gem-muted uppercase tracking-wider">Business Name
                            <input class="mt-1.5 w-full rounded-xl bg-gem-gray border border-gem-border px-4 py-3 text-[13px] text-gem-text focus:outline-none focus:border-gem-blue focus:ring-2 focus:ring-gem-blue/10" name="business_name" value="<?= e((string) ($_POST['business_name'] ?? '')); ?>">
                        </label>
                        <label class="text-[12px] font-semibold text-gem-muted uppercase tracking-wider">Phone Number
                            <input class="mt-1.5 w-full rounded-xl bg-gem-gray border border-gem-border px-4 py-3 text-[13px] text-gem-text focus:outline-none focus:border-gem-blue focus:ring-2 focus:ring-gem-blue/10" name="phone" value="<?= e((string) ($_POST['phone'] ?? ($user['phone'] ?? ''))); ?>">
                        </label>
                        <label class="sm:col-span-2 flex items-start gap-3 rounded-2xl border border-gem-border bg-gem-gray p-4 text-[13px] font-semibold text-gem-text">
                            <input class="mt-1 h-4 w-4 rounded border-gem-border text-gem-blue focus:ring-gem-blue" type="checkbox" name="reseller_agreement" value="1" required>
                            <span>I agree to the Reseller Terms and Conditions and understand my account will be upgraded immediately.</span>
                        </label>
                        <div class="sm:col-span-2"><button class="gd-button" type="submit" data-loading-label="Upgrading account...">Upgrade to Reseller</button></div>
                    <?php elseif ($role === 'reseller'): ?>
                        <label class="sm:col-span-2 text-[12px] font-semibold text-gem-muted uppercase tracking-wider">Website/App URL
                            <input class="mt-1.5 w-full rounded-xl bg-gem-gray border border-gem-border px-4 py-3 text-[13px] text-gem-text focus:outline-none focus:border-gem-blue focus:ring-2 focus:ring-gem-blue/10" name="website_url" type="url" value="<?= e((string) ($_POST['website_url'] ?? '')); ?>" placeholder="https://example.com" required>
                        </label>
                        <label class="sm:col-span-2 flex items-start gap-3 rounded-2xl border border-gem-border bg-gem-gray p-4 text-[13px] font-semibold text-gem-text">
                            <input class="mt-1 h-4 w-4 rounded border-gem-border text-gem-blue focus:ring-gem-blue" type="checkbox" name="api_agreement" value="1" required>
                            <span>I agree to the API User Agreement and understand API access requires admin approval.</span>
                        </label>
                        <div class="sm:col-span-2"><button class="gd-button" type="submit" data-loading-label="Submitting request...">Submit API Access Request</button></div>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </article>

        <aside class="lg:col-span-2 bg-white rounded-2xl shadow-card border border-gem-border p-5">
            <h2 class="text-[16px] font-bold text-gem-text">Progression Rule</h2>
            <p class="text-[13px] text-gem-muted mt-2">Smart users become Resellers immediately after accepting the Reseller agreement. Resellers request API access through admin approval, and API tools stay hidden until approval.</p>
        </aside>
    </section>
</div>
<?php render_footer(); ?>
