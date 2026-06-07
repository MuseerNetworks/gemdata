<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('services.manage');
$pricing = app(\GemData\Classes\PricingService::class);
$providerPlans = app(\GemData\Classes\ProviderPlanService::class);
$providerPlanCatalog = app(\GemData\Classes\ProviderPlanCatalogService::class);
$catalogPreview = null;

if (is_post()) {
    verify_csrf();
    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'sync_provider_plans') {
            $catalogPreview = $providerPlanCatalog->syncPlans((int) ($_POST['provider_account_id'] ?? 0), $serviceId);
        } elseif ($action === 'preview_csv_plans') {
            $catalogPreview = $providerPlanCatalog->previewCsvUpload($_FILES['catalog_csv'] ?? [], (int) ($_POST['provider_account_id'] ?? 0), $serviceId);
        } elseif ($action === 'preview_bulk_plans') {
            $catalogPreview = $providerPlanCatalog->previewBulkText((string) ($_POST['bulk_plans'] ?? ''), (int) ($_POST['provider_account_id'] ?? 0), $serviceId);
        } elseif ($action === 'preview_manual_plan') {
            $catalogPreview = $providerPlanCatalog->previewManual($_POST);
        } elseif ($action === 'save_manual_provider_plan') {
            $result = $providerPlanCatalog->saveManual($_POST);
            flash('success', $result['saved'] . ' provider plan mapping saved.');
            app(\GemData\Classes\ActivityLogger::class)->log('admin', (int) $admin['id'], 'provider_plan_catalog_manual_saved', 'Saved manual provider plan catalog row.', [
                'saved' => (int) $result['saved'],
            ]);
            redirect(base_url('admin/services.php#provider-plan-catalog'));
        } elseif ($action === 'publish_provider_plan_preview') {
            $result = $providerPlanCatalog->publishRows($_POST);
            if ($result['errors'] !== []) {
                flash('error', 'Some plan rows could not be saved: ' . implode(' ', $result['errors']));
            } else {
                flash('success', $result['saved'] . ' provider plan mapping(s) saved. ' . $result['skipped'] . ' row(s) skipped.');
            }
            app(\GemData\Classes\ActivityLogger::class)->log('admin', (int) $admin['id'], 'provider_plan_catalog_published', 'Published provider plan catalog rows.', [
                'saved' => (int) $result['saved'],
                'skipped' => (int) $result['skipped'],
            ]);
            redirect(base_url('admin/services.php#provider-plan-catalog'));
        } elseif ($action === 'delete_provider_plan') {
            $providerPlanCatalog->deleteMapping((int) ($_POST['mapping_id'] ?? 0));
            flash('success', 'Provider plan mapping deleted.');
            redirect(base_url('admin/services.php#provider-plan-catalog'));
        } else {
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
    } catch (Throwable $throwable) {
        flash('error', $throwable->getMessage());
        if (!in_array($action, ['sync_provider_plans', 'preview_csv_plans', 'preview_bulk_plans', 'preview_manual_plan'], true)) {
            redirect(base_url('admin/services.php#provider-plan-catalog'));
        }
    }
}

$services = db()->query(
    'SELECT s.*, c.rate_percent AS default_rate
     FROM services s
     LEFT JOIN commissions c ON c.service_id = s.id AND c.user_id IS NULL
     ORDER BY s.name'
);
$apiUsers = db()->query('SELECT id, full_name FROM users WHERE is_api_user = 1 ORDER BY full_name');
$networks = db()->query('SELECT sn.*, s.name AS service_name FROM service_networks sn INNER JOIN services s ON s.id = sn.service_id ORDER BY s.name, sn.network_name');
$allowedProviderDrivers = \GemData\Classes\RealProviderRegistry::sqlInList(\GemData\Classes\RealProviderRegistry::DRIVERS);
$providerRows = db()->query(
    'SELECT id, name, code, driver, status
     FROM provider_accounts
     WHERE driver IN (' . $allowedProviderDrivers . ')
       AND status <> "archived"
     ORDER BY priority_order, name'
);
$providerPlanMappings = $providerPlans->mappingsForAdmin();
$latestProviderPlanMappings = $providerPlans->latestMappingsForAdmin(5);

render_header('Services', 'admin');
?>
<div class="space-y-6">
    <?php if ($message = flash('success')): ?><div class="notice notice-success"><?= e($message); ?></div><?php endif; ?>
    <?php if ($message = flash('error')): ?><div class="notice notice-error"><?= e($message); ?></div><?php endif; ?>
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

    <div class="surface-card p-6" id="provider-plan-catalog">
        <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-white">Provider Plan Catalog</h2>
                <p class="mt-2 text-sm text-slate-400">Sync, import, paste, or manually add provider plans. Plans only appear to users after they are published/enabled.</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/60 px-4 py-3 text-xs text-slate-300">
                CSV columns: <span class="font-mono">service_slug,network_code,local_plan_code,local_plan_name,provider_plan_id,provider_plan_name,amount,is_enabled</span>
            </div>
        </div>

        <div class="mt-5 grid gap-4 xl:grid-cols-2">
            <form method="post" class="rounded-2xl border border-white/10 bg-slate-950/60 p-4">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="sync_provider_plans">
                <h3 class="text-lg font-bold text-white">Sync Plans</h3>
                <p class="mt-1 text-sm text-slate-400">Always available. Unsupported providers will show a safe fallback message.</p>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="provider_account_id" required>
                        <?php foreach ($providerRows as $provider): ?>
                            <option value="<?= (int) $provider['id']; ?>"><?= e($provider['name'] . ' [' . $provider['code'] . ']'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="service_id" required>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= (int) $service['id']; ?>"><?= e($service['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="mt-4 w-full rounded-lg bg-cyan-400 px-5 py-3 font-semibold text-slate-950" type="submit">Sync Plans</button>
            </form>

            <form method="post" enctype="multipart/form-data" class="rounded-2xl border border-white/10 bg-slate-950/60 p-4">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="preview_csv_plans">
                <h3 class="text-lg font-bold text-white">CSV Import</h3>
                <p class="mt-1 text-sm text-slate-400">Upload provider plans using the catalog columns.</p>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="provider_account_id" required>
                        <?php foreach ($providerRows as $provider): ?>
                            <option value="<?= (int) $provider['id']; ?>"><?= e($provider['name'] . ' [' . $provider['code'] . ']'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="service_id" required>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= (int) $service['id']; ?>"><?= e($service['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3 md:col-span-2" type="file" name="catalog_csv" accept=".csv,text/csv" required>
                </div>
                <button class="mt-4 w-full rounded-lg border border-cyan-300/40 px-5 py-3 font-semibold text-cyan-200" type="submit">Preview CSV</button>
            </form>
        </div>

        <div class="mt-4 grid gap-4 xl:grid-cols-2">
            <form method="post" class="rounded-2xl border border-white/10 bg-slate-950/60 p-4">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="preview_bulk_plans">
                <h3 class="text-lg font-bold text-white">Bulk Paste Import</h3>
                <p class="mt-1 text-sm text-slate-400">Accepts comma, tab, or pipe-separated rows with the same columns.</p>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="provider_account_id" required>
                        <?php foreach ($providerRows as $provider): ?>
                            <option value="<?= (int) $provider['id']; ?>"><?= e($provider['name'] . ' [' . $provider['code'] . ']'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="service_id" required>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= (int) $service['id']; ?>"><?= e($service['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <textarea class="min-h-40 rounded-lg border border-white/10 bg-slate-900 px-4 py-3 md:col-span-2" name="bulk_plans" placeholder="service_slug,network_code,local_plan_code,local_plan_name,provider_plan_id,provider_plan_name,amount,is_enabled" required></textarea>
                </div>
                <button class="mt-4 w-full rounded-lg border border-white/10 px-5 py-3 font-semibold text-white" type="submit">Preview Bulk Paste</button>
            </form>

            <form method="post" class="rounded-2xl border border-white/10 bg-slate-950/60 p-4">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="save_manual_provider_plan">
                <h3 class="text-lg font-bold text-white">Manual Add</h3>
                <p class="mt-1 text-sm text-slate-400">Save a one-off provider plan immediately.</p>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="provider_account_id" required>
                        <?php foreach ($providerRows as $provider): ?>
                            <option value="<?= (int) $provider['id']; ?>"><?= e($provider['name'] . ' [' . $provider['code'] . ']'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="service_id" required>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= (int) $service['id']; ?>"><?= e($service['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="network_code" placeholder="network code e.g. mtn">
                    <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="local_plan_code" placeholder="local plan code, optional">
                    <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="local_plan_name" placeholder="local plan name" required>
                    <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="provider_plan_id" placeholder="provider plan ID" required>
                    <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="provider_plan_name" placeholder="provider plan name">
                    <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="amount" placeholder="selling amount" required>
                    <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="is_enabled" value="1"> Publish now</label>
                </div>
                <button class="mt-4 w-full rounded-lg bg-emerald-400 px-5 py-3 font-semibold text-slate-950" type="submit">Save Manual Plan</button>
            </form>
        </div>

        <?php if (is_array($catalogPreview)): ?>
            <div class="mt-6 rounded-2xl border border-white/10 bg-slate-950/60 p-4">
                <?php if (!empty($catalogPreview['message'])): ?>
                    <div class="notice <?= empty($catalogPreview['supported']) ? 'notice-error' : 'notice-success'; ?>"><?= e((string) $catalogPreview['message']); ?></div>
                <?php endif; ?>
                <?php if (!empty($catalogPreview['errors'])): ?>
                    <div class="notice notice-error mt-3">
                        <strong>Preview errors</strong>
                        <ul class="mt-2 list-disc pl-5 text-sm">
                            <?php foreach ($catalogPreview['errors'] as $error): ?><li><?= e((string) $error); ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($catalogPreview['rows'])): ?>
                    <form method="post" class="mt-4">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="publish_provider_plan_preview">
                        <div class="table-shell">
                            <table>
                                <thead><tr class="text-slate-400"><th>Publish</th><th>Provider</th><th>Service</th><th>Network</th><th>Local Plan</th><th>Provider Plan ID</th><th>Amount</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php $lastGroup = ''; ?>
                                <?php foreach ($catalogPreview['rows'] as $index => $row): ?>
                                    <?php
                                    $group = $row['provider_code'] . ' / ' . $row['service_slug'] . ' / ' . ($row['network_code'] ?: 'default');
                                    if ($group !== $lastGroup):
                                        $lastGroup = $group;
                                    ?>
                                        <tr><td colspan="8" class="bg-slate-900/80 px-4 py-2 text-xs font-bold uppercase tracking-widest text-cyan-200"><?= e($group); ?></td></tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td>
                                            <input type="hidden" name="plans[<?= (int) $index; ?>][provider_account_id]" value="<?= (int) $row['provider_account_id']; ?>">
                                            <input type="hidden" name="plans[<?= (int) $index; ?>][service_id]" value="<?= (int) $row['service_id']; ?>">
                                            <input type="hidden" name="plans[<?= (int) $index; ?>][service_slug]" value="<?= e($row['service_slug']); ?>">
                                            <input type="hidden" name="plans[<?= (int) $index; ?>][selected]" value="0">
                                            <input type="checkbox" name="plans[<?= (int) $index; ?>][selected]" value="1"<?= !empty($row['selected']) ? ' checked' : ''; ?>>
                                        </td>
                                        <td><?= e($row['provider_name']); ?></td>
                                        <td><?= e($row['service_name']); ?></td>
                                        <td><input class="w-28 rounded-lg border border-white/10 bg-slate-900 px-3 py-2" name="plans[<?= (int) $index; ?>][network_code]" value="<?= e((string) ($row['network_code'] ?? '')); ?>"></td>
                                        <td>
                                            <input class="mb-2 w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2" name="plans[<?= (int) $index; ?>][local_plan_code]" value="<?= e($row['local_plan_code']); ?>" placeholder="local code">
                                            <input class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2" name="plans[<?= (int) $index; ?>][local_plan_name]" value="<?= e($row['local_plan_name']); ?>" placeholder="local name">
                                        </td>
                                        <td>
                                            <input class="mb-2 w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 font-mono" name="plans[<?= (int) $index; ?>][provider_plan_id]" value="<?= e($row['provider_plan_id']); ?>">
                                            <input class="w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2" name="plans[<?= (int) $index; ?>][provider_plan_name]" value="<?= e($row['provider_plan_name']); ?>" placeholder="provider name">
                                        </td>
                                        <td><input class="w-32 rounded-lg border border-white/10 bg-slate-900 px-3 py-2" name="plans[<?= (int) $index; ?>][amount]" value="<?= e((string) $row['amount']); ?>"></td>
                                        <td>
                                            <input type="hidden" name="plans[<?= (int) $index; ?>][is_enabled]" value="0">
                                            <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="plans[<?= (int) $index; ?>][is_enabled]" value="1"<?= !empty($row['is_enabled']) ? ' checked' : ''; ?>> Enabled</label>
                                            <?php if (!empty($row['errors'])): ?>
                                                <div class="mt-2 text-xs text-red-300"><?= e(implode(' ', $row['errors'])); ?></div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button class="mt-4 rounded-lg bg-cyan-400 px-5 py-3 font-semibold text-slate-950" type="submit">Save Selected Plans</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="mt-6 rounded-2xl border border-emerald-300/20 bg-emerald-950/20 p-4">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-white">Latest Saved Provider Plans</h3>
                    <p class="text-sm text-slate-400">Fresh rows from <span class="font-mono">provider_service_plans</span>, newest first.</p>
                </div>
            </div>
            <div class="table-shell mt-4">
                <table>
                    <thead><tr class="text-slate-400"><th>ID</th><th>Provider</th><th>Service</th><th>Network</th><th>Local Plan</th><th>Provider Plan</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if ($latestProviderPlanMappings === []): ?>
                        <tr><td colspan="8" class="text-slate-400">No saved provider plan rows yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($latestProviderPlanMappings as $mapping): ?>
                        <tr>
                            <td class="font-mono">#<?= (int) $mapping['id']; ?></td>
                            <td><?= e($mapping['provider_name'] . ' [' . $mapping['provider_code'] . ']'); ?></td>
                            <td><?= e($mapping['service_name'] . ' [' . $mapping['service_slug'] . ']'); ?></td>
                            <td><?= e((string) ($mapping['network_code'] ?? 'default')); ?></td>
                            <td><?= e($mapping['local_plan_code'] . ' - ' . $mapping['local_plan_name']); ?></td>
                            <td><?= e($mapping['provider_plan_id'] . ((string) ($mapping['provider_plan_name'] ?? '') !== '' ? ' - ' . $mapping['provider_plan_name'] : '')); ?></td>
                            <td><?= e(money($mapping['amount'])); ?></td>
                            <td><?= (int) $mapping['is_enabled'] === 1 ? 'Enabled' : 'Disabled'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="table-shell mt-6">
            <table>
                <thead><tr class="text-slate-400"><th>Provider</th><th>Service</th><th>Network</th><th>Local Plan</th><th>Provider Plan</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if ($providerPlanMappings === []): ?>
                    <tr><td colspan="8" class="text-slate-400">No provider plans are mapped yet. Use Sync, CSV, Bulk Paste, or Manual Add above.</td></tr>
                <?php endif; ?>
                <?php foreach ($providerPlanMappings as $mapping): ?>
                    <tr>
                        <td><?= e($mapping['provider_name']); ?></td>
                        <td><?= e($mapping['service_name']); ?></td>
                        <td colspan="5">
                            <form method="post" class="grid gap-2 xl:grid-cols-7">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="save_provider_plan">
                                <input type="hidden" name="provider_account_id" value="<?= (int) $mapping['provider_account_id']; ?>">
                                <input type="hidden" name="service_id" value="<?= (int) $mapping['service_id']; ?>">
                                <input class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2" name="network_code" value="<?= e((string) ($mapping['network_code'] ?? '')); ?>" placeholder="network">
                                <input class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2" name="local_plan_code" value="<?= e($mapping['local_plan_code']); ?>" placeholder="local code">
                                <input class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2" name="local_plan_name" value="<?= e($mapping['local_plan_name']); ?>" placeholder="local name">
                                <input class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2 font-mono" name="provider_plan_id" value="<?= e($mapping['provider_plan_id']); ?>" placeholder="provider ID">
                                <input class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2" name="provider_plan_name" value="<?= e((string) ($mapping['provider_plan_name'] ?? '')); ?>" placeholder="provider name">
                                <input class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2" name="amount" value="<?= e((string) $mapping['amount']); ?>" placeholder="amount">
                                <label class="flex items-center gap-2 rounded-lg border border-white/10 px-3 py-2 text-sm text-slate-300"><input type="checkbox" name="is_enabled" value="1"<?= (int) $mapping['is_enabled'] === 1 ? ' checked' : ''; ?>> Enabled</label>
                                <button class="rounded-lg border border-cyan-300/40 px-3 py-2 text-sm font-semibold text-cyan-200 xl:col-span-3" type="submit">Save Edit</button>
                            </form>
                        </td>
                        <td>
                            <form method="post" onsubmit="return confirm('Delete this provider plan mapping?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete_provider_plan">
                                <input type="hidden" name="mapping_id" value="<?= (int) $mapping['id']; ?>">
                                <button class="rounded-lg border border-red-400/40 px-3 py-2 text-sm font-semibold text-red-200" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php render_footer(); ?>
