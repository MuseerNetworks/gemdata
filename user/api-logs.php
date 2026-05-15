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

// Filters
$page       = max(1, (int) ($_GET['page'] ?? 1));
$perPage    = 25;
$offset     = ($page - 1) * $perPage;
$status     = $_GET['status'] ?? '';
$service    = $_GET['service'] ?? '';
$dateFrom   = $_GET['date_from'] ?? '';
$dateTo     = $_GET['date_to'] ?? '';

$where  = ['t.user_id = :uid', 't.channel = "api"'];
$params = ['uid' => $userId, 'limit' => $perPage, 'offset' => $offset];

if ($status !== '') {
    $where[]          = 't.status = :status';
    $params['status'] = $status;
}
if ($service !== '') {
    $where[]          = 's.slug = :service';
    $params['service'] = $service;
}
if ($dateFrom !== '') {
    $where[]           = 'DATE(t.created_at) >= :date_from';
    $params['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where[]          = 'DATE(t.created_at) <= :date_to';
    $params['date_to'] = $dateTo;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$transactions = $db->safeQuery(
    "SELECT t.*, s.name AS service_name, s.slug AS service_slug
       FROM transactions t
       JOIN services s ON s.id = t.service_id
       {$whereSQL}
      ORDER BY t.created_at DESC
      LIMIT :limit OFFSET :offset",
    $params
);

$totalParams = $params;
unset($totalParams['limit'], $totalParams['offset']);
$totalRow = $db->first(
    "SELECT COUNT(*) AS cnt FROM transactions t JOIN services s ON s.id = t.service_id {$whereSQL}",
    $totalParams
);
$total     = (int) ($totalRow['cnt'] ?? 0);
$totalPages = (int) ceil($total / $perPage);

$services = $db->safeQuery('SELECT slug, name FROM services ORDER BY name');

render_header('API Transaction Logs', 'api-logs');
?>

<div class="page-header d-flex align-items-center justify-content-between">
  <div>
    <h1>API Transaction Logs</h1>
    <p class="text-muted">Full history of all API channel transactions.</p>
  </div>
  <a href="/user/api-dashboard.php" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
  </a>
</div>

<!-- Filters -->
<form method="GET" class="card border-0 shadow-sm mb-4">
  <div class="card-body">
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small fw-medium">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <option value="successful" <?= $status === 'successful' ? 'selected' : '' ?>>Successful</option>
          <option value="failed"     <?= $status === 'failed'     ? 'selected' : '' ?>>Failed</option>
          <option value="pending"    <?= $status === 'pending'    ? 'selected' : '' ?>>Pending</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-medium">Service</label>
        <select name="service" class="form-select form-select-sm">
          <option value="">All Services</option>
          <?php foreach ($services as $svc): ?>
            <option value="<?= $svc['slug'] ?>" <?= $service === $svc['slug'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($svc['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-medium">From</label>
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-medium">To</label>
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
        <a href="?" class="btn btn-outline-secondary btn-sm">Clear</a>
      </div>
    </div>
  </div>
</form>

<!-- Summary bar -->
<div class="d-flex gap-3 mb-3 flex-wrap">
  <span class="badge bg-light text-dark border px-3 py-2">Total: <?= number_format($total) ?></span>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($transactions)): ?>
      <div class="text-center text-muted py-5">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>No transactions found.
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 small">
        <thead class="table-light">
          <tr>
            <th>Reference</th>
            <th>Service</th>
            <th>Recipient</th>
            <th class="text-end">Amount</th>
            <th>Provider</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($transactions as $tx): ?>
          <tr>
            <td>
              <code><?= htmlspecialchars($tx['reference']) ?></code>
              <?php if (!empty($tx['provider_reference'])): ?>
                <div class="text-muted" style="font-size:.7rem">PRV: <?= htmlspecialchars($tx['provider_reference']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($tx['service_name']) ?></td>
            <td><?= htmlspecialchars($tx['recipient']) ?></td>
            <td class="text-end fw-medium">₦<?= number_format((float)$tx['amount'], 2) ?></td>
            <td class="text-muted"><?= htmlspecialchars($tx['provider_code'] ?? '—') ?></td>
            <td>
              <?php $b = match($tx['status']) { 'successful'=>'success','failed'=>'danger','pending'=>'warning',default=>'secondary' }; ?>
              <span class="badge bg-<?= $b ?>-subtle text-<?= $b ?> text-capitalize"><?= $tx['status'] ?></span>
              <?php if (!empty($tx['failure_code'])): ?>
                <div style="font-size:.68rem" class="text-danger"><?= htmlspecialchars($tx['failure_code']) ?></div>
              <?php endif; ?>
            </td>
            <td class="text-muted"><?= date('d M Y, H:i', strtotime($tx['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
      <small class="text-muted">Page <?= $page ?> of <?= $totalPages ?> (<?= $total ?> results)</small>
      <div class="d-flex gap-1">
        <?php if ($page > 1): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-chevron-left"></i>
          </a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-chevron-right"></i>
          </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php render_footer(); ?>
