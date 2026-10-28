<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$codes = $_SESSION['admin_2fa_recovery_codes'] ?? [];
unset($_SESSION['admin_2fa_recovery_codes']);
if (!is_array($codes) || $codes === []) { redirect(base_url('admin/dashboard.php')); }
render_header('Save Recovery Codes', 'admin');
?>
<div class="mx-auto max-w-2xl space-y-6"><section class="surface-card p-6"><p class="eyebrow">Admin security</p><h1 class="mt-2 text-3xl font-black text-white">Save your recovery codes</h1><p class="mt-3 text-slate-300">Each code works once if you lose your authenticator. Store them in a password manager. They will not be shown again.</p><div class="mt-6 grid gap-2 sm:grid-cols-2"><?php foreach ($codes as $code): ?><code class="rounded-lg bg-slate-900 px-4 py-3 text-center text-base text-cyan-300"><?= e($code); ?></code><?php endforeach; ?></div><div class="mt-6 flex flex-wrap gap-3"><button class="secondary-action" type="button" data-copy-value="<?= e(implode("\n", $codes)); ?>">Copy codes</button><button class="secondary-action" type="button" id="download-recovery-codes">Download codes</button><a class="primary-action" href="<?= e(base_url('admin/dashboard.php')); ?>">I saved my codes</a></div></section></div>
<script nonce="<?= e(csp_nonce()); ?>">document.getElementById('download-recovery-codes').addEventListener('click',function(){var t=<?= json_encode("GemData recovery codes\nStore securely. Each code works once.\n\n" . implode("\n", $codes)); ?>,a=document.createElement('a');a.href=URL.createObjectURL(new Blob([t],{type:'text/plain'}));a.download='gemdata-recovery-codes.txt';a.click();URL.revokeObjectURL(a.href);});</script>
<?php render_footer(); ?>
