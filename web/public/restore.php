<?php
require __DIR__ . '/_auth.php';
requireRole(['admin']);

$pdo = pdo();

// Restore from uploaded file
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore') {
    csrf_verify();
    
    if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        $backupContent = file_get_contents($_FILES['backup_file']['tmp_name']);
        $backupData = json_decode($backupContent, true);
        
        if ($backupData && isset($backupData['tables'])) {
            try {
                // Disable foreign key checks
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                
                // Clear existing data
                $tables = array_keys($backupData['tables']);
                foreach ($tables as $table) {
                    $pdo->exec("TRUNCATE TABLE $table");
                }
                
                // Restore data
                foreach ($backupData['tables'] as $table => $rows) {
                    if (!empty($rows)) {
                        $columns = array_keys($rows[0]);
                        $placeholders = str_repeat('?,', count($columns) - 1) . '?';
                        $sql = "INSERT INTO $table (" . implode(',', $columns) . ") VALUES ($placeholders)";
                        $stmt = $pdo->prepare($sql);
                        
                        foreach ($rows as $row) {
                            $stmt->execute(array_values($row));
                        }
                    }
                }
                
                // Re-enable foreign key checks
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                
                writeAudit((int)($_SESSION['user_id'] ?? 0), 'restore_success', ['filename' => $_FILES['backup_file']['name']]);
                
                $success = "Data berhasil direstore dari backup!";
            } catch (Exception $e) {
                $error = "Error saat restore: " . $e->getMessage();
            }
        } else {
            $error = "File backup tidak valid!";
        }
    } else {
        $error = "File backup tidak ditemukan!";
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <title>Restore Data - Sistem RFID Enterprise</title>
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
              <i class="fas fa-upload"></i>
            </span>
            <div class="page-heading__label">
              <h2>Restore Data</h2>
              <p class="page-heading__description">Restore data dari file backup</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php if (isset($success)): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Restore Form -->
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">Restore dari File Backup</h5>
      </div>
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="restore">
          
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-1"></i>
            <strong>Peringatan:</strong> Restore akan menghapus semua data yang ada dan menggantinya dengan data dari backup. 
            Pastikan Anda sudah membuat backup terbaru sebelum melakukan restore.
          </div>
          
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Pilih File Backup</label>
              <input type="file" name="backup_file" class="form-control" accept=".json" required />
              <div class="form-text">Pilih file backup dengan ekstensi .json</div>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Yakin ingin restore data? Semua data saat ini akan dihapus!')">
                <i class="fas fa-upload me-1"></i>Restore Data
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
