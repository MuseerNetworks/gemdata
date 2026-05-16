<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$db = db();

if (($user['user_type'] ?? 'smart') !== 'reseller') {
    redirect(base_url('user/dashboard.php'));
}

$commWallet  = new \GemData\Classes\CommissionWallet($db);
$withdrawSvc = new \GemData\Classes\WithdrawalService($db, $commWallet);
$featureFlag = new \GemData\Classes\FeatureFlag($db);

if (!$featureFlag->enabled('withdrawal_enabled')) {
    redirect(base_url('user/commission.php'));
}

$balance   = $commWallet->balance((int) $user['id']);
$history   = $withdrawSvc->listByUser((int) $user['id'], 30);
$error     = '';
$success   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $amount    = (float) ($_POST['amount'] ?? 0);
    $bankName  = trim($_POST['bank_name'] ?? '');
    $acctNo    = trim($_POST['account_number'] ?? '');
    $acctName  = trim($_POST['account_name'] ?? '');

    try {
        if ($bankName === '' || $acctNo === '' || $acctName === '') {
            throw new \InvalidArgumentException('All bank details are required.');
        }
        $withdrawSvc->request((int) $user['id'], $amount, $bankName, $acctNo, $acctName);
        $success = 'Withdrawal request submitted successfully. Admin will review and process it.';
        $history = $withdrawSvc->listByUser((int) $user['id'], 30);
        $balance = $commWallet->balance((int) $user['id']);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

render_header('Request Withdrawal', 'reseller-withdrawals');
?>

<div class="page-header">
  <h1>Request Withdrawal</h1>
  <p class="text-muted">Withdraw your commission balance to your bank account.</p>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="row g-4">
  <!-- Withdrawal Form -->
  <div class="col-md-5">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-0 pt-3">
        <h5 class="fw-semibold mb-0">Withdrawal Details</h5>
        <small class="text-muted">Available: <strong class="text-success">₦<?= number_format($balance, 2) ?></strong></small>
      </div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
          <div class="mb-3">
            <label class="form-label fw-medium">Amount (₦)</label>
            <input type="number" name="amount" class="form-control" min="500" max="<?= $balance ?>"
                   step="0.01" placeholder="Minimum ₦500" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Bank Name</label>
            <input type="text" name="bank_name" class="form-control" placeholder="e.g. First Bank" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Account Number</label>
            <input type="text" name="account_number" class="form-control" maxlength="10" placeholder="10-digit account number" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Account Name</label>
            <input type="text" name="account_name" class="form-control" placeholder="As it appears on your bank" required>
          </div>
          <div class="alert alert-info small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            After submitting, admin will verify and manually transfer to your account within 24–48 hours.
          </div>
          <button type="submit" class="btn btn-success w-100" <?= $balance < 500 ? 'disabled' : '' ?>>
            <i class="bi bi-send me-1"></i> Submit Withdrawal Request
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Withdrawal History -->
  <div class="col-md-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-0 pt-3">
        <h5 class="fw-semibold mb-0">Withdrawal History</h5>
      </div>
      <div class="card-body p-0">
        <?php if (empty($history)): ?>
          <div class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>No withdrawal requests yet.
          </div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Date</th>
                <th>Amount</th>
                <th>Bank</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($history as $w): ?>
              <tr>
                <td class="small text-muted"><?= date('d M Y', strtotime($w['created_at'])) ?></td>
                <td class="fw-semibold">₦<?= number_format((float)$w['amount'], 2) ?></td>
                <td class="small">
                  <?= htmlspecialchars($w['bank_name']) ?><br>
                  <span class="text-muted"><?= htmlspecialchars($w['account_number']) ?></span>
                </td>
                <td>
                  <?php $badge = match($w['status']) {
                    'pending'  => 'warning',
                    'approved' => 'info',
                    'paid'     => 'success',
                    'rejected' => 'danger',
                    default    => 'secondary'
                  }; ?>
                  <span class="badge bg-<?= $badge ?>-subtle text-<?= $badge ?> text-capitalize"><?= $w['status'] ?></span>
                  <?php if (!empty($w['admin_note'])): ?>
                    <div class="small text-muted mt-1"><?= htmlspecialchars($w['admin_note']) ?></div>
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
  </div>
</div>

<?php render_footer(); ?>
