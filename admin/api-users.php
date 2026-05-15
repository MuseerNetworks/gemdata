<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$auth = new \GemData\Classes\SessionAuth($db, $config);
$auth->requireAdminLogin();
$admin = $auth->admin();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);

    try {
        if ($action === 'activate') {
            // Ensure api_users row exists
            $existing = $db->first('SELECT id FROM api_users WHERE user_id = :uid LIMIT 1', ['uid' => $userId]);
            if (!$existing) {
                $db->execute(
                    'INSERT INTO api_users (user_id, status, created_by_admin_id) VALUES (:uid, :s, :aid)',
                    ['uid' => $userId, 's' => 'active', 'aid' => $admin['id']]
                );
            } else {
                $db->execute('UPDATE api_users SET status = "active" WHERE user_id = :uid', ['uid' => $userId]);
            }
            // Set user_type and tier
            $db->execute(
                'UPDATE users SET user_type = "api", tier = "API_RESELLER", is_api_user = 1 WHERE id = :uid',
                ['uid' => $userId]
            );
            $success = 'API user activated.';

        } elseif ($action === 'deactivate') {
            $db->execute('UPDATE api_users SET status = "inactive" WHERE user_id = :uid', ['uid' => $userId]);
            $success = 'API user deactivated.';

        } elseif ($action === 'revoke_key') {
            $keyId = (int) ($_POST['key_id'] ?? 0);
            $db->execute('UPDATE api_keys SET status = "inactive" WHERE id = :id', ['id' => $keyId]);
            $success = 'API key revoked.';
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');

$where  = ["u.user_type = 'api'"];
$params = [];

if ($filterStatus !== '') {
    $where[]          = 'au.status = :status';
    $params['status'] = $filterStatus;
}
if ($search !== '') {
    $where[]          = '(u.full_name LIKE :q OR u.email LIKE :q OR u.phone LIKE :q)';
    $params['q']      = '%' . $search . '%';
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$apiUsers = $db->safeQuery(
    "SELECT u.id, u.full_name, u.email, u.phone, u.user_type, u.created_at AS registered_at,
            au.id AS api_user_id, au.status AS api_status, au.created_at AS activated_at,
            (SELECT COUNT(*) FROM api_keys ak WHERE ak.api_user_id = au.id AND ak.status = 'active') AS active_keys,
            (SELECT COUNT(*) FROM transactions t WHERE t.user_id = u.id AND t.channel = 'api') AS total_api_txns,
            (SELECT COALESCE(SUM(t2.amount),0) FROM transactions t2 WHERE t2.user_id = u.id AND t2.channel = 'api' AND t2.status = 'successful') AS api_volume,
            w.balance AS wallet_balance
       FROM users u
  LEFT JOIN api_users au ON au.user_id = u.id
  LEFT JOIN wallets w ON w.user_id = u.id
       {$whereSQL}
      ORDER BY u.created_at DESC",
    $params
);

// Users eligible to become API (not yet api type)
$candidates = $db->safeQuery(
    "SELECT id, full_name, email FROM users WHERE user_type != 'api' ORDER BY full_name LIMIT 200"
);

render_header('API User Management', 'admin-api-users');
?>

<div class="page-header d-flex align-items-center justify-content-between">
  <div>
    <h1>API User Management</h1>
    <p class="text-muted">Activate, monitor and manage B2B API users.</p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#activateModal">
    <i class="bi bi-person-plus me-1"></i> Activate API User
  </button>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Filters -->
<form method="GET" class="d-flex gap-2 mb-4 flex-wrap">
  <input type="text" name="q" class="form-control form-control-sm" style="max-width:240px"
         placeholder="Search name, email, phone" value="<?= htmlspecialchars($search) ?>">
  <select name="status" class="form-select form-select-sm" style="max-width:160px">
    <option value="">All Status</option>
    <option value="active"   <?= $filterStatus === 'active'   ? 'selected' : '' ?>>Active</option>
    <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">Filter</button>
  <a href="?" class="btn btn-outline-secondary btn-sm">Clear</a>
</form>

<!-- Table -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-0">
    <?php if (empty($apiUsers)): ?>
      <div class="text-center text-muted py-5">
        <i class="bi bi-people fs-1 d-block mb-2"></i>No API users yet. Activate one above.
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>User</th>
            <th>Status</th>
            <th class="text-center">Active Keys</th>
            <th class="text-end">Wallet</th>
            <th class="text-end">API Volume</th>
            <th class="text-center">Txns</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($apiUsers as $u): ?>
          <tr>
            <td>
              <div class="fw-medium"><?= htmlspecialchars($u['full_name']) ?></div>
              <div class="small text-muted"><?= htmlspecialchars($u['email']) ?></div>
              <div class="small text-muted"><?= htmlspecialchars($u['phone']) ?></div>
            </td>
            <td>
              <?php $badge = ($u['api_status'] ?? 'inactive') === 'active' ? 'success' : 'secondary'; ?>
              <span class="badge bg-<?= $badge ?>-subtle text-<?= $badge ?> text-capitalize">
                <?= $u['api_status'] ?? 'inactive' ?>
              </span>
            </td>
            <td class="text-center"><?= $u['active_keys'] ?></td>
            <td class="text-end fw-medium">₦<?= number_format((float)$u['wallet_balance'], 2) ?></td>
            <td class="text-end text-success fw-medium">₦<?= number_format((float)$u['api_volume'], 2) ?></td>
            <td class="text-center"><?= number_format((int)$u['total_api_txns']) ?></td>
            <td>
              <?php if (($u['api_status'] ?? 'inactive') === 'active'): ?>
              <form method="POST" class="d-inline">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <input type="hidden" name="action" value="deactivate">
                <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Deactivate this API user?')">
                  Deactivate
                </button>
              </form>
              <?php else: ?>
              <form method="POST" class="d-inline">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <input type="hidden" name="action" value="activate">
                <button class="btn btn-sm btn-outline-success">Activate</button>
              </form>
              <?php endif; ?>
              <a href="/admin/user-detail.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary ms-1">
                <i class="bi bi-eye"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Activate Modal -->
<div class="modal fade" id="activateModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Activate API User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="activate">
        <div class="mb-3">
          <label class="form-label fw-medium">Select User</label>
          <select name="user_id" class="form-select" required>
            <option value="">Choose a user…</option>
            <?php foreach ($candidates as $c): ?>
              <option value="<?= $c['id'] ?>">
                <?= htmlspecialchars($c['full_name']) ?> — <?= htmlspecialchars($c['email']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="alert alert-info small">
          This will set the user's account type to <strong>API</strong> and enable API key generation.
          API users pay wholesale (API_RESELLER) pricing.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Activate API Access</button>
      </div>
    </form>
  </div>
</div>

<?php render_footer(); ?>
