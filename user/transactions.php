<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$countRow = db()->first('SELECT COUNT(*) AS total FROM transactions WHERE user_id = :user_id', ['user_id' => $user['id']]);
$pagination = pagination_meta((int) ($countRow['total'] ?? 0), $page, $perPage);
$rows = db()->query(
    'SELECT t.*, s.name AS service_name FROM transactions t
     INNER JOIN services s ON s.id = t.service_id
     WHERE t.user_id = :user_id ORDER BY t.id DESC
     LIMIT ' . $pagination['offset'] . ', ' . $pagination['per_page'],
    ['user_id' => $user['id']]
);

render_header('Transactions', 'user');
?>
<div class="surface-card p-6">
    <p class="eyebrow">Transactions</p>
    <h1 class="surface-section-title">Transaction History</h1>
    <p class="surface-section-copy">Submitted purchases may remain pending briefly while provider processing completes. Hover or press on a date to see the exact timestamp.</p>
    <div class="table-shell mt-6">
        <table>
            <thead>
                <tr class="text-slate-400">
                    <th>Reference</th>
                    <th>Service</th>
                    <th>Status</th>
                    <th>Channel</th>
                    <th>Amount</th>
                    <th>Commission</th>
                    <th>Recipient</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr data-search-item data-search="<?= e($row['reference'] . ' ' . $row['service_name'] . ' ' . $row['status'] . ' ' . $row['channel'] . ' ' . $row['recipient']); ?>">
                        <td><?= e($row['reference']); ?></td>
                        <td><?= e($row['service_name']); ?></td>
                        <td><span class="status-chip status-<?= e($row['status']); ?>"><?= e(ucfirst($row['status'])); ?></span></td>
                        <td><?= e($row['channel']); ?></td>
                        <td><?= e(money($row['amount'])); ?></td>
                        <td><?= e(money($row['commission_amount'])); ?></td>
                        <td><?= e($row['recipient']); ?></td>
                        <td><span class="timestamp" title="<?= e($row['created_at']); ?>"><?= e(human_datetime((string) $row['created_at'])); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="mt-6">
        <?php render_pagination($pagination, 'user/transactions.php'); ?>
    </div>
</div>
<?php render_footer(); ?>
