<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('providers.manage');
$providers = app(\GemData\Classes\ProviderManager::class);
$logger = app(\GemData\Classes\ActivityLogger::class);

function mask_provider_secret_ref(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return 'Not set';
    }

    if (strlen($value) <= 6) {
        return substr($value, 0, 1) . '****';
    }

    return substr($value, 0, 3) . '****' . substr($value, -2);
}

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $providers->upsertProvider($_POST);
        $logger->log('admin', (int) $admin['id'], 'provider_saved', 'Admin saved provider safeguards.', [
            'code' => $_POST['code'] ?? '',
            'status' => $_POST['status'] ?? 'active',
            'driver' => $_POST['driver'] ?? 'mock',
            'sandbox_mode' => !empty($_POST['sandbox_mode']),
            'cheapest_routing_enabled' => !empty($_POST['cheapest_routing_enabled']),
        ]);
        flash('success', 'Provider saved successfully.');
    } elseif ($action === 'test') {
        $result = $providers->testConnection((int) $_POST['provider_id']);
        $logger->log('admin', (int) $admin['id'], 'provider_tested', 'Admin tested provider connection.', [
            'provider_id' => (int) $_POST['provider_id'],
            'status' => $result['status'] ?? 'unknown',
        ]);
        flash('success', $result['message'] . ' (' . $result['provider'] . ') health=' . ($result['health']['status'] ?? 'unknown'));
    } elseif ($action === 'status') {
        $providerId = (int) ($_POST['provider_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'inactive');
        $providers->updateProviderStatus($providerId, $status);
        $logger->log('admin', (int) $admin['id'], 'provider_status_changed', 'Admin changed provider status.', [
            'provider_id' => $providerId,
            'status' => $status,
        ]);
        flash('success', 'Provider status updated.');
    } elseif ($action === 'reset_circuit') {
        $providerId = (int) ($_POST['provider_id'] ?? 0);
        $providers->resetCircuitBreaker($providerId);
        $logger->log('admin', (int) $admin['id'], 'provider_circuit_reset', 'Admin reset provider circuit breaker.', [
            'provider_id' => $providerId,
        ]);
        flash('success', 'Provider circuit breaker reset.');
    } elseif ($action === 'routing_setting') {
        $providers->upsertRoutingSetting($_POST, (int) $admin['id']);
        $logger->log('admin', (int) $admin['id'], 'provider_routing_setting_saved', 'Admin saved provider routing setting.', [
            'service_slug' => $_POST['service_slug'] ?? '__global__',
            'routing_mode' => $_POST['routing_mode'] ?? 'priority',
            'manual_provider_account_id' => $_POST['manual_provider_account_id'] ?? null,
        ]);
        flash('success', 'Routing controls saved.');
    }

    redirect(base_url('admin/providers.php'));
}

$rows = $providers->allProviders();
$services = db()->query('SELECT slug, name FROM services ORDER BY name ASC');
$routingSettings = $providers->routingSettings();
render_header('Providers', 'admin');
?>
<div class="space-y-6">
    <?php if ($message = flash('success')): ?><div class="notice notice-success"><?= e($message); ?></div><?php endif; ?>

    <section class="rounded-2xl border border-gem-border bg-white p-5 shadow-card">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-widest text-gem-blue">Provider Operations</p>
                <h1 class="mt-1 text-2xl font-extrabold text-gem-text">Provider Management</h1>
                <p class="mt-1 text-[14px] text-gem-muted">Configure provider status, fallback safety, sandbox mode, health thresholds, and circuit-breaker safeguards.</p>
            </div>
            <span class="rounded-full bg-gem-blueLt px-3 py-1.5 text-[12px] font-bold text-gem-blue"><?= count($rows); ?> providers</span>
        </div>

        <form method="post" class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <input type="hidden" name="action" value="save">
            <input class="rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text focus:border-gem-blue focus:outline-none focus:ring-2 focus:ring-gem-blue/10" name="code" placeholder="provider code" required>
            <input class="rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text focus:border-gem-blue focus:outline-none focus:ring-2 focus:ring-gem-blue/10" name="name" placeholder="provider name" required>
            <select class="rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text" name="driver">
                <option value="albani">AlbaniAPI</option>
                <option value="smeplug">SMEPlug</option>
                <option value="vtpass">VTpass</option>
                <option value="clubkonnect">ClubKonnect</option>
                <option value="alrahuzdata">AlrahuzData</option>
                <option value="easyaccessapi">EasyAccessAPI</option>
                <option value="mock">Mock</option>
            </select>
            <select class="rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text" name="status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="maintenance">Maintenance</option>
                <option value="archived">Archived</option>
            </select>
            <input class="rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text" name="priority_order" placeholder="priority" value="1">
            <input class="rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text" name="credentials_key" placeholder="credentials key reference">
            <input class="rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text md:col-span-2" name="base_url" placeholder="base URL override">
            <input class="rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text md:col-span-2" name="supported_services" placeholder="comma-separated supported services">
            <input class="rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text" name="low_balance_threshold" placeholder="low balance threshold">
            <input class="rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text" name="minimum_success_rate" placeholder="min success %" value="80">
            <input class="rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text" name="failure_threshold" placeholder="failure threshold" value="5">
            <input class="rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text" name="health_score" placeholder="health score" value="100">
            <label class="flex items-center gap-2 rounded-xl border border-gem-border bg-white px-4 py-3 text-[13px] font-semibold text-gem-muted"><input type="checkbox" name="supports_fallback" value="1" checked> Fallback</label>
            <label class="flex items-center gap-2 rounded-xl border border-gem-border bg-white px-4 py-3 text-[13px] font-semibold text-gem-muted"><input type="checkbox" name="cheapest_routing_enabled" value="1"> Cheapest routing</label>
            <label class="flex items-center gap-2 rounded-xl border border-gem-border bg-white px-4 py-3 text-[13px] font-semibold text-gem-muted"><input type="checkbox" name="sandbox_mode" value="1"> Sandbox mode</label>
            <label class="flex items-center gap-2 rounded-xl border border-gem-border bg-white px-4 py-3 text-[13px] font-semibold text-gem-muted"><input type="checkbox" name="auto_disable_enabled" value="1" checked> Auto-disable</label>
            <button class="rounded-xl bg-gem-blue px-5 py-3 text-[13px] font-bold text-white shadow-panel hover:bg-gem-blueDk md:col-span-2 xl:col-span-4" type="submit">Save Provider</button>
        </form>
    </section>

    <section class="rounded-2xl border border-gem-border bg-white p-5 shadow-card">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-widest text-gem-blue">Routing Engine</p>
                <h2 class="mt-1 text-xl font-extrabold text-gem-text">Provider Router Controls</h2>
                <p class="mt-1 text-[14px] text-gem-muted">Provider choices stay hidden from users. These controls decide backend routing, fallback, cheapest routing, and health weighting.</p>
            </div>
        </div>
        <form method="post" class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <input type="hidden" name="action" value="routing_setting">
            <select class="rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text" name="service_slug">
                <option value="__global__">Global default</option>
                <?php foreach ($services as $service): ?>
                    <option value="<?= e($service['slug']); ?>"><?= e($service['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select class="rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text" name="routing_mode">
                <option value="priority">Priority routing</option>
                <option value="manual">Manual provider</option>
                <option value="cheapest">Cheapest provider</option>
                <option value="cheapest_health">Cheapest + health weighting</option>
            </select>
            <select class="rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text" name="manual_provider_account_id">
                <option value="">Manual provider (optional)</option>
                <?php foreach ($rows as $row): ?>
                    <?php if (($row['status'] ?? '') !== 'archived'): ?>
                        <option value="<?= (int) $row['id']; ?>"><?= e($row['name']); ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <input class="rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text" name="minimum_success_rate" value="80" placeholder="Minimum success %">
            <input class="rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text" name="health_weight" value="30" placeholder="Health weight">
            <input class="rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text" name="cost_weight" value="70" placeholder="Cost weight">
            <label class="flex items-center gap-2 rounded-xl border border-gem-border bg-white px-4 py-3 text-[13px] font-semibold text-gem-muted"><input type="checkbox" name="fallback_enabled" value="1" checked> Enable fallback</label>
            <button class="rounded-xl bg-gem-blue px-5 py-3 text-[13px] font-bold text-white shadow-panel hover:bg-gem-blueDk" type="submit">Save Routing</button>
        </form>

        <div class="mt-5 overflow-hidden rounded-2xl border border-gem-border">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gem-border text-left text-[13px]">
                    <thead class="bg-gem-gray text-[11px] uppercase tracking-widest text-gem-muted">
                        <tr><th class="px-4 py-3">Scope</th><th class="px-4 py-3">Mode</th><th class="px-4 py-3">Manual Provider</th><th class="px-4 py-3">Fallback</th><th class="px-4 py-3">Thresholds</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gem-border bg-white">
                    <?php if ($routingSettings === []): ?>
                        <tr><td class="px-4 py-4 text-gem-muted" colspan="5">No custom routing settings yet. Priority routing with fallback is active by default.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($routingSettings as $setting): ?>
                        <tr>
                            <td class="px-4 py-4 font-bold text-gem-text"><?= e($setting['service_slug'] ?: 'Global default'); ?></td>
                            <td class="px-4 py-4"><span class="rounded-full bg-gem-blueLt px-2.5 py-1 text-[11px] font-bold text-gem-blue"><?= e($setting['routing_mode']); ?></span></td>
                            <td class="px-4 py-4 text-gem-muted"><?= e($setting['provider_name'] ?? 'Automatic'); ?></td>
                            <td class="px-4 py-4 text-gem-muted"><?= !empty($setting['fallback_enabled']) ? 'Enabled' : 'Disabled'; ?></td>
                            <td class="px-4 py-4 text-gem-muted">Min <?= e((string) $setting['minimum_success_rate']); ?>%, health <?= e((string) $setting['health_weight']); ?>, cost <?= e((string) $setting['cost_weight']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-gem-border bg-white p-5 shadow-card">
        <div class="overflow-hidden rounded-2xl border border-gem-border">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gem-border text-left text-[13px]">
                    <thead class="bg-gem-gray text-[11px] uppercase tracking-widest text-gem-muted">
                        <tr><th class="px-4 py-3">Provider</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Routing Safety</th><th class="px-4 py-3">Balance</th><th class="px-4 py-3">Credentials</th><th class="px-4 py-3">Actions</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gem-border bg-white">
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $supported = implode(', ', json_decode_array($row['supported_services_json'] ?? '[]'));
                            $status = (string) ($row['status'] ?? 'inactive');
                            ?>
                            <tr>
                                <td class="px-4 py-4 align-top">
                                    <div class="font-bold text-gem-text"><?= e($row['name']); ?></div>
                                    <div class="mt-1 text-[12px] text-gem-muted"><?= e($row['code']); ?> | <?= e($row['driver']); ?></div>
                                    <div class="mt-1 text-[11px] text-gem-muted"><?= e($supported !== '' ? $supported : 'All mapped services'); ?></div>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold uppercase <?= $status === 'active' ? 'bg-green-50 text-gem-green' : ($status === 'maintenance' ? 'bg-orange-50 text-gem-orange' : ($status === 'archived' ? 'bg-slate-100 text-slate-500' : 'bg-red-50 text-gem-red')); ?>"><?= e($status); ?></span>
                                    <div class="mt-2 text-[11px] text-gem-muted">Priority <?= (int) $row['priority_order']; ?></div>
                                    <?php if (!empty($row['sandbox_mode'])): ?><div class="mt-1 text-[11px] font-bold text-gem-blue">Sandbox</div><?php endif; ?>
                                </td>
                                <td class="px-4 py-4 align-top text-[12px] text-gem-muted">
                                    <div>Fallback: <strong><?= !empty($row['supports_fallback']) ? 'On' : 'Off'; ?></strong></div>
                                    <div>Cheapest: <strong><?= !empty($row['cheapest_routing_enabled']) ? 'On' : 'Off'; ?></strong></div>
                                    <div>Min success: <strong><?= e((string) ($row['minimum_success_rate'] ?? '80.00')); ?>%</strong></div>
                                    <div>Circuit: <strong><?= e((string) ($row['circuit_breaker_status'] ?? 'closed')); ?></strong></div>
                                </td>
                                <td class="px-4 py-4 align-top text-[12px] text-gem-muted">
                                    <div class="font-mono font-bold text-gem-text"><?= e(isset($row['current_balance']) && $row['current_balance'] !== null ? money((float) $row['current_balance']) : 'Unknown'); ?></div>
                                    <div>Threshold <?= e(money($row['low_balance_threshold'] ?? 0)); ?></div>
                                    <div>Refreshed <?= e((string) ($row['balance_refreshed_at'] ?? 'Never')); ?></div>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <div class="font-mono text-[12px] text-gem-muted"><?= e(mask_provider_secret_ref($row['credentials_key'] ?? '')); ?></div>
                                    <div class="mt-1 max-w-xs truncate text-[11px] text-gem-muted"><?= e((string) ($row['base_url'] ?? '')); ?></div>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <div class="flex flex-wrap gap-2">
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="test">
                                            <input type="hidden" name="provider_id" value="<?= (int) $row['id']; ?>">
                                            <button class="rounded-lg border border-gem-border px-3 py-2 text-[12px] font-bold text-gem-blue hover:bg-gem-blueLt" type="submit">Test</button>
                                        </form>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="status">
                                            <input type="hidden" name="provider_id" value="<?= (int) $row['id']; ?>">
                                            <input type="hidden" name="status" value="<?= $status === 'active' ? 'inactive' : 'active'; ?>">
                                            <button class="rounded-lg border border-gem-border px-3 py-2 text-[12px] font-bold text-gem-text hover:bg-gem-gray" type="submit"><?= $status === 'active' ? 'Disable' : 'Activate'; ?></button>
                                        </form>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="status">
                                            <input type="hidden" name="provider_id" value="<?= (int) $row['id']; ?>">
                                            <input type="hidden" name="status" value="maintenance">
                                            <button class="rounded-lg border border-orange-100 px-3 py-2 text-[12px] font-bold text-gem-orange hover:bg-orange-50" type="submit">Maintenance</button>
                                        </form>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="reset_circuit">
                                            <input type="hidden" name="provider_id" value="<?= (int) $row['id']; ?>">
                                            <button class="rounded-lg border border-gem-border px-3 py-2 text-[12px] font-bold text-gem-muted hover:bg-gem-gray" type="submit">Reset Circuit</button>
                                        </form>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="status">
                                            <input type="hidden" name="provider_id" value="<?= (int) $row['id']; ?>">
                                            <input type="hidden" name="status" value="archived">
                                            <button class="rounded-lg border border-red-100 px-3 py-2 text-[12px] font-bold text-gem-red hover:bg-red-50" type="submit">Archive</button>
                                        </form>
                                    </div>
                                    <details class="mt-3">
                                        <summary class="cursor-pointer text-[12px] font-bold text-gem-blue">Edit safeguards</summary>
                                        <form method="post" class="mt-3 grid gap-2">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="save">
                                            <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                            <input class="rounded-lg border border-gem-border bg-gem-gray px-3 py-2 text-[12px]" name="code" value="<?= e($row['code']); ?>">
                                            <input class="rounded-lg border border-gem-border bg-gem-gray px-3 py-2 text-[12px]" name="name" value="<?= e($row['name']); ?>">
                                            <input type="hidden" name="driver" value="<?= e($row['driver']); ?>">
                                            <select class="rounded-lg border border-gem-border bg-gem-gray px-3 py-2 text-[12px]" name="status">
                                                <?php foreach (['active', 'inactive', 'maintenance', 'archived'] as $option): ?>
                                                    <option value="<?= e($option); ?>"<?= $status === $option ? ' selected' : ''; ?>><?= e(ucfirst($option)); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input class="rounded-lg border border-gem-border bg-gem-gray px-3 py-2 text-[12px]" name="priority_order" value="<?= (int) $row['priority_order']; ?>">
                                            <input class="rounded-lg border border-gem-border bg-gem-gray px-3 py-2 text-[12px]" name="credentials_key" value="<?= e((string) ($row['credentials_key'] ?? '')); ?>">
                                            <input class="rounded-lg border border-gem-border bg-gem-gray px-3 py-2 text-[12px]" name="base_url" value="<?= e((string) ($row['base_url'] ?? '')); ?>">
                                            <input class="rounded-lg border border-gem-border bg-gem-gray px-3 py-2 text-[12px]" name="supported_services" value="<?= e($supported); ?>">
                                            <input class="rounded-lg border border-gem-border bg-gem-gray px-3 py-2 text-[12px]" name="low_balance_threshold" value="<?= e((string) ($row['low_balance_threshold'] ?? '0')); ?>">
                                            <input class="rounded-lg border border-gem-border bg-gem-gray px-3 py-2 text-[12px]" name="minimum_success_rate" value="<?= e((string) ($row['minimum_success_rate'] ?? '80')); ?>">
                                            <input class="rounded-lg border border-gem-border bg-gem-gray px-3 py-2 text-[12px]" name="failure_threshold" value="<?= e((string) ($row['failure_threshold'] ?? '5')); ?>">
                                            <input class="rounded-lg border border-gem-border bg-gem-gray px-3 py-2 text-[12px]" name="health_score" value="<?= e((string) ($row['health_score'] ?? '100')); ?>">
                                            <label class="text-[12px] text-gem-muted"><input type="checkbox" name="supports_fallback" value="1"<?= !empty($row['supports_fallback']) ? ' checked' : ''; ?>> Fallback</label>
                                            <label class="text-[12px] text-gem-muted"><input type="checkbox" name="cheapest_routing_enabled" value="1"<?= !empty($row['cheapest_routing_enabled']) ? ' checked' : ''; ?>> Cheapest routing</label>
                                            <label class="text-[12px] text-gem-muted"><input type="checkbox" name="sandbox_mode" value="1"<?= !empty($row['sandbox_mode']) ? ' checked' : ''; ?>> Sandbox mode</label>
                                            <label class="text-[12px] text-gem-muted"><input type="checkbox" name="auto_disable_enabled" value="1"<?= !array_key_exists('auto_disable_enabled', $row) || !empty($row['auto_disable_enabled']) ? ' checked' : ''; ?>> Auto-disable</label>
                                            <button class="rounded-lg bg-gem-blue px-3 py-2 text-[12px] font-bold text-white" type="submit">Save Changes</button>
                                        </form>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
<?php render_footer(); ?>
