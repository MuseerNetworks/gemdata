<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('services.manage');
$pricing = app(\GemData\Classes\PricingService::class);
$providerPlans = app(\GemData\Classes\ProviderPlanService::class);

if (is_post()) {
    verify_csrf();
    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        db()->execute('UPDATE services SET is_enabled = :enabled WHERE id = :id', ['enabled' => (int) ($_POST['enabled'] ?? 0), 'id' => $serviceId]);
    }
    if ($action === 'commission') {
        app(\GemData\Classes\Commission::class)->upsert(($_POST['user_id'] ?? '') === '' ? null : (int) $_POST['user_id'], $serviceId, (float) ($_POST['rate_percent'] ?? 0));
    }
    if ($action === 'toggle_network') {
        db()->execute('UPDATE service_networks SET is_enabled = :enabled WHERE id = :id', ['enabled' => (int) ($_POST['enabled'] ?? 0), 'id' => (int) ($_POST['network_id'] ?? 0)]);
    }
    if ($action === 'save_tier_price') {
        $pricing->upsertTierPrice($serviceId, $_POST['network_code'] ?? null, (string) $_POST['tier'], (float) $_POST['cost_price'], (float) $_POST['selling_price']);
    }
    if ($action === 'save_user_price') {
        $pricing->upsertUserPrice((int) $_POST['user_id'], $serviceId, $_POST['network_code'] ?? null, (float) $_POST['selling_price']);
    }
    if ($action === 'save_provider_plan') {
        $providerPlans->upsertMapping($_POST);
    }
    app(\GemData\Classes\ActivityLogger::class)->log('admin', (int) $admin['id'], 'service_configuration_updated', 'Updated service configuration.', ['service_id' => $serviceId, 'action' => $action]);
    flash('success', 'Service settings updated.');
    redirect(base_url('admin/services.php'));
}

$services = db()->query(
    'SELECT s.*, c.rate_percent AS default_rate
     FROM services s
     LEFT JOIN commissions c ON c.service_id = s.id AND c.user_id IS NULL
     ORDER BY s.name'
);
$apiUsers = db()->query('SELECT id, full_name FROM users WHERE is_api_user = 1 ORDER BY full_name');
$networks = db()->query('SELECT sn.*, s.name AS service_name FROM service_networks sn INNER JOIN services s ON s.id = sn.service_id ORDER BY s.name, sn.network_name');
$providerRows = db()->query('SELECT id, name, code, driver, status FROM provider_accounts ORDER BY priority_order, name');
$providerPlanMappings = $providerPlans->mappingsForAdmin();

render_header('Services', 'admin');
?>
<div class="space-y-6">
    <?php if ($message = flash('success')): ?><div class="notice notice-success"><?= e($message); ?></div><?php endif; ?>
    <div class="surface-card p-6">
        <h1 class="text-3xl font-black text-white">Service Controls, Networks, and Pricing</h1>
        <div class="table-shell mt-6">
            <table>
                <thead>
                    <tr class="text-slate-400">
                        <th>Service</th><th>Status</th><th>Default Commission</th><th>Tier Pricing</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <?php $tierPrices = $pricing->tierPricesByService((int) $service['id']); ?>
                        <tr data-search-item data-search="<?= e($service['name'] . ' ' . ($service['default_rate'] ?? '0.00')); ?>">
                            <td><?= e($service['name']); ?></td>
                            <td><?= (int) $service['is_enabled'] === 1 ? 'Enabled' : 'Disabled'; ?></td>
                            <td><?= e((string) ($service['default_rate'] ?? '0.00')); ?>%</td>
                            <td>
                                <div class="space-y-2">
                                    <?php foreach (array_slice($tierPrices, 0, 4) as $price): ?>
                                        <div class="text-xs text-slate-300"><?= e($price['tier']); ?> / <?= e($price['network_code'] ?? 'default'); ?>: <?= e(money($price['selling_price'])); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-3">
                                    <form method="post" class="flex items-center gap-2">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="service_id" value="<?= (int) $service['id']; ?>">
                                        <input type="hidden" name="enabled" value="<?= (int) $service['is_enabled'] === 1 ? 0 : 1; ?>">
                                        <button class="rounded-lg bg-cyan-400 px-3 py-2 text-sm font-semibold text-slate-950" type="submit"><?= (int) $service['is_enabled'] === 1 ? 'Disable' : 'Enable'; ?></button>
                                    </form>
                                    <form method="post" class="flex items-center gap-2">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="commission">
                                        <input type="hidden" name="service_id" value="<?= (int) $service['id']; ?>">
                                        <input class="w-28 rounded-lg border border-white/10 bg-slate-900 px-3 py-2" name="rate_percent" placeholder="Rate %" value="<?= e((string) ($service['default_rate'] ?? '0.00')); ?>">
                                        <button class="rounded-lg border border-white/10 px-3 py-2 text-sm font-semibold" type="submit">Save Commission</button>
                                    </form>
                                </div>
                                <form method="post" class="mt-3 grid gap-2 md:grid-cols-5">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="save_tier_price">
                                    <input type="hidden" name="service_id" value="<?= (int) $service['id']; ?>">
                                    <select class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2" name="tier"><?php foreach (['USER','RESELLER','AGENT','API_RESELLER'] as $tier): ?><option value="<?= e($tier); ?>"><?= e($tier); ?></option><?php endforeach; ?></select>
                                    <input class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2" name="network_code" placeholder="network">
                                    <input class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2" name="cost_price" placeholder="cost price">
                                    <input class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2" name="selling_price" placeholder="selling price">
                                    <button class="rounded-lg border border-white/10 px-3 py-2 text-sm font-semibold" type="submit">Save Price</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="surface-card p-6">
        <h2 class="text-2xl font-bold text-white">Network Availability</h2>
        <div class="table-shell mt-4">
            <table>
                <thead><tr class="text-slate-400"><th>Service</th><th>Network</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($networks as $network): ?>
                    <tr>
                        <td><?= e($network['service_name']); ?></td>
                        <td><?= e($network['network_name']); ?></td>
                        <td><?= (int) $network['is_enabled'] === 1 ? 'Enabled' : 'Disabled'; ?></td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="toggle_network">
                                <input type="hidden" name="service_id" value="<?= (int) $network['service_id']; ?>">
                                <input type="hidden" name="network_id" value="<?= (int) $network['id']; ?>">
                                <input type="hidden" name="enabled" value="<?= (int) $network['is_enabled'] === 1 ? 0 : 1; ?>">
                                <button class="rounded-lg border border-white/10 px-3 py-2 text-sm font-semibold" type="submit"><?= (int) $network['is_enabled'] === 1 ? 'Disable' : 'Enable'; ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="surface-card p-6">
        <h2 class="text-2xl font-bold text-white">User-specific Price Override</h2>
        <form method="post" class="mt-4 grid gap-4 md:grid-cols-5">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <input type="hidden" name="action" value="save_user_price">
            <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="service_id"><?php foreach ($services as $service): ?><option value="<?= (int) $service['id']; ?>"><?= e($service['name']); ?></option><?php endforeach; ?></select>
            <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="user_id"><?php foreach ($apiUsers as $apiUser): ?><option value="<?= (int) $apiUser['id']; ?>"><?= e($apiUser['full_name']); ?></option><?php endforeach; ?></select>
            <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="network_code" placeholder="network">
            <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="selling_price" placeholder="custom selling price">
            <button class="rounded-lg bg-emerald-400 px-5 py-3 font-semibold text-slate-950" type="submit">Apply Override</button>
        </form>
    </div>

    <div class="surface-card p-6">
        <h2 class="text-2xl font-bold text-white">Provider Data Plan Mapping</h2>
        <p class="mt-2 text-sm text-slate-400">Map GemData plan codes to the provider plan IDs Albani expects. Use the same local plan code across providers if you want fallback support later.</p>
        <form method="post" class="mt-4 grid gap-4 md:grid-cols-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <input type="hidden" name="action" value="save_provider_plan">
            <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="provider_account_id">
                <?php foreach ($providerRows as $provider): ?>
                    <option value="<?= (int) $provider['id']; ?>"><?= e($provider['name'] . ' [' . $provider['code'] . ']'); ?></option>
                <?php endforeach; ?>
            </select>
            <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="service_id">
                <?php foreach ($services as $service): ?>
                    <?php if ($service['slug'] === 'data'): ?>
                        <option value="<?= (int) $service['id']; ?>"><?= e($service['name']); ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="network_code" placeholder="network code e.g. mtn">
            <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="local_plan_code" placeholder="local plan code e.g. MTN_2GB_SME">
            <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3 md:col-span-2" name="local_plan_name" placeholder="local plan label e.g. MTN 2GB SME">
            <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="provider_plan_id" placeholder="provider plan ID">
            <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="provider_plan_name" placeholder="provider plan name">
            <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="amount" placeholder="display amount">
            <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="is_enabled" value="1" checked> Enabled</label>
            <button class="rounded-lg bg-cyan-400 px-5 py-3 font-semibold text-slate-950" type="submit">Save Plan Mapping</button>
        </form>

        <div class="table-shell mt-6">
            <table>
                <thead><tr class="text-slate-400"><th>Provider</th><th>Service</th><th>Network</th><th>Local Plan</th><th>Provider Plan</th><th>Amount</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($providerPlanMappings as $mapping): ?>
                    <tr>
                        <td><?= e($mapping['provider_name']); ?></td>
                        <td><?= e($mapping['service_name']); ?></td>
                        <td><?= e($mapping['network_code'] ?: 'default'); ?></td>
                        <td><?= e($mapping['local_plan_code'] . ' - ' . $mapping['local_plan_name']); ?></td>
                        <td><?= e($mapping['provider_plan_id'] . ($mapping['provider_plan_name'] ? ' - ' . $mapping['provider_plan_name'] : '')); ?></td>
                        <td><?= e(money($mapping['amount'])); ?></td>
                        <td><?= (int) $mapping['is_enabled'] === 1 ? 'Enabled' : 'Disabled'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php render_footer(); ?>
