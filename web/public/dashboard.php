<?php
require __DIR__ . '/_auth.php';
requireRole(['admin', 'teacher']);

$pdo = pdo();
$today = (new DateTime('now'))->format('Y-m-d');
$schoolName = getSetting('sekolah_NAME', 'SMA Peradaban Bumiayu');

// Statistik hari ini
$stats = [
    'total_users' => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_active=1 AND role="student"')->fetchColumn(),
    'today_scans' => 0,
    'late_today'  => 0,
    'absent_today' => 0
];

$targetDate = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : $today;
$latestDate = $pdo->query('SELECT DATE(MAX(ts)) FROM kehadiran WHERE user_id IS NOT NULL')->fetchColumn();

$summaryStmt = $pdo->prepare(
    'SELECT 
        COUNT(DISTINCT COALESCE(CAST(user_id AS CHAR), uid_hex)) AS hadir,
        COUNT(DISTINCT CASE WHEN (
            JSON_UNQUOTE(JSON_EXTRACT(raw_json, "$.status")) = "late" OR JSON_EXTRACT(raw_json, "$.is_late") = true
        ) THEN COALESCE(CAST(user_id AS CHAR), uid_hex) END) AS terlambat
     FROM kehadiran
     WHERE DATE(ts) = ?'
);
$summaryStmt->execute([$targetDate]);
$row = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: ['hadir' => 0, 'terlambat' => 0];

if ((int)$row['hadir'] === 0 && $latestDate && $targetDate === $today && $latestDate !== $today) {
    $targetDate = $latestDate;
    $summaryStmt->execute([$targetDate]);
    $row = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: ['hadir' => 0, 'terlambat' => 0];
}

$stats['today_scans'] = (int)$row['hadir'];
$stats['late_today'] = (int)$row['terlambat'];

// Hitung yang tidak hadir hari ini
$stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE is_active=1 AND role="student" AND id NOT IN (SELECT DISTINCT user_id FROM kehadiran WHERE DATE(ts)=? AND user_id IS NOT NULL)');
$stmt->execute([$targetDate]);
$stats['absent_today'] = (int)$stmt->fetchColumn();

// Data untuk chart (7 hari terakhir berdasarkan tanggal pilihan)
$chartData = [];
$baseDate = new DateTime($targetDate);
$countStmt = $pdo->prepare('SELECT COUNT(DISTINCT COALESCE(CAST(user_id AS CHAR), uid_hex)) FROM kehadiran WHERE DATE(ts) = ?');
for ($i = 6; $i >= 0; $i--) {
    $dateObj = (clone $baseDate)->modify("-$i days");
    $dateStr = $dateObj->format('Y-m-d');
    $countStmt->execute([$dateStr]);
    $chartData[] = ['date' => $dateStr, 'scans' => (int)$countStmt->fetchColumn()];
}

// Notifikasi terbaru
$notifications = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC LIMIT 5');
$notifications->execute([$_SESSION['user_id']]);
$notifs = $notifications->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <title>Dashboard - Sistem Kehadiran RFID Enterprise</title>
  <style>
    body {
      background-color: #f8f9fa;
    }
    .card {
      border: none;
      border-radius: 15px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 15px rgba(0,0,0,0.2);
    }
    .stat-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      cursor: pointer;
      position: relative;
    }
    .stat-card.success {
      background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    }
    .stat-card.warning {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }
    .stat-card.danger {
      background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }
    .chart-container {
      position: relative;
      height: 300px;
    }
    .notification-item {
      border-left: 4px solid #007bff;
      transition: all 0.3s ease;
    }
    .notification-item:hover {
      background-color: #f8f9fa;
      transform: translateX(5px);
    }
  </style>
</head>
<body data-dashboard-date="<?= htmlspecialchars($targetDate) ?>">
<?php include __DIR__ . '/_sidebar.php'; ?>

<?php
try {
    $debugStmt = $pdo->query('SELECT COUNT(*) FROM kehadiran');
    $totalAll = (int)$debugStmt->fetchColumn();
    $debugStmt = $pdo->query("SELECT DATE(MAX(ts)) FROM kehadiran WHERE user_id IS NOT NULL");
    $latestDebug = $debugStmt->fetchColumn();
    if ($totalAll === 0) {
        echo '<div class="alert alert-warning m-3">Tabel kehadiran masih kosong. Tambahkan data manual atau via API agar dashboard terisi.</div>';
    } elseif ($latestDebug && $latestDebug !== date('Y-m-d')) {
        echo '<div class="alert alert-info m-3">Data terbaru ada pada tanggal ' . htmlspecialchars(formatDateIndo($latestDebug, true)) . '.</div>';
    }
} catch (Throwable $dbgErr) {
    // ignore
}
?>

<div class="main-content">
  <div class="container-fluid py-4">
    <div class="row">
      <div class="col-12 page-heading">
        <div class="page-heading__content">
          <div class="page-heading__title">
            <span class="page-heading__icon">
              <i class="fas fa-tachometer-alt"></i>
            </span>
            <div class="page-heading__label">
              <h2>Ringkasan Kehadiran</h2>
              <div class="page-heading__description">
                <div class="page-heading__meta page-heading__meta-school">
                  <i class="fas fa-school me-1"></i><?= htmlspecialchars($schoolName) ?>
                </div>
                <div class="page-heading__meta page-heading__meta-date">
                  <i class="fas fa-calendar me-1"></i><?= htmlspecialchars(formatDateIndo($targetDate, true)) ?><?= ($targetDate !== $today) ? ' (riwayat)' : '' ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-12">
        <div class="card mb-4">
          <div class="card-body">
            <form class="row gx-2 gy-2 align-items-end" method="get">
              <div class="col-sm-3">
                <label class="form-label small text-uppercase">Tampilkan tanggal</label>
                <input type="date" class="form-control" name="date" value="<?= htmlspecialchars($targetDate) ?>" />
              </div>
              <div class="col-sm-2">
                <button type="submit" class="btn btn-primary w-100">Lihat</button>
              </div>
              <?php if (isset($_GET['date']) && $_GET['date'] !== $today): ?>
                <div class="col-sm-2">
                  <a class="btn btn-outline-secondary w-100" href="/kehadiran/web/public/dashboard.php">Reset</a>
                </div>
              <?php endif; ?>
            </form>
          </div>
        </div>
      </div>
    </div>
    <!-- Statistik Cards -->
    <div class="row g-4 mb-4">
      <div class="col-md-3">
        <div class="card stat-card" data-bs-toggle="modal" data-bs-target="#detailModalSiswa" role="button">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="card-title">Total Siswa</h6>
                <h3 class="mb-0" id="metricTotalStudents"><?= $stats['total_users'] ?></h3>
              </div>
              <div class="align-self-center">
                <i class="fas fa-users fa-2x opacity-75"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card stat-card success" data-bs-toggle="modal" data-bs-target="#detailModalHadir" role="button">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="card-title">Hadir</h6>
                <h3 class="mb-0" id="metricPresent"><?= $stats['today_scans'] ?></h3>
              </div>
              <div class="align-self-center">
                <i class="fas fa-check-circle fa-2x opacity-75"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-3">
        <div class="card stat-card warning" data-bs-toggle="modal" data-bs-target="#detailModalTerlambat" role="button">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="card-title">Terlambat</h6>
                <h3 class="mb-0" id="metricLate"><?= $stats['late_today'] ?></h3>
              </div>
              <div class="align-self-center">
                <i class="fas fa-clock fa-2x opacity-75"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-3">
        <div class="card stat-card danger" data-bs-toggle="modal" data-bs-target="#detailModalTidakHadir" role="button">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="card-title">Tidak Hadir</h6>
                <h3 class="mb-0" id="metricAbsent"><?= $stats['absent_today'] ?></h3>
              </div>
              <div class="align-self-center">
                <i class="fas fa-times-circle fa-2x opacity-75"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Chart dan Notifikasi -->
    <div class="row g-4">
      <div class="col-md-8">
        <div class="card">
          <div class="card-header bg-white">
            <h5 class="card-title mb-0">
              <i class="fas fa-chart-line me-2 text-primary"></i>Grafik Kehadiran 7 Hari Terakhir
            </h5>
          </div>
          <div class="card-body">
            <div class="chart-container">
              <canvas id="attendanceChart"></canvas>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-4">
        <div class="card">
          <div class="card-header bg-white">
            <h5 class="card-title mb-0">
              <i class="fas fa-bell me-2 text-primary"></i>Notifikasi Terbaru
            </h5>
          </div>
          <div class="card-body" id="notificationList">
            <?php if (empty($notifs)): ?>
              <div class="text-center py-4">
                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                <p class="text-muted">Tidak ada notifikasi</p>
              </div>
            <?php else: ?>
              <?php foreach ($notifs as $notif): ?>
                <div class="notification-item mb-3 p-3 bg-light rounded">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <h6 class="mb-1"><?= htmlspecialchars($notif['title']) ?></h6>
                      <p class="mb-1 text-muted small"><?= htmlspecialchars($notif['message']) ?></p>
                      <small class="text-muted">
                        <i class="fas fa-clock me-1"></i><?= htmlspecialchars(formatTimeIndo($notif['created_at'])) ?>
                      </small>
                    </div>
                    <span class="badge bg-<?= $notif['type'] === 'error' ? 'danger' : ($notif['type'] === 'warning' ? 'warning' : ($notif['type'] === 'success' ? 'success' : 'info')) ?>">
                      <?= ucfirst($notif['type']) ?>
                    </span>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Detail Modals -->
<div class="modal fade" id="detailModalSiswa" tabindex="-1" aria-labelledby="detailModalSiswaLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailModalSiswaLabel">Daftar Siswa Aktif</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-sm" id="tableStudents">
            <thead>
              <tr>
                <th>Nama</th>
                <th>Username</th>
                <th>Kelas</th>
                <th>UID</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="detailModalHadir" tabindex="-1" aria-labelledby="detailModalHadirLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailModalHadirLabel">Siswa Hadir Hari Ini</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-sm" id="tablePresent">
            <thead>
              <tr>
                <th>Nama</th>
                <th>Kelas</th>
                <th>Scan Pertama</th>
                <th>Scan Terakhir</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="detailModalTerlambat" tabindex="-1" aria-labelledby="detailModalTerlambatLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailModalTerlambatLabel">Siswa Terlambat</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-sm" id="tableLate">
            <thead>
              <tr>
                <th>Nama</th>
                <th>Kelas</th>
                <th>Waktu</th>
                <th>Catatan</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="detailModalTidakHadir" tabindex="-1" aria-labelledby="detailModalTidakHadirLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailModalTidakHadirLabel">Siswa Tidak Hadir Hari Ini</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-sm" id="tableAbsent">
            <thead>
              <tr>
                <th>Nama</th>
                <th>Kelas</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const selectedDate = document.body.dataset.dashboardDate || new Date().toISOString().slice(0,10);
const chartCtx = document.getElementById('attendanceChart').getContext('2d');
let chartData = <?= json_encode($chartData) ?>;
const attendanceChart = new Chart(chartCtx, {
  type: 'line',
  data: {
    labels: chartData.map(d => new Date(d.date).toLocaleDateString('id-ID')),
    datasets: [{
      label: 'Jumlah Scan',
      data: chartData.map(d => d.scans),
      borderColor: 'rgb(75, 192, 192)',
      backgroundColor: 'rgba(75, 192, 192, 0.2)',
      tension: 0.1,
      fill: true
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.1)' } },
      x: { grid: { color: 'rgba(0,0,0,0.1)' } }
    },
    plugins: { legend: { display: false } }
  }
});

function renderNotifications(items) {
  const container = document.getElementById('notificationList');
  if (!container) return;
  if (!items.length) {
    container.innerHTML = `
      <div class="text-center py-4">
        <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
        <p class="text-muted">Tidak ada notifikasi</p>
      </div>
    `;
    return;
  }
  container.innerHTML = items.map(item => {
    const badgeClass = item.type === 'error' ? 'danger' : (item.type === 'warning' ? 'warning' : (item.type === 'success' ? 'success' : 'info'));
    const timeLabel = item.created_at
      ? new Date(item.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false }).replace(/\./g, ':')
      : '';
    return `
      <div class="notification-item mb-3 p-3 bg-light rounded">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h6 class="mb-1">${item.title ? item.title : 'Notifikasi'}</h6>
            <p class="mb-1 text-muted small">${item.message ? item.message : ''}</p>
            ${timeLabel ? `<small class="text-muted"><i class='fas fa-clock me-1'></i>${timeLabel}</small>` : ''}
          </div>
          <span class="badge bg-${badgeClass}">${item.type ? item.type.charAt(0).toUpperCase() + item.type.slice(1) : 'Info'}</span>
        </div>
      </div>
    `;
  }).join('');
}

async function refreshDashboardOverview() {
  try {
    const response = await fetch(`/kehadiran/web/api/dashboard_overview.php?date=${encodeURIComponent(selectedDate)}`);
    if (!response.ok) return;
    const payload = await response.json();
    if (!payload.ok) return;

    const summary = payload.summary || {};
    const chartPayload = payload.chart || [];
    const notifications = payload.notifications || [];

    const totalNode = document.getElementById('metricTotalStudents');
    if (totalNode) totalNode.textContent = summary.total_students ?? 0;
    const presentNode = document.getElementById('metricPresent');
    if (presentNode) presentNode.textContent = summary.hadir ?? 0;
    const lateNode = document.getElementById('metricLate');
    if (lateNode) lateNode.textContent = summary.terlambat ?? 0;
    const absentNode = document.getElementById('metricAbsent');
    if (absentNode) absentNode.textContent = summary.tidak_hadir ?? 0;

    chartData = chartPayload;
    attendanceChart.data.labels = chartData.map(d => new Date(d.date).toLocaleDateString('id-ID'));
    attendanceChart.data.datasets[0].data = chartData.map(d => d.scans);
    attendanceChart.update();

    renderNotifications(notifications);
  } catch (error) {
    console.error('refreshDashboardOverview error', error);
  }
}

function formatDateTimeIndo(value, includeSeconds = false) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  const options = { day: 'numeric', month: 'long', year: 'numeric' };
  const datePart = date.toLocaleDateString('id-ID', options);
  const timeOptions = { hour: '2-digit', minute: '2-digit', hour12: false };
  if (includeSeconds) timeOptions.second = '2-digit';
  const timePart = date.toLocaleTimeString('id-ID', timeOptions).replace(/\./g, ':');
  return `${datePart} ${timePart}`;
}

function populateTable(tableId, rows, formatter) {
  const tbody = document.querySelector(`#${tableId} tbody`);
  if (!tbody) return;
  tbody.innerHTML = '';
  if (!rows.length) {
    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = tbody.closest('table').querySelectorAll('thead th').length;
    td.className = 'text-center text-muted py-3';
    td.textContent = 'Tidak ada data.';
    tr.appendChild(td);
    tbody.appendChild(tr);
    return;
  }
  rows.forEach(row => {
    const tr = document.createElement('tr');
    formatter(row).forEach(cell => {
      const td = document.createElement('td');
      td.innerHTML = cell;
      tr.appendChild(td);
    });
    tbody.appendChild(tr);
  });
}

async function loadDetail(type) {
  try {
    const response = await fetch(`/kehadiran/web/api/dashboard_details.php?type=${encodeURIComponent(type)}&date=${encodeURIComponent(selectedDate)}`);
    if (!response.ok) throw new Error('Gagal memuat data');
    const payload = await response.json();
    switch (type) {
      case 'students':
        populateTable('tableStudents', payload.data || [], row => [
          row.name ? row.name : '-',
          row.username ? row.username : '-',
          row.kelas ? row.kelas : '-',
          row.uid_hex ? `<code>${row.uid_hex}</code>` : '-',
          row.is_active == 1 ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>'
        ]);
        break;
      case 'present':
        populateTable('tablePresent', payload.data || [], row => [
          row.name || '-',
          row.kelas || '-',
          row.first_scan ? formatDateTimeIndo(row.first_scan) : '-',
          row.last_scan ? formatDateTimeIndo(row.last_scan) : '-'
        ]);
        break;
      case 'late':
        populateTable('tableLate', payload.data || [], row => [
          row.name || '-',
          row.kelas || '-',
          row.ts ? formatDateTimeIndo(row.ts, true) : '-',
          row.notes ? row.notes : '-'
        ]);
        break;
      case 'absent':
        populateTable('tableAbsent', payload.data || [], row => [
          row.name || '-',
          row.kelas || '-'
        ]);
        break;
    }
  } catch (err) {
    console.error(err);
  }
}

const modalTypeMap = {
  'Siswa': 'students',
  'Hadir': 'present',
  'Terlambat': 'late',
  'TidakHadir': 'absent'
};

Object.keys(modalTypeMap).forEach(key => {
  const modal = document.getElementById(`detailModal${key}`);
  if (modal) {
    modal.addEventListener('show.bs.modal', () => {
      loadDetail(modalTypeMap[key]);
    });
  }
});

refreshDashboardOverview();
setInterval(refreshDashboardOverview, 5000);
</script>
</body>
</html>
