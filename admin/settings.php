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
        'active_funding_provider' => 'funding',
        'multi_provider_funding' => 'funding',
        'funding_provider_katpay_user_display_enabled' => 'funding',
        'funding_provider_paystack_user_display_enabled' => 'funding',
        'funding_provider_xixapay_user_display_enabled' => 'funding',
    ];
    foreach ($map as $key => $group) {
        $isToggle = in_array($key, [
            'maintenance_mode',
            'referral_enabled',
            'auto_retry_enabled',
            'multi_provider_funding',
            'funding_provider_katpay_user_display_enabled',
            'funding_provider_paystack_user_display_enabled',
            'funding_provider_xixapay_user_display_enabled',
        ], true);
        $value = (string) ($_POST[$key] ?? ($isToggle ? '0' : ''));
        if ($key === 'active_funding_provider' && !in_array($value, ['katpay', 'paystack', 'xixapay'], true)) {
            $value = 'katpay';
        }
        $settings->set($key, $value, $group, (int) $admin['id']);
    }
    app(\GemData\Classes\ActivityLogger::class)->log('admin', (int) $admin['id'], 'system_settings_updated', 'Admin updated system settings.');
    flash('success', 'Settings updated successfully.');
    redirect(base_url('admin/settings.php'));
}
$all = $settings->all();
$activeFundingProvider = (string) ($all['active_funding_provider'] ?? config('payments.active_funding_provider', 'katpay'));
$multiProviderFunding = (string) ($all['multi_provider_funding'] ?? ((bool) config('payments.multi_provider_funding', false) ? '1' : '0'));
$katpayDisplay = (string) ($all['funding_provider_katpay_user_display_enabled'] ?? '1');
$paystackDisplay = (string) ($all['funding_provider_paystack_user_display_enabled'] ?? '0');
$xixapayDisplay = (string) ($all['funding_provider_xixapay_user_display_enabled'] ?? '0');
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
        <label>Active funding provider <select name="active_funding_provider"><option value="katpay"<?= $activeFundingProvider === 'katpay' ? ' selected' : ''; ?>>KatPay</option><option value="paystack"<?= $activeFundingProvider === 'paystack' ? ' selected' : ''; ?>>Paystack</option><option value="xixapay"<?= $activeFundingProvider === 'xixapay' ? ' selected' : ''; ?>>XixaPay</option></select></label>
        <label>Multi-provider funding <select name="multi_provider_funding"><option value="0"<?= $multiProviderFunding === '0' ? ' selected' : ''; ?>>Off</option><option value="1"<?= $multiProviderFunding === '1' ? ' selected' : ''; ?>>On</option></select></label>
        <label>Show KatPay to users <select name="funding_provider_katpay_user_display_enabled"><option value="1"<?= $katpayDisplay === '1' ? ' selected' : ''; ?>>Yes</option><option value="0"<?= $katpayDisplay === '0' ? ' selected' : ''; ?>>No</option></select></label>
        <label>Show Paystack to users <select name="funding_provider_paystack_user_display_enabled"><option value="0"<?= $paystackDisplay === '0' ? ' selected' : ''; ?>>No</option><option value="1"<?= $paystackDisplay === '1' ? ' selected' : ''; ?>>Yes</option></select></label>
        <label>Show XixaPay to users <select name="funding_provider_xixapay_user_display_enabled"><option value="0"<?= $xixapayDisplay === '0' ? ' selected' : ''; ?>>No</option><option value="1"<?= $xixapayDisplay === '1' ? ' selected' : ''; ?>>Yes</option></select></label>
        <button class="primary-action" type="submit">Save Settings</button>
    </form>
</div>
<?php render_footer(); ?>
