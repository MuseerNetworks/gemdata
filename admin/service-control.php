<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('services.manage');

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $serviceId = (int) ($_POST['service_id'] ?? 0);

    if ($action === 'toggle_service' && $serviceId > 0) {
        $newEnabled = (int) ($_POST['enabled'] ?? 0);
        db()->execute('UPDATE services SET is_enabled = :enabled WHERE id = :id', ['enabled' => $newEnabled, 'id' => $serviceId]);
        app(\GemData\Classes\ActivityLogger::class)->log('admin', (int) $admin['id'], 'service_toggled', 'Service ' . ($newEnabled ? 'enabled' : 'disabled') . '.', ['service_id' => $serviceId]);
        flash('success', 'Service ' . ($newEnabled ? 'enabled' : 'disabled') . '.');
    }

    if ($action === 'set_maintenance' && $serviceId > 0) {
        $maintenanceMsg = trim((string) ($_POST['maintenance_message'] ?? ''));
        if ($maintenanceMsg !== '') {
            db()->execute('UPDATE services SET is_enabled = 0, maintenance_message = :msg WHERE id = :id', ['msg' => $maintenanceMsg, 'id' => $serviceId]);
            app(\GemData\Classes\ActivityLogger::class)->log('admin', (int) $admin['id'], 'service_maintenance', 'Service put under maintenance.', ['service_id' => $serviceId, 'message' => $maintenanceMsg]);
            flash('success', 'Service put under maintenance.');
        } else {
            // Clear maintenance and re-enable
            db()->execute('UPDATE services SET is_enabled = 1, maintenance_message = NULL WHERE id = :id', ['id' => $serviceId]);
            app(\GemData\Classes\ActivityLogger::class)->log('admin', (int) $admin['id'], 'service_maintenance_cleared', 'Service maintenance cleared and re-enabled.', ['service_id' => $serviceId]);
            flash('success', 'Maintenance cleared and service re-enabled.');
        }
    }

    redirect(base_url('admin/service-control.php'));
}

$services = db()->query('SELECT * FROM services ORDER BY name');

render_header('Service Control', 'admin');
?>
<div class="space-y-6">
    <?php if ($message = flash('success')): ?><div class="notice notice-success"><?= e($message); ?></div><?php endif; ?>

    <section class="surface-card p-6">
        <h1 class="text-3xl font-black text-white">Service Availability Control</h1>
        <p class="mt-2 text-slate-400">Enable, disable, or put services under maintenance with a custom message. Changes take effect immediately.</p>
        <div class="table-shell mt-6">
            <table>
                <thead>
                    <tr class="text-slate-400">
                        <th>Service</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Maintenance Message</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <?php
                        $isEnabled = (int) $service['is_enabled'] === 1;
                        $maintenanceMsg = $service['maintenance_message'] ?? '';
                        $isUnderMaintenance = !$isEnabled && $maintenanceMsg !== '';
                        ?>
                        <tr data-search-item data-search="<?= e($service['name'] . ' ' . $service['slug'] . ' ' . $service['category']); ?>">
                            <td>
                                <strong class="text-white"><?= e($service['name']); ?></strong>
                                <div class="text-xs text-slate-400 mt-1"><?= e($service['slug']); ?></div>
                            </td>
                            <td><?= e(ucfirst($service['category'])); ?></td>
                            <td>
                                <?php if ($isUnderMaintenance): ?>
                                    <span class="rounded-full bg-amber-500/20 px-2.5 py-1 text-xs font-bold text-amber-300">Maintenance</span>
                                <?php elseif ($isEnabled): ?>
                                    <span class="rounded-full bg-emerald-500/20 px-2.5 py-1 text-xs font-bold text-emerald-300">Active</span>
                                <?php else: ?>
                                    <span class="rounded-full bg-rose-500/20 px-2.5 py-1 text-xs font-bold text-rose-300">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($maintenanceMsg): ?>
                                    <span class="text-sm text-amber-200"><?= e($maintenanceMsg); ?></span>
                                <?php else: ?>
                                    <span class="text-sm text-slate-500">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-2">
                                    <!-- Toggle Enable/Disable -->
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="toggle_service">
                                        <input type="hidden" name="service_id" value="<?= (int) $service['id']; ?>">
                                        <input type="hidden" name="enabled" value="<?= $isEnabled ? 0 : 1; ?>">
                                        <button class="rounded-lg px-3 py-1.5 text-xs font-semibold <?= $isEnabled ? 'border border-rose-400/30 text-rose-400 hover:bg-rose-500/10' : 'border border-emerald-400/30 text-emerald-400 hover:bg-emerald-500/10'; ?>" type="submit">
                                            <?= $isEnabled ? 'Disable' : 'Enable'; ?>
                                        </button>
                                    </form>
                                    <!-- Maintenance toggle -->
                                    <form method="post" class="flex items-center gap-2">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="set_maintenance">
                                        <input type="hidden" name="service_id" value="<?= (int) $service['id']; ?>">
                                        <?php if ($isUnderMaintenance): ?>
                                            <button class="rounded-lg border border-cyan-400/30 px-3 py-1.5 text-xs font-semibold text-cyan-400 hover:bg-cyan-500/10" type="submit">Clear Maintenance</button>
                                        <?php else: ?>
                                            <input class="w-48 rounded-lg border border-white/10 bg-slate-900 px-2 py-1.5 text-xs" name="maintenance_message" placeholder="Maintenance reason...">
                                            <button class="rounded-lg border border-amber-400/30 px-3 py-1.5 text-xs font-semibold text-amber-400 hover:bg-amber-500/10" type="submit">Set Maintenance</button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php render_footer(); ?>
