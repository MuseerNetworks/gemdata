<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$db = db();

// Only resellers access this page
if (($user['user_type'] ?? 'smart') !== 'reseller') {
    redirect(base_url('user/dashboard.php'));
}

$commWallet    = new \GemData\Classes\CommissionWallet($db);
$withdrawSvc   = new \GemData\Classes\WithdrawalService($db, $commWallet);
$featureFlag   = new \GemData\Classes\FeatureFlag($db);

$balance       = $commWallet->balance((int) $user['id']);
$totalEarned   = $commWallet->totalEarned((int) $user['id']);
$totalWithdrawn= $commWallet->totalWithdrawn((int) $user['id']);
$history       = $commWallet->history((int) $user['id'], 20);
$pendingWdr    = $withdrawSvc->listByUser((int) $user['id'], 1);
$hasPending    = !empty($pendingWdr) && ($pendingWdr[0]['status'] ?? '') === 'pending';
$withdrawEnabled = $featureFlag->enabled('withdrawal_enabled');

render_header('Commission Wallet', 'reseller-commission');
?>

<div class="page-header">
  <h1>Commission Wallet</h1>
  <p class="text-muted">Track and manage your earned commissions.</p>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3">
          <div class="rounded-3 bg-success bg-opacity-10 p-3">
            <i class="bi bi-wallet2 text-success fs-4"></i>
          </div>
          <div>
            <div class="text-muted small">Available Balance</div>
            <div class="fw-bold fs-4 text-success">₦<?= number_format($balance, 2) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3">
          <div class="rounded-3 bg-primary bg-opacity-10 p-3">
            <i class="bi bi-graph-up-arrow text-primary fs-4"></i>
          </div>
          <div>
            <div class="text-muted small">Total Earned</div>
            <div class="fw-bold fs-4">₦<?= number_format($totalEarned, 2) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3">
          <div class="rounded-3 bg-warning bg-opacity-10 p-3">
            <i class="bi bi-arrow-up-circle text-warning fs-4"></i>
          </div>
          <div>
            <div class="text-muted small">Total Withdrawn</div>
            <div class="fw-bold fs-4">₦<?= number_format($totalWithdrawn, 2) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Withdraw CTA -->
<?php if ($withdrawEnabled): ?>
<div class="d-flex justify-content-end mb-3">
  <?php if ($hasPending): ?>
    <span class="btn btn-outline-warning disabled">
      <i class="bi bi-clock me-1"></i> Withdrawal Pending Review
    </span>
  <?php elseif ($balance >= 500): ?>
    <a href="<?= e(base_url('user/withdrawals.php')); ?>" class="btn btn-success">
      <i class="bi bi-cash-stack me-1"></i> Request Withdrawal
    </a>
  <?php else: ?>
    <span class="btn btn-outline-secondary disabled">
      Min. ₦500 balance to withdraw
    </span>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Commission History -->
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white border-0 pt-3">
    <h5 class="mb-0 fw-semibold">Commission History</h5>
  </div>
  <div class="card-body p-0">
    <?php if (empty($history)): ?>
      <div class="text-center text-muted py-5">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
        No commission transactions yet. Complete sales to earn commission.
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Narration</th>
            <th class="text-end">Amount</th>
            <th class="text-end">Balance After</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $row): ?>
          <tr>
            <td class="text-muted small"><?= date('d M Y, H:i', strtotime($row['created_at'])) ?></td>
            <td>
              <?php if ($row['type'] === 'credit'): ?>
                <span class="badge bg-success-subtle text-success">Earned</span>
              <?php else: ?>
                <span class="badge bg-warning-subtle text-warning">Withdrawn</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($row['narration']) ?></td>
            <td class="text-end fw-semibold <?= $row['type'] === 'credit' ? 'text-success' : 'text-danger' ?>">
              <?= $row['type'] === 'credit' ? '+' : '-' ?>₦<?= number_format((float)$row['amount'], 2) ?>
            </td>
            <td class="text-end text-muted">₦<?= number_format((float)$row['balance_after'], 2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php render_footer(); ?>
