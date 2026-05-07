<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('settings.manage');
$settings = app(\GemData\Classes\SettingsService::class);
if (is_post()) {
    verify_csrf();
    $map = [
        'site_name' => 'general',
        'site_logo' => 'general',
        'maintenance_mode' => 'general',
        'maintenance_message' => 'general',
        'charge_default_percent' => 'billing',
        'referral_enabled' => 'referrals',
        'referral_default_rate' => 'referrals',
        'auto_retry_enabled' => 'automation',
        'low_balance_alert_threshold' => 'alerts',
    ];
    foreach ($map as $key => $group) {
        $value = (string) ($_POST[$key] ?? ($key === 'maintenance_mode' || $key === 'referral_enabled' || $key === 'auto_retry_enabled' ? '0' : ''));
        $settings->set($key, $value, $group, (int) $admin['id']);
    }
    app(\GemData\Classes\ActivityLogger::class)->log('admin', (int) $admin['id'], 'system_settings_updated', 'Admin updated system settings.');
    flash('success', 'Settings updated successfully.');
    redirect(base_url('admin/settings.php'));
}
$all = $settings->all();
render_header('Settings', 'admin');
?>
<div class="surface-card p-6">
    <?php if ($message = flash('success')): ?><div class="notice notice-success"><?= e($message); ?></div><?php endif; ?>
    <h1 class="text-3xl font-black text-white">System Settings</h1>
    <form method="post" class="mt-6 settings-grid">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <label>Site name <input name="site_name" value="<?= e($all['site_name'] ?? ''); ?>"></label>
        <label>Logo path <input name="site_logo" value="<?= e($all['site_logo'] ?? ''); ?>"></label>
        <label>Maintenance mode <select name="maintenance_mode"><option value="0"<?= ($all['maintenance_mode'] ?? '0') === '0' ? ' selected' : ''; ?>>Off</option><option value="1"<?= ($all['maintenance_mode'] ?? '0') === '1' ? ' selected' : ''; ?>>On</option></select></label>
        <label>Maintenance message <input name="maintenance_message" value="<?= e($all['maintenance_message'] ?? ''); ?>"></label>
        <label>Default charge percent <input name="charge_default_percent" value="<?= e($all['charge_default_percent'] ?? '0'); ?>"></label>
        <label>Referral enabled <select name="referral_enabled"><option value="0"<?= ($all['referral_enabled'] ?? '0') === '0' ? ' selected' : ''; ?>>No</option><option value="1"<?= ($all['referral_enabled'] ?? '0') === '1' ? ' selected' : ''; ?>>Yes</option></select></label>
        <label>Referral default rate <input name="referral_default_rate" value="<?= e($all['referral_default_rate'] ?? '0'); ?>"></label>
        <label>Auto retry enabled <select name="auto_retry_enabled"><option value="0"<?= ($all['auto_retry_enabled'] ?? '0') === '0' ? ' selected' : ''; ?>>No</option><option value="1"<?= ($all['auto_retry_enabled'] ?? '0') === '1' ? ' selected' : ''; ?>>Yes</option></select></label>
        <label>Low balance alert threshold <input name="low_balance_alert_threshold" value="<?= e($all['low_balance_alert_threshold'] ?? '5000'); ?>"></label>
        <button class="primary-action" type="submit">Save Settings</button>
    </form>
</div>
<?php render_footer(); ?>
