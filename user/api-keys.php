<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
app(\GemData\Classes\RoleMiddleware::class)->requireRole($user, 'api');
$apiUser = db()->first('SELECT * FROM api_users WHERE user_id = :user_id LIMIT 1', ['user_id' => $user['id']]);

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'regenerate_key') {
            if (!$apiUser || ($apiUser['status'] ?? '') !== 'active') {
                throw new RuntimeException('Your API account is not active yet.');
            }
            $credential = app(\GemData\Classes\ApiCredentialService::class)->generateForApiUser((int) $apiUser['id']);
            app(\GemData\Classes\ActivityLogger::class)->log(
                'user',
                (int) $user['id'],
                'api_key_regenerated',
                'API user regenerated credentials.',
                ['api_user_id' => (int) $apiUser['id']]
            );
            flash('generated_api_key', (string) $credential['api_key']);
            flash('generated_api_secret', (string) $credential['secret']);
            flash('success', 'New API credentials generated. Copy the secret now; it will not be shown again.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
    redirect(base_url('user/api-keys.php'));
}

$keys = $apiUser ? db()->safeQuery('SELECT * FROM api_keys WHERE api_user_id = :api_user_id ORDER BY created_at DESC', ['api_user_id' => $apiUser['id']]) : [];

render_header('API Keys', 'user');
?>
<div class="space-y-6">
    <div class="stagger-1">
        <h1 class="text-2xl font-extrabold text-gem-text">API Keys</h1>
        <p class="text-[14px] text-gem-muted mt-0.5">Manage secure API credentials for approved API access.</p>
    </div>

    <?php if ($secret = flash('generated_api_secret')): ?>
        <div class="bg-green-50 border border-green-100 text-gem-green rounded-2xl px-5 py-4 text-[13px]">
            <strong>New API credentials</strong><br>
            <?php if ($apiKey = flash('generated_api_key')): ?>
                <span class="block mt-2 text-gem-text">API Key</span>
                <span class="font-mono break-all"><?= e($apiKey); ?></span>
            <?php endif; ?>
            <span class="block mt-2 text-gem-text">API Secret</span>
            <span class="font-mono break-all"><?= e($secret); ?></span>
            <span class="block mt-2 text-[12px]">Copy this secret now. It will not be shown again.</span>
        </div>
    <?php endif; ?>
    <?php if ($message = flash('success')): ?>
        <div class="notice notice-success"><?= e($message); ?></div>
    <?php endif; ?>
    <?php if ($message = flash('error')): ?>
        <div class="notice notice-error"><?= e($message); ?></div>
    <?php endif; ?>

    <section class="bg-white rounded-2xl shadow-card border border-gem-border p-5 stagger-2">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-[16px] font-bold text-gem-text">API Credentials</h2>
                <p class="text-[13px] text-gem-muted mt-1">Generate a fresh key and one-time secret for API authentication.</p>
            </div>
            <form method="post" data-loading-form>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="regenerate_key">
                <button class="primary-action" type="submit" data-loading-label="Generating..." onclick="return confirm('Generate new API credentials? Existing active keys will be revoked.');">
                    Generate / Regenerate
                </button>
            </form>
        </div>
    </section>

    <section class="bg-white rounded-2xl shadow-card border border-gem-border overflow-hidden stagger-3">
        <div class="hidden sm:grid grid-cols-4 gap-4 px-5 py-3 bg-gem-gray border-b border-gem-border text-[11px] font-bold text-gem-muted uppercase tracking-wider">
            <div class="col-span-2">API Key</div><div>Status</div><div>Created</div>
        </div>
        <div class="divide-y divide-gem-border">
            <?php if ($keys === []): ?>
                <div class="px-5 py-8 text-center text-[13px] text-gem-muted">No API keys have been generated yet.</div>
            <?php endif; ?>
            <?php foreach ($keys as $key): ?>
                <?php $masked = substr((string) $key['api_key'], 0, 8) . '...' . substr((string) $key['api_key'], -6); ?>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 sm:gap-4 px-5 py-4 hover:bg-gem-gray/50 transition-colors">
                    <div class="col-span-2"><div class="text-[13px] font-semibold text-gem-text font-mono"><?= e($masked); ?></div><div class="text-[11px] text-gem-muted">Secret is never shown after generation</div></div>
                    <div class="sm:flex sm:items-center"><span class="inline-flex items-center gap-1 bg-green-50 text-gem-green text-[11px] font-semibold px-2.5 py-1 rounded-full"><?= e(ucfirst((string) $key['status'])); ?></span></div>
                    <div class="sm:flex sm:items-center"><span class="text-[12px] text-gem-muted"><?= e(human_datetime((string) $key['created_at'])); ?></span></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<?php render_footer(); ?>
