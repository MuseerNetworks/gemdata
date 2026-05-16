<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$db = db();

$upgradeSvc = new \GemData\Classes\UpgradeRequestService($db);
$currentType = $user['user_type'] ?? 'smart';
$targetType  = 'reseller';

// Already a reseller or api — redirect
if (in_array($currentType, ['reseller', 'api'], true)) {
    redirect(base_url('user/dashboard.php'));
}

$latest  = $upgradeSvc->latestForUser((int) $user['id']);
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $upgradeSvc->request((int) $user['id'], $targetType);
        $success = 'Your upgrade request has been submitted. Admin will review it shortly.';
        $latest  = $upgradeSvc->latestForUser((int) $user['id']);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

render_header('Upgrade to Reseller', 'upgrade-request');
?>

<div class="page-header">
  <h1>Upgrade to Reseller</h1>
  <p class="text-muted">Earn commission on every successful transaction you complete.</p>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="row g-4 justify-content-center">
  <div class="col-md-7">

    <!-- Benefits card -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <h5 class="fw-bold mb-3">What you get as a Reseller</h5>
        <ul class="list-unstyled mb-0">
          <li class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-check-circle-fill text-success"></i>
            Earn commission on every transaction (rate set by admin)
          </li>
          <li class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-check-circle-fill text-success"></i>
            Dedicated commission wallet — separate from main wallet
          </li>
          <li class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-check-circle-fill text-success"></i>
            Request withdrawals to your bank account
          </li>
          <li class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-check-circle-fill text-success"></i>
            Access to reseller pricing tier
          </li>
        </ul>
      </div>
    </div>

    <!-- Status / Request Form -->
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <?php if ($latest && $latest['status'] === 'pending'): ?>
          <div class="text-center py-4">
            <i class="bi bi-hourglass-split text-warning fs-1 d-block mb-2"></i>
            <h5 class="fw-semibold">Request Under Review</h5>
            <p class="text-muted">Your upgrade request was submitted on
              <strong><?= date('d M Y', strtotime($latest['created_at'])) ?></strong>.
              Admin will review it soon.
            </p>
          </div>
        <?php elseif ($latest && $latest['status'] === 'rejected'): ?>
          <div class="alert alert-danger mb-3">
            <strong>Previous request rejected.</strong>
            <?php if (!empty($latest['admin_note'])): ?>
              Reason: <?= htmlspecialchars($latest['admin_note']) ?>
            <?php endif; ?>
          </div>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <p>You can submit a new request.</p>
            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-arrow-up-circle me-1"></i> Request Reseller Upgrade
            </button>
          </form>
        <?php else: ?>
          <h5 class="fw-semibold mb-3">Request Account Upgrade</h5>
          <p class="text-muted">Click the button below to send an upgrade request to admin. No documents required.</p>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <button type="submit" class="btn btn-primary btn-lg w-100">
              <i class="bi bi-arrow-up-circle me-1"></i> Request Reseller Upgrade
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<?php render_footer(); ?>
