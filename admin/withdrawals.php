<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$auth = new \GemData\Classes\SessionAuth($db, $config);
$auth->requireAdminLogin();
$admin = $auth->admin();

$commWallet  = new \GemData\Classes\CommissionWallet($db);
$withdrawSvc = new \GemData\Classes\WithdrawalService($db, $commWallet);

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $note      = trim($_POST['note'] ?? '');

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

$requests   = $withdrawSvc->listAll(100, 0, $filterStatus ?: null);
$totalPaid  = $withdrawSvc->totalPaidOut();
$pendingCnt = count($withdrawSvc->listPending());

render_header('Withdrawal Requests', 'admin-withdrawals');
?>

<div class="page-header d-flex align-items-center justify-content-between">
  <div>
    <h1>Withdrawal Requests</h1>
    <p class="text-muted">Review and approve reseller commission withdrawals.</p>
  </div>
  <div class="text-end">
    <div class="small text-muted">Total Paid Out</div>
    <div class="fw-bold fs-5 text-success">₦<?= number_format($totalPaid, 2) ?></div>
  </div>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($pendingCnt > 0): ?>
  <div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong><?= $pendingCnt ?></strong> pending withdrawal request<?= $pendingCnt > 1 ? 's' : '' ?> awaiting review.
  </div>
<?php endif; ?>

<!-- Status Filter -->
<div class="d-flex gap-2 mb-4 flex-wrap">
  <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Paid', 'rejected' => 'Rejected', '' => 'All'] as $s => $label): ?>
    <a href="?status=<?= $s ?>" class="btn btn-sm <?= $filterStatus === $s ? 'btn-primary' : 'btn-outline-secondary' ?>">
      <?= $label ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($requests)): ?>
      <div class="text-center text-muted py-5">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>No withdrawal requests found.
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
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
          <tr>
            <td class="small text-muted"><?= date('d M Y, H:i', strtotime($req['created_at'])) ?></td>
            <td>
              <div class="fw-medium"><?= htmlspecialchars($req['full_name']) ?></div>
              <div class="text-muted small"><?= htmlspecialchars($req['email']) ?></div>
            </td>
            <td class="fw-bold text-success">₦<?= number_format((float)$req['amount'], 2) ?></td>
            <td class="small">
              <div><?= htmlspecialchars($req['bank_name']) ?></div>
              <div class="text-muted"><?= htmlspecialchars($req['account_number']) ?> — <?= htmlspecialchars($req['account_name']) ?></div>
            </td>
            <td>
              <?php $badge = match($req['status']) {
                'pending'  => 'warning',
                'approved' => 'info',
                'paid'     => 'success',
                'rejected' => 'danger',
                default    => 'secondary'
              }; ?>
              <span class="badge bg-<?= $badge ?>-subtle text-<?= $badge ?> text-capitalize"><?= $req['status'] ?></span>
              <?php if (!empty($req['admin_note'])): ?>
                <div class="small text-muted"><?= htmlspecialchars($req['admin_note']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($req['status'] === 'pending'): ?>
              <form method="POST" class="d-inline">
                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <input type="text" name="note" class="form-control form-control-sm d-inline w-auto me-1" placeholder="Note (optional)">
                <button class="btn btn-sm btn-success" onclick="return confirm('Approve this withdrawal?')">Approve</button>
              </form>
              <form method="POST" class="d-inline ms-1">
                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <input type="text" name="note" class="form-control form-control-sm d-inline w-auto me-1" placeholder="Reason (required)">
                <button class="btn btn-sm btn-danger">Reject</button>
              </form>
              <?php elseif ($req['status'] === 'approved'): ?>
              <form method="POST" class="d-inline">
                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                <input type="hidden" name="action" value="mark_paid">
                <button class="btn btn-sm btn-outline-success" onclick="return confirm('Mark as paid after manual bank transfer?')">
                  <i class="bi bi-check2-all me-1"></i>Mark Paid
                </button>
              </form>
              <?php else: ?>
                <span class="text-muted small">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php render_footer(); ?>
