<?php
require __DIR__ . '/_auth.php';
requireRole(['admin']);

$pdo = pdo();

// Filter parameters
$from = $_GET['from'] ?? (new DateTime('-30 days'))->format('Y-m-d');
$to = $_GET['to'] ?? date('Y-m-d');
$user_id = $_GET['user_id'] ?? '';
$action = $_GET['action'] ?? '';

// Build query
$params = [$from . ' 00:00:00', $to . ' 23:59:59'];
$sql = 'SELECT al.*, u.name as user_name, u.username 
        FROM audit_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        WHERE al.created_at BETWEEN ? AND ?';

if ($user_id !== '') {
    $sql .= ' AND al.user_id = ?';
    $params[] = $user_id;
}

if ($action !== '') {
    $sql .= ' AND al.action LIKE ?';
    $params[] = '%' . $action . '%';
}

$sql .= ' ORDER BY al.created_at DESC LIMIT 1000';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get users for filter
$users = $pdo->query('SELECT id, name, username FROM users ORDER BY name')->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <title>Audit Log - Sistem RFID Enterprise</title>
  <style>
    body {
      background-color: #f8f9fa;
    }
    .card {
      border: none;
      border-radius: 15px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main-content">
  <div class="container-fluid py-4">
    <div class="row">
      <div class="col-12 page-heading">
        <div class="page-heading__content">
          <div class="page-heading__title">
            <span class="page-heading__icon">
              <i class="fas fa-clipboard-list"></i>
            </span>
            <div class="page-heading__label">
              <h2>Audit Log</h2>
              <p class="page-heading__description">Catatan aktivitas sistem untuk keamanan dan akuntabilitas</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
      <div class="card-body">
        <form class="row g-3">
          <div class="col-md-2">
            <label class="form-label">Dari Tanggal</label>
            <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control" />
          </div>
          <div class="col-md-2">
            <label class="form-label">Sampai Tanggal</label>
            <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Pengguna</label>
            <select name="user_id" class="form-select">
              <option value="">Semua Pengguna</option>
              <?php foreach ($users as $user): ?>
                <option value="<?= $user['id'] ?>" <?= $user_id == $user['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['username']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Aksi</label>
            <input type="text" name="action" value="<?= htmlspecialchars($action) ?>" placeholder="login, scan, user_create, dll" class="form-control" />
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-primary"><i class="fas fa-search me-1"></i>Filter</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Results Table -->
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          Log Aktivitas
          <small class="text-muted">(<?= count($logs) ?> entri)</small>
        </h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped table-hover table-sm">
            <thead class="table-dark">
              <tr>
                <th>Waktu</th>
                <th>Pengguna</th>
                <th>Aksi</th>
                <th>Detail</th>
                <th>IP Address</th>
                <th>User Agent</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($logs as $log): ?>
                <tr>
                  <td>
                    <small><?= htmlspecialchars(formatDateTimeIndo($log['created_at'], true)) ?></small>
                  </td>
                  <td>
                    <?php if ($log['user_name']): ?>
                      <strong><?= htmlspecialchars($log['user_name']) ?></strong><br>
                      <small class="text-muted"><?= htmlspecialchars($log['username']) ?></small>
                    <?php else: ?>
                      <span class="text-muted">System/Anonymous</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php
                    $actionClass = 'secondary';
                    if (strpos($log['action'], 'login') !== false) $actionClass = 'success';
                    elseif (strpos($log['action'], 'failed') !== false) $actionClass = 'danger';
                    elseif (strpos($log['action'], 'scan') !== false) $actionClass = 'info';
                    elseif (strpos($log['action'], 'user_') !== false) $actionClass = 'warning';
                    ?>
                    <span class="badge bg-<?= $actionClass ?>"><?= htmlspecialchars($log['action']) ?></span>
                  </td>
                  <td>
                    <?php if ($log['details']): ?>
                      <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#detailModal<?= $log['id'] ?>">
                        <i class="fas fa-eye"></i>
                      </button>
                    <?php endif; ?>
                  </td>
                  <td><small><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></small></td>
                  <td><small><?= htmlspecialchars(substr($log['user_agent'] ?? 'N/A', 0, 50)) ?>...</small></td>
                </tr>
                
                <!-- Detail Modal -->
                <?php if ($log['details']): ?>
                  <div class="modal fade" id="detailModal<?= $log['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title">Detail Aktivitas</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                          <pre class="bg-light p-3 rounded"><?= htmlspecialchars($log['details']) ?></pre>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
