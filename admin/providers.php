<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('providers.manage');
$providers = app(\GemData\Classes\ProviderManager::class);
if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $providers->upsertProvider($_POST);
        app(\GemData\Classes\ActivityLogger::class)->log('admin', (int) $admin['id'], 'provider_switch', 'Updated provider configuration.', [
            'code' => $_POST['code'] ?? '',
            'status' => $_POST['status'] ?? 'active',
            'driver' => $_POST['driver'] ?? 'mock',
        ]);
        flash('success', 'Provider saved successfully.');
    } elseif ($action === 'test') {
        $result = $providers->testConnection((int) $_POST['provider_id']);
        flash('success', $result['message'] . ' (' . $result['provider'] . ') health=' . ($result['health']['status'] ?? 'unknown'));
    }
    redirect(base_url('admin/providers.php'));
}
$rows = $providers->allProviders();
render_header('Providers', 'admin');
?>
<div class="space-y-6">
    <?php if ($message = flash('success')): ?><div class="notice notice-success"><?= e($message); ?></div><?php endif; ?>
    <section class="surface-card p-6">
        <h1 class="text-3xl font-black text-white">Provider Management</h1>
        <form method="post" class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <input type="hidden" name="action" value="save">
            <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="code" placeholder="provider code">
            <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="name" placeholder="provider name">
            <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="driver">
                <option value="smeplug">SMEPlug</option>
                <option value="vtpass">VTpass</option>
                <option value="clubkonnect">ClubKonnect</option>
                <option value="alrahuzdata">AlrahuzData</option>
                <option value="easyaccessapi">EasyAccessAPI</option>
                <option value="mock">Mock</option>
            </select>
            <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="priority_order" placeholder="priority">
            <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="credentials_key" placeholder="credentials key">
            <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3 md:col-span-2" name="supported_services" placeholder="comma-separated supported services">
            <input class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="low_balance_threshold" placeholder="low balance threshold">
            <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="supports_fallback" value="1" checked> Supports fallback</label>
            <button class="primary-action" type="submit">Save Provider</button>
        </form>
    </section>
    <section class="surface-card p-6">
        <div class="table-shell">
            <table>
                <thead><tr class="text-slate-400"><th>Name</th><th>Code</th><th>Driver</th><th>Status</th><th>Priority</th><th>Threshold</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['name']); ?></td>
                            <td><?= e($row['code']); ?></td>
                            <td><?= e($row['driver']); ?></td>
                            <td><?= e($row['status']); ?></td>
                            <td><?= (int) $row['priority_order']; ?></td>
                            <td><?= e(money($row['low_balance_threshold'] ?? 0)); ?></td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="test">
                                    <input type="hidden" name="provider_id" value="<?= (int) $row['id']; ?>">
                                    <button class="rounded-lg border border-white/10 px-3 py-2 text-sm font-semibold" type="submit">Test Connection</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php render_footer(); ?>
