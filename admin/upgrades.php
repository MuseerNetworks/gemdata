<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('upgrades.manage');
$db = db();

$upgradeSvc = new \GemData\Classes\UpgradeRequestService($db);
$commWallet = new \GemData\Classes\CommissionWallet($db);

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action    = $_POST['action'] ?? '';
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $note      = trim($_POST['note'] ?? '');

    try {
        if ($action === 'approve') {
            $upgradeSvc->approve($requestId, (int) $admin['id'], $note);
            $success = 'User upgrade approved successfully.';
        } elseif ($action === 'reject') {
            if ($note === '') {
                throw new \InvalidArgumentException('Please provide a rejection reason.');
            }
            $upgradeSvc->reject($requestId, (int) $admin['id'], $note);
            $success = 'Upgrade request rejected.';
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

$filterStatus = $_GET['status'] ?? 'pending';
$requests     = $upgradeSvc->listAll(100, 0);
if ($filterStatus !== 'all') {
    $requests = array_filter($requests, fn($r) => ($r['status'] ?? '') === $filterStatus);
}
$pendingCnt = count($upgradeSvc->listPending());

render_header('Upgrade Requests', 'admin-upgrades');
?>

<div class="page-header d-flex align-items-center justify-content-between">
  <div>
    <h1>Upgrade Requests</h1>
    <p class="text-muted">Approve or reject user account upgrade requests.</p>
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
    <i class="bi bi-person-up me-1"></i>
    <strong><?= $pendingCnt ?></strong> upgrade request<?= $pendingCnt > 1 ? 's' : '' ?> awaiting review.
  </div>
<?php endif; ?>

<!-- Filter -->
<div class="d-flex gap-2 mb-4">
  <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $s => $label): ?>
    <a href="?status=<?= $s ?>" class="btn btn-sm <?= $filterStatus === $s ? 'btn-primary' : 'btn-outline-secondary' ?>">
      <?= $label ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($requests)): ?>
      <div class="text-center text-muted py-5">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>No upgrade requests found.
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>User</th>
            <th>Upgrade</th>
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
              <div class="small text-muted"><?= htmlspecialchars($req['email']) ?></div>
            </td>
            <td>
              <span class="badge bg-secondary-subtle text-secondary text-capitalize"><?= $req['from_type'] ?></span>
              <i class="bi bi-arrow-right mx-1 text-muted"></i>
              <span class="badge bg-primary-subtle text-primary text-capitalize"><?= $req['to_type'] ?></span>
            </td>
            <td>
              <?php $badge = match($req['status']) {
                'pending'  => 'warning',
                'approved' => 'success',
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
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="text" name="note" class="form-control form-control-sm d-inline w-auto me-1" placeholder="Note (optional)">
                <button class="btn btn-sm btn-success" onclick="return confirm('Approve this upgrade?')">
                  <i class="bi bi-check-lg"></i> Approve
                </button>
              </form>
              <form method="POST" class="d-inline ms-1">
                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="text" name="note" class="form-control form-control-sm d-inline w-auto me-1" placeholder="Reason (required)">
                <button class="btn btn-sm btn-danger">Reject</button>
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
