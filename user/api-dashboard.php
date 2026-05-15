<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$auth = new \GemData\Classes\SessionAuth($db, $config);
$auth->requireLogin();
$user = $auth->user();

if (($user['user_type'] ?? 'smart') !== 'api') {
    header('Location: /user/dashboard.php');
    exit;
}

$userId = (int) $user['id'];

// API user record
$apiUser = $db->first('SELECT * FROM api_users WHERE user_id = :uid LIMIT 1', ['uid' => $userId]);
$apiKeys = $db->safeQuery(
    'SELECT ak.*, (SELECT COUNT(*) FROM api_rate_limits WHERE api_key_id = ak.id) AS rate_limit_windows
       FROM api_keys ak
      WHERE ak.api_user_id = :api_user_id
      ORDER BY ak.created_at DESC',
    ['api_user_id' => $apiUser['id'] ?? 0]
);

// Stats last 30 days
$stats = $db->first(
    'SELECT
       COUNT(*) AS total_requests,
       SUM(CASE WHEN status = "successful" THEN 1 ELSE 0 END) AS successful,
       SUM(CASE WHEN status = "failed"     THEN 1 ELSE 0 END) AS failed,
       SUM(CASE WHEN status = "pending"    THEN 1 ELSE 0 END) AS pending_count,
       COALESCE(SUM(amount), 0) AS total_volume
     FROM transactions
     WHERE user_id = :uid AND channel = "api"
       AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
    ['uid' => $userId]
);

$successRate = $stats['total_requests'] > 0
    ? round(($stats['successful'] / $stats['total_requests']) * 100, 1)
    : 0;

// Recent 5 transactions
$recent = $db->safeQuery(
    'SELECT t.*, s.name AS service_name
       FROM transactions t
       JOIN services s ON s.id = t.service_id
      WHERE t.user_id = :uid AND t.channel = "api"
      ORDER BY t.created_at DESC
      LIMIT 5',
    ['uid' => $userId]
);

// Main wallet balance
$wallet = $db->first('SELECT balance FROM wallets WHERE user_id = :uid LIMIT 1', ['uid' => $userId]);

render_header('API Dashboard', 'api-dashboard');
?>

<div class="page-header">
  <h1>API Dashboard</h1>
  <p class="text-muted">Monitor your API usage, keys, and transaction performance.</p>
</div>

<!-- Status Banner -->
<?php $apiStatus = $apiUser['status'] ?? 'inactive'; ?>
<?php if ($apiStatus === 'inactive'): ?>
  <div class="alert alert-warning mb-4">
    <i class="bi bi-exclamation-triangle me-2"></i>
    Your API account is <strong>pending activation</strong>. Contact admin to activate your account before making API calls.
  </div>
<?php endif; ?>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small mb-1">Wallet Balance</div>
        <div class="fw-bold fs-4">₦<?= number_format((float)($wallet['balance'] ?? 0), 2) ?></div>
        <a href="/user/fund-wallet.php" class="btn btn-sm btn-outline-primary mt-2">Fund Wallet</a>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small mb-1">Requests (30 days)</div>
        <div class="fw-bold fs-4"><?= number_format((int)$stats['total_requests']) ?></div>
        <div class="small mt-1">
          <span class="text-success"><?= $stats['successful'] ?> success</span> ·
          <span class="text-danger"><?= $stats['failed'] ?> failed</span>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small mb-1">Success Rate</div>
        <div class="fw-bold fs-4 <?= $successRate >= 90 ? 'text-success' : ($successRate >= 70 ? 'text-warning' : 'text-danger') ?>">
          <?= $successRate ?>%
        </div>
        <div class="progress mt-2" style="height:5px">
          <div class="progress-bar bg-success" style="width:<?= $successRate ?>%"></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small mb-1">Volume (30 days)</div>
        <div class="fw-bold fs-4">₦<?= number_format((float)$stats['total_volume'], 2) ?></div>
        <div class="small text-muted mt-1"><?= $stats['pending_count'] ?> pending</div>
      </div>
    </div>
  </div>
</div>

<!-- API Keys -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white border-0 pt-3 d-flex align-items-center justify-content-between">
    <h5 class="fw-semibold mb-0">API Keys</h5>
    <a href="/user/api-keys.php" class="btn btn-sm btn-outline-primary">Manage Keys</a>
  </div>
  <div class="card-body p-0">
    <?php if (empty($apiKeys)): ?>
      <div class="text-center text-muted py-4">
        <i class="bi bi-key fs-1 d-block mb-2"></i>
        No API keys yet. <a href="/user/api-keys.php">Generate your first key</a>.
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr><th>Key</th><th>Status</th><th>Last Used</th><th>Created</th></tr>
        </thead>
        <tbody>
          <?php foreach ($apiKeys as $key): ?>
          <tr>
            <td><code class="small"><?= htmlspecialchars(substr($key['api_key'], 0, 16)) ?>••••••••</code></td>
            <td>
              <span class="badge bg-<?= $key['status'] === 'active' ? 'success' : 'secondary' ?>-subtle
                                    text-<?= $key['status'] === 'active' ? 'success' : 'secondary' ?> text-capitalize">
                <?= $key['status'] ?>
              </span>
            </td>
            <td class="small text-muted">
              <?= $key['last_used_at'] ? date('d M Y, H:i', strtotime($key['last_used_at'])) : 'Never' ?>
            </td>
            <td class="small text-muted"><?= date('d M Y', strtotime($key['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Quick Links -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <a href="/user/api-logs.php" class="card border-0 shadow-sm text-decoration-none h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-3 bg-primary bg-opacity-10 p-3">
          <i class="bi bi-list-ul text-primary fs-4"></i>
        </div>
        <div>
          <div class="fw-semibold">Transaction Logs</div>
          <div class="small text-muted">View all API transactions</div>
        </div>
        <i class="bi bi-chevron-right ms-auto text-muted"></i>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a href="/docs/api.php" class="card border-0 shadow-sm text-decoration-none h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-3 bg-warning bg-opacity-10 p-3">
          <i class="bi bi-book text-warning fs-4"></i>
        </div>
        <div>
          <div class="fw-semibold">API Documentation</div>
          <div class="small text-muted">Endpoints, auth, examples</div>
        </div>
        <i class="bi bi-chevron-right ms-auto text-muted"></i>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a href="/user/fund-wallet.php" class="card border-0 shadow-sm text-decoration-none h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-3 bg-success bg-opacity-10 p-3">
          <i class="bi bi-wallet2 text-success fs-4"></i>
        </div>
        <div>
          <div class="fw-semibold">Fund Wallet</div>
          <div class="small text-muted">Top up your API balance</div>
        </div>
        <i class="bi bi-chevron-right ms-auto text-muted"></i>
      </div>
    </a>
  </div>
</div>

<!-- Recent API Transactions -->
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
    <h5 class="fw-semibold mb-0">Recent API Transactions</h5>
    <a href="/user/api-logs.php" class="btn btn-sm btn-link text-decoration-none">View All</a>
  </div>
  <div class="card-body p-0">
    <?php if (empty($recent)): ?>
      <div class="text-center text-muted py-4">No API transactions yet.</div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr><th>Reference</th><th>Service</th><th>Recipient</th><th>Amount</th><th>Status</th><th>Date</th></tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $tx): ?>
          <tr>
            <td><code class="small"><?= htmlspecialchars($tx['reference']) ?></code></td>
            <td class="small"><?= htmlspecialchars($tx['service_name']) ?></td>
            <td class="small"><?= htmlspecialchars($tx['recipient']) ?></td>
            <td class="fw-medium">₦<?= number_format((float)$tx['amount'], 2) ?></td>
            <td>
              <?php $b = match($tx['status']) { 'successful'=>'success','failed'=>'danger','pending'=>'warning',default=>'secondary' }; ?>
              <span class="badge bg-<?= $b ?>-subtle text-<?= $b ?> text-capitalize"><?= $tx['status'] ?></span>
            </td>
            <td class="small text-muted"><?= date('d M, H:i', strtotime($tx['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php render_footer(); ?>
