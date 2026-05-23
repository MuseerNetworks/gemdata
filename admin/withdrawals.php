<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('wallet.manage');

$db = db();
$commWallet = new \GemData\Classes\CommissionWallet($db);
$withdrawSvc = new \GemData\Classes\WithdrawalService($db, $commWallet);

$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    try {
        if ($action === 'approve') {
            $withdrawSvc->approve($requestId, (int) $admin['id'], $note);
            $success = 'Withdrawal approved. Commission wallet debited. Please transfer funds manually.';
        } elseif ($action === 'reject') {
            if ($note === '') {
                throw new \InvalidArgumentException('Please provide a rejection reason.');
            }
            $withdrawSvc->reject($requestId, (int) $admin['id'], $note);
            $success = 'Withdrawal request rejected.';
        } elseif ($action === 'mark_paid') {
            $withdrawSvc->markPaid($requestId, (int) $admin['id']);
            $success = 'Withdrawal marked as paid.';
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

$filterStatus = $_GET['status'] ?? 'pending';
$validStatuses = ['pending', 'approved', 'rejected', 'paid', ''];
$filterStatus = in_array($filterStatus, $validStatuses, true) ? $filterStatus : 'pending';

$requests = $withdrawSvc->listAll(100, 0, $filterStatus ?: null);
$totalPaid = $withdrawSvc->totalPaidOut();
$pendingCnt = count($withdrawSvc->listPending());

render_header('Withdrawal Requests', 'admin');
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Finance Operations</p>
        <h1>Withdrawal Requests</h1>
        <p>Review and approve reseller commission withdrawals.</p>
    </div>
    <div class="rounded-2xl border border-gem-border bg-gem-gray px-4 py-3 text-right">
        <div class="text-[12px] font-bold uppercase text-gem-muted">Total Paid Out</div>
        <div class="text-xl font-black text-gem-green"><?= e(money($totalPaid)); ?></div>
    </div>
</div>

<?php if ($error): ?><div class="notice notice-error mb-4"><?= e($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="notice notice-success mb-4"><?= e($success); ?></div><?php endif; ?>

<?php if ($pendingCnt > 0): ?>
    <div class="alert alert-warning mb-4">
        <strong><?= (int) $pendingCnt; ?></strong> pending withdrawal request<?= $pendingCnt > 1 ? 's' : ''; ?> awaiting review.
    </div>
<?php endif; ?>

<section class="surface-card p-5">
    <div class="flex flex-wrap gap-2">
        <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Paid', 'rejected' => 'Rejected', '' => 'All'] as $s => $label): ?>
            <a href="<?= e(base_url('admin/withdrawals.php?status=' . $s)); ?>" class="btn btn-sm <?= $filterStatus === $s ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                <?= e($label); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($requests === []): ?>
        <div class="mt-5 rounded-2xl border border-gem-border bg-gem-gray p-8 text-center text-gem-muted">
            No withdrawal requests found.
        </div>
    <?php else: ?>
        <div class="table-responsive mt-5">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reseller</th>
                        <th>Amount</th>
                        <th>Bank Details</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($requests as $req): ?>
                    <?php
                    $statusTone = match ($req['status']) {
                        'pending' => 'bg-yellow-50 text-gem-orange',
                        'approved' => 'bg-blue-50 text-gem-blue',
                        'paid' => 'bg-green-50 text-gem-green',
                        'rejected' => 'bg-red-50 text-gem-red',
                        default => 'bg-slate-100 text-gem-muted',
                    };
                    ?>
                    <tr>
                        <td class="text-[12px] text-gem-muted"><?= e(date('d M Y, H:i', strtotime($req['created_at']))); ?></td>
                        <td>
                            <div class="font-bold text-gem-text"><?= e($req['full_name']); ?></div>
                            <div class="text-[12px] text-gem-muted"><?= e($req['email']); ?></div>
                        </td>
                        <td class="font-black text-gem-green"><?= e(money((float) $req['amount'])); ?></td>
                        <td>
                            <div class="font-semibold text-gem-text"><?= e($req['bank_name']); ?></div>
                            <div class="text-[12px] text-gem-muted"><?= e($req['account_number']); ?> - <?= e($req['account_name']); ?></div>
                        </td>
                        <td>
                            <span class="badge <?= $statusTone; ?>"><?= e($req['status']); ?></span>
                            <?php if (!empty($req['admin_note'])): ?>
                                <div class="mt-1 text-[12px] text-gem-muted"><?= e($req['admin_note']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($req['status'] === 'pending'): ?>
                                <div class="grid gap-2 min-w-[280px]">
                                    <form method="POST" class="flex flex-wrap gap-2" data-loading-form>
                                        <input type="hidden" name="request_id" value="<?= (int) $req['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="text" name="note" class="form-control flex-1 min-w-[150px]" placeholder="Note (optional)">
                                        <button class="btn btn-sm btn-outline-success" type="submit" data-loading-label="Approving..." onclick="return confirm('Approve this withdrawal?')">Approve</button>
                                    </form>
                                    <form method="POST" class="flex flex-wrap gap-2" data-loading-form>
                                        <input type="hidden" name="request_id" value="<?= (int) $req['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="text" name="note" class="form-control flex-1 min-w-[150px]" placeholder="Reason (required)" required>
                                        <button class="btn btn-sm btn-outline-danger" type="submit" data-loading-label="Rejecting...">Reject</button>
                                    </form>
                                </div>
                            <?php elseif ($req['status'] === 'approved'): ?>
                                <form method="POST" data-loading-form>
                                    <input type="hidden" name="request_id" value="<?= (int) $req['id']; ?>">
                                    <input type="hidden" name="action" value="mark_paid">
                                    <button class="btn btn-sm btn-outline-success" type="submit" data-loading-label="Saving..." onclick="return confirm('Mark as paid after manual bank transfer?')">Mark Paid</button>
                                </form>
                            <?php else: ?>
                                <span class="text-[12px] text-gem-muted">No action</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
