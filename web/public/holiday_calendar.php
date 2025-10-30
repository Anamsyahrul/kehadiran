<?php
require __DIR__ . '/_auth.php';
requireRole(['admin']);

$pdo = pdo();

// Add holiday
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    csrf_verify();
    $date = $_POST['date'] ?? '';
    $title = trim($_POST['title'] ?? '');
    
    if ($date && $title) {
        $stmt = $pdo->prepare('INSERT INTO holiday_calendar(holiday_date, title) VALUES(?,?) ON DUPLICATE KEY UPDATE title=VALUES(title)');
        $stmt->execute([$date, $title]);
        writeAudit((int)($_SESSION['user_id'] ?? 0), 'holiday_add', ['date'=>$date, 'title'=>$title]);
    }
    header('Location: /kehadiran/web/public/holiday_calendar.php');
    exit;
}

// Delete holiday
if (($_GET['action'] ?? '') === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare('DELETE FROM holiday_calendar WHERE id = ?');
        $stmt->execute([$id]);
        writeAudit((int)($_SESSION['user_id'] ?? 0), 'holiday_delete', ['id'=>$id]);
    }
    header('Location: /kehadiran/web/public/holiday_calendar.php');
    exit;
}

// Get holidays
$year = $_GET['year'] ?? date('Y');
$stmt = $pdo->prepare('SELECT * FROM holiday_calendar WHERE YEAR(holiday_date) = ? ORDER BY holiday_date');
$stmt->execute([$year]);
$holidays = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <title>Kalender Libur - Sistem RFID Enterprise</title>
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
              <i class="fas fa-calendar-alt"></i>
            </span>
            <div class="page-heading__label">
              <h2>Kalender Hari Libur</h2>
              <p class="page-heading__description">Kelola hari libur untuk sistem kehadiran</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Holiday Form -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title mb-0">Tambah Hari Libur</h5>
      </div>
      <div class="card-body">
        <form method="post" class="row g-3">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="add">
          <div class="col-md-4">
            <label class="form-label">Tanggal</label>
            <input type="date" name="date" class="form-control" required />
          </div>
          <div class="col-md-6">
            <label class="form-label">Nama Hari Libur</label>
            <input type="text" name="title" class="form-control" placeholder="Contoh: Hari Kemerdekaan RI" required />
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-primary"><i class="fas fa-plus me-1"></i>Tambah</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Year Filter -->
    <div class="card mb-4">
      <div class="card-body">
        <form class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Tahun</label>
            <select name="year" class="form-select" onchange="this.form.submit()">
              <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>
        </form>
      </div>
    </div>

    <!-- Holidays List -->
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          Hari Libur Tahun <?= $year ?>
          <small class="text-muted">(<?= count($holidays) ?> hari)</small>
        </h5>
      </div>
      <div class="card-body">
        <?php if (empty($holidays)): ?>
          <div class="text-center py-4">
            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Tidak ada hari libur</h5>
            <p class="text-muted">Tambahkan hari libur menggunakan form di atas</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-striped table-hover">
              <thead class="table-dark">
                <tr>
                  <th>Tanggal</th>
                  <th>Hari</th>
                  <th>Nama Hari Libur</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($holidays as $holiday): ?>
                  <?php
                  $holidayDate = new DateTime($holiday['holiday_date']);
                  $isPast = $holidayDate < new DateTime();
                  $isToday = $holidayDate->format('Y-m-d') === date('Y-m-d');
                  ?>
                  <tr class="<?= $isToday ? 'table-warning' : '' ?>">
                    <td>
                      <strong><?= htmlspecialchars(formatDateIndo($holidayDate->format('Y-m-d'))) ?></strong>
                      <?php if ($isToday): ?>
                        <span class="badge bg-warning ms-1">Hari Ini</span>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars(dayNameIndo($holidayDate->format('Y-m-d'))) ?></td>
                    <td><?= htmlspecialchars($holiday['title']) ?></td>
                    <td>
                      <?php if ($isPast): ?>
                        <span class="badge bg-secondary">Sudah Lewat</span>
                      <?php else: ?>
                        <span class="badge bg-info">Akan Datang</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <a class="btn btn-sm btn-danger" href="?action=delete&id=<?= $holiday['id'] ?>" onclick="return confirm('Hapus hari libur ini?')">
                        <i class="fas fa-trash me-1"></i>Hapus
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
