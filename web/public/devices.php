<?php
require __DIR__ . '/_auth.php';
requireRole(['admin']);

$pdo = pdo();

// Create / Update device
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $id = trim($_POST['id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $secret = trim($_POST['secret'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if ($id !== '' && $name !== '' && $secret !== '') {
        $stmt = $pdo->prepare('INSERT INTO devices(id, name, device_secret, is_active) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), device_secret=VALUES(device_secret), is_active=VALUES(is_active)');
        $stmt->execute([$id, $name, $secret, $is_active]);
        writeAudit((int)($_SESSION['user_id'] ?? 0), 'device_update', ['id'=>$id]);
    }
    header('Location: /kehadiran/web/public/devices.php');
    exit;
}

// Delete device
if (($_GET['action'] ?? '') === 'delete') {
    $id = trim($_GET['id'] ?? '');
    if ($id !== '') {
        $stmt = $pdo->prepare('DELETE FROM devices WHERE id = ?');
        $stmt->execute([$id]);
        writeAudit((int)($_SESSION['user_id'] ?? 0), 'device_delete', ['id'=>$id]);
    }
    header('Location: /kehadiran/web/public/devices.php');
    exit;
}

$devices = $pdo->query('SELECT * FROM devices ORDER BY id')->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <title>Perangkat RFID - Sistem RFID Enterprise</title>
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
              <i class="fas fa-microchip"></i>
            </span>
            <div class="page-heading__label">
              <h2>Manajemen Perangkat RFID</h2>
              <p class="page-heading__description">Kelola perangkat RFID yang terhubung ke sistem</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <form class="card mb-4" method="post">
      <div class="card-header">
        <h5 class="card-title mb-0">Tambah/Edit Perangkat</h5>
      </div>
      <div class="card-body">
        <?= csrf_input() ?>
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Device ID</label>
            <input class="form-control" name="id" placeholder="esp32-01" required />
          </div>
          <div class="col-md-3">
            <label class="form-label">Nama Perangkat</label>
            <input class="form-control" name="name" placeholder="Perangkat Pintu Utama" required />
          </div>
          <div class="col-md-4">
            <label class="form-label">Device Secret</label>
            <input class="form-control" name="secret" placeholder="esp32-secret-key-2025" required />
          </div>
          <div class="col-md-2">
            <label class="form-label">Status</label>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
              <label class="form-check-label" for="is_active">Aktif</label>
            </div>
          </div>
          <div class="col-12">
            <button class="btn btn-primary">Simpan</button>
          </div>
        </div>
      </div>
    </form>

    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">Daftar Perangkat</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped table-hover">
            <thead class="table-dark">
              <tr>
                <th>ID</th>
                <th>Nama</th>
                <th>Secret</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($devices as $d): ?>
                <tr>
                  <td><code><?= htmlspecialchars($d['id']) ?></code></td>
                  <td><?= htmlspecialchars($d['name']) ?></td>
                  <td><code><?= htmlspecialchars(substr($d['device_secret'], 0, 8)) ?>...</code></td>
                  <td>
                    <span class="badge bg-<?= $d['is_active'] ? 'success' : 'secondary' ?>">
                      <?= $d['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                    </span>
                  </td>
                  <td>
                    <a class="btn btn-sm btn-danger" href="?action=delete&id=<?= urlencode($d['id']) ?>" onclick="return confirm('Hapus perangkat ini?')">
                      <i class="fas fa-trash me-1"></i>Hapus
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        
        <div class="alert alert-info mt-3">
          <h6><i class="fas fa-info-circle me-1"></i>Contoh Device Secret untuk Testing:</h6>
          <code>esp32-secret-key-2025</code><br>
          <small>Gunakan secret ini di firmware ESP32 dan di tabel devices untuk testing endpoint ingest.</small>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
