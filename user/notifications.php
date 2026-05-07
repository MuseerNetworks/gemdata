<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
db()->execute('UPDATE notifications SET is_read = 1 WHERE user_id = :user_id', ['user_id' => $user['id']]);
$rows = db()->query('SELECT * FROM notifications WHERE user_id = :user_id ORDER BY id DESC', ['user_id' => $user['id']]);

render_header('Notifications', 'user');
?>
<div class="surface-card p-6">
    <p class="eyebrow">Notifications</p>
    <h1 class="surface-section-title">Notifications</h1>
    <div class="mt-6 space-y-3">
        <?php foreach ($rows as $row): ?>
            <article class="rounded-xl border border-white/10 bg-slate-900/70 p-4" data-search-item data-search="<?= e($row['title'] . ' ' . $row['message'] . ' ' . $row['type']); ?>">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold"><?= e($row['title']); ?></h2>
                    <span class="text-xs uppercase tracking-[0.2em] text-slate-400"><?= e($row['type']); ?></span>
                </div>
                <p class="mt-2 text-slate-300"><?= e($row['message']); ?></p>
                <p class="mt-2"><span class="timestamp" title="<?= e($row['created_at']); ?>"><?= e(human_datetime((string) $row['created_at'])); ?></span></p>
            </article>
        <?php endforeach; ?>
    </div>
</div>
<?php render_footer(); ?>
