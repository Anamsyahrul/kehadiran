<?php
require __DIR__ . '/_auth.php';
requireRole(['admin']);

$pdo = pdo();

// Generate backup
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'backup') {
    csrf_verify();
    
    $backupData = [
        'timestamp' => date('c'),
        'version' => '1.0',
        'tables' => []
    ];
    
    // Get all tables
    $tables = ['users', 'devices', 'kehadiran', 'notifications', 'audit_logs', 'system_settings', 'holiday_calendar', 'kehadiran_rules', 'remember_tokens', 'login_attempts'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT * FROM $table");
        $backupData['tables'][$table] = $stmt->fetchAll();
    }
    
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.json';
    $filepath = __DIR__ . '/../storage/' . $filename;
    
    // Ensure storage directory exists
    if (!is_dir(__DIR__ . '/../storage')) {
        mkdir(__DIR__ . '/../storage', 0755, true);
    }
    
    file_put_contents($filepath, json_encode($backupData, JSON_PRETTY_PRINT));
    
    writeAudit((int)($_SESSION['user_id'] ?? 0), 'backup_create', ['filename' => $filename]);
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    readfile($filepath);
    exit;
}

// Get existing backups
$backups = [];
$storageDir = __DIR__ . '/../storage';
if (is_dir($storageDir)) {
    $files = glob($storageDir . '/backup_*.json');
    foreach ($files as $file) {
        $backups[] = [
            'filename' => basename($file),
            'size' => filesize($file),
            'created' => filemtime($file)
        ];
    }
    usort($backups, function($a, $b) { return $b['created'] - $a['created']; });
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <title>Backup Data - Sistem RFID Enterprise</title>
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
              <i class="fas fa-download"></i>
            </span>
            <div class="page-heading__label">
              <h2>Backup Data</h2>
              <p class="page-heading__description">Buat dan kelola backup data sistem</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Backup -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title mb-0">Buat Backup Baru</h5>
      </div>
      <div class="card-body">
        <form method="post">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="backup">
          <div class="row g-3">
            <div class="col-md-8">
              <p class="text-muted mb-0">Backup akan mencakup semua data: pengguna, kehadiran, notifikasi, pengaturan, dan log audit.</p>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-download me-1"></i>Buat Backup
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Existing Backups -->
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">Backup yang Tersedia</h5>
      </div>
      <div class="card-body">
        <?php if (empty($backups)): ?>
          <div class="text-center py-4">
            <i class="fas fa-archive fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Belum ada backup</h5>
            <p class="text-muted">Buat backup pertama Anda menggunakan tombol di atas</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-striped table-hover">
              <thead class="table-dark">
                <tr>
                  <th>Nama File</th>
                  <th>Ukuran</th>
                  <th>Tanggal Dibuat</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($backups as $backup): ?>
                  <tr>
                    <td><code><?= htmlspecialchars($backup['filename']) ?></code></td>
                    <td><?= number_format($backup['size'] / 1024, 2) ?> KB</td>
                    <td><?= htmlspecialchars(formatDateTimeIndo(date('Y-m-d H:i:s', $backup['created']))) ?></td>
                    <td>
                      <a class="btn btn-sm btn-outline-primary" href="/kehadiran/web/storage/<?= urlencode($backup['filename']) ?>" download>
                        <i class="fas fa-download me-1"></i>Download
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
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
