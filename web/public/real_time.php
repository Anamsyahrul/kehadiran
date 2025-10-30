<?php
require __DIR__ . '/_auth.php';
requireRole(['admin', 'teacher']);

$pdo = pdo();
$schoolName = getSetting('sekolah_NAME', 'SMA Peradaban Bumiayu');
$today = (new DateTime('now'))->format('Y-m-d');
$targetDate = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : $today;
$latestDate = $pdo->query('SELECT DATE(MAX(ts)) FROM kehadiran')->fetchColumn();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <title>Real-time Monitoring - Sistem RFID Enterprise</title>
  <style>
    body {
      background-color: #f8f9fa;
    }
    .card {
      border: none;
      border-radius: 15px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .scan-item {
      animation: slideIn 0.5s ease-out;
    }
    @keyframes slideIn {
      from { transform: translateX(-100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    .status-late { border-left: 4px solid #ffc107; }
    .status-ontime { border-left: 4px solid #198754; }
    .status-weekend { border-left: 4px solid #6c757d; }
  </style>
</head>
<body data-realtime-date="<?= htmlspecialchars($targetDate) ?>">
<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main-content">
  <div class="container-fluid py-4">
    <div class="row">
      <div class="col-12 page-heading">
        <div class="page-heading__content">
          <div class="page-heading__title">
            <span class="page-heading__icon">
              <i class="fas fa-broadcast-tower"></i>
            </span>
            <div class="page-heading__label">
              <h2>Real-time Monitoring</h2>
              <p class="page-heading__description"><?= htmlspecialchars($schoolName) ?> · Monitoring kehadiran secara real-time</p>
            </div>
          </div>
          <p class="page-heading__description small" id="realTimeDateInfo"></p>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-12">
        <div class="card mb-4">
          <div class="card-body">
            <form class="row g-3 align-items-end" method="get">
              <div class="col-sm-3 col-md-2">
                <label class="form-label small text-uppercase">Tanggal</label>
                <input type="date" class="form-control" name="date" value="<?= htmlspecialchars($targetDate) ?>" />
              </div>
              <div class="col-sm-2 col-md-1">
                <button class="btn btn-primary w-100" type="submit">Lihat</button>
              </div>
              <?php if (isset($_GET['date']) && $_GET['date'] !== $today): ?>
                <div class="col-sm-2 col-md-1">
                  <a class="btn btn-outline-secondary w-100" href="/kehadiran/web/public/real_time.php">Reset</a>
                </div>
              <?php endif; ?>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Status Cards -->
    <div class="row g-4 mb-4">
      <div class="col-md-3">
        <div class="card bg-primary text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="card-title">Total Scan Hari Ini</h6>
                <h3 class="mb-0" id="totalScans">0</h3>
              </div>
              <div class="align-self-center">
                <i class="fas fa-qrcode fa-2x"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-3">
        <div class="card bg-success text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="card-title">Tepat Waktu</h6>
                <h3 class="mb-0" id="ontimeCount">0</h3>
              </div>
              <div class="align-self-center">
                <i class="fas fa-check-circle fa-2x"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-3">
        <div class="card bg-warning text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="card-title">Terlambat</h6>
                <h3 class="mb-0" id="lateCount">0</h3>
              </div>
              <div class="align-self-center">
                <i class="fas fa-clock fa-2x"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-3">
        <div class="card bg-info text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="card-title">Perangkat Aktif</h6>
                <h3 class="mb-0" id="activeDevices">0</h3>
              </div>
              <div class="align-self-center">
                <i class="fas fa-microchip fa-2x"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Real-time Feed -->
    <div class="row">
      <div class="col-md-8">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Feed Real-time</h5>
            <div>
              <span class="badge bg-success" id="connectionStatus">
                <i class="fas fa-circle me-1"></i>Connected
              </span>
              <button class="btn btn-sm btn-outline-secondary ms-2" onclick="clearFeed()">
                <i class="fas fa-trash me-1"></i>Clear
              </button>
            </div>
          </div>
          <div class="card-body" style="height: 500px; overflow-y: auto;" id="feedContainer">
            <div class="text-center text-muted py-4">
              <i class="fas fa-broadcast-tower fa-3x mb-3"></i>
              <h5>Menunggu aktivitas RFID...</h5>
              <p>Scan kartu RFID akan muncul di sini secara real-time</p>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-4">
        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0">Statistik Hari Ini</h5>
          </div>
          <div class="card-body">
            <canvas id="todayChart" height="200"></canvas>
          </div>
        </div>
        
        <div class="card mt-4">
          <div class="card-header">
            <h5 class="card-title mb-0">Perangkat RFID</h5>
          </div>
          <div class="card-body">
            <div id="devicesList">
              <!-- Devices will be loaded here -->
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let chart;
let feedCount = 0;

// Initialize chart
const ctx = document.getElementById('todayChart').getContext('2d');
chart = new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Tepat Waktu', 'Terlambat'],
        datasets: [{
            data: [0, 0],
            backgroundColor: ['#198754', '#ffc107']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

// Real-time updates
function updateStats() {
    fetch('/kehadiran/web/api/realtime_stats.php')
    const selectedDate = document.body.dataset.realtimeDate || new Date().toISOString().slice(0,10);
    fetch(`/kehadiran/web/api/realtime_stats.php?date=${encodeURIComponent(selectedDate)}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('totalScans').textContent = data.totalScans || 0;
            document.getElementById('ontimeCount').textContent = data.ontimeCount || 0;
            document.getElementById('lateCount').textContent = data.lateCount || 0;
            document.getElementById('activeDevices').textContent = data.activeDevices || 0;
            if (data.targetDate) {
                const info = document.getElementById('realTimeDateInfo');
                if (info) {
                    const today = new Date().toISOString().slice(0,10);
                    if (data.targetDate !== today) {
                        info.textContent = `Menampilkan data tanggal ${new Date(data.targetDate).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}`;
                    } else {
                        info.textContent = '';
                    }
                }
            }

            // Update chart
            chart.data.datasets[0].data = [data.ontimeCount || 0, data.lateCount || 0];
            chart.update();
        })
        .catch(error => {
            console.error('Error fetching stats:', error);
            document.getElementById('connectionStatus').innerHTML = '<i class="fas fa-circle me-1"></i>Disconnected';
            document.getElementById('connectionStatus').className = 'badge bg-danger';
        });
}

function loadRecentScans() {
    const selectedDate = document.body.dataset.realtimeDate || new Date().toISOString().slice(0,10);
    fetch(`/kehadiran/web/api/recent_scans.php?date=${encodeURIComponent(selectedDate)}`)
        .then(response => response.json())
        .then(data => {
            if (data.scans && data.scans.length > 0) {
                const container = document.getElementById('feedContainer');
                if (feedCount === 0) {
                    container.innerHTML = '';
                }
                
                data.scans.forEach(scan => {
            if (scan.id > feedCount) {
                        addScanToFeed(scan);
                        feedCount = Math.max(feedCount, scan.id);
                    }
                });
            }
        });
}

function addScanToFeed(scan) {
    const container = document.getElementById('feedContainer');
    const scanDiv = document.createElement('div');
    const status = (scan.status || (scan.is_late ? 'late' : 'present')).toLowerCase();
    const statusClass = status === 'late' ? 'late' : (status === 'checkout' ? 'ontime' : 'ontime');
    scanDiv.className = 'scan-item mb-3 p-3 border rounded status-' + statusClass;
    
    const timeAgo = new Date(scan.ts).toLocaleTimeString('id-ID');
    let statusBadge;
    if (status === 'late') {
        statusBadge = '<span class="badge bg-warning"><i class="fas fa-clock me-1"></i>Terlambat</span>';
    } else if (status === 'checkout') {
        statusBadge = '<span class="badge bg-info text-dark"><i class="fas fa-sign-out-alt me-1"></i>Check-out</span>';
    } else {
        statusBadge = '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Tepat Waktu</span>';
    }
    
    scanDiv.innerHTML = `
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h6 class="mb-1">${scan.user_name || 'Unknown User'}</h6>
                <p class="mb-1 text-muted">${scan.kelas || 'N/A'} | ${scan.device_name || 'Unknown Device'}</p>
                <small class="text-muted">${timeAgo}</small>
            </div>
            <div>
                ${statusBadge}
            </div>
        </div>
    `;
    
    container.insertBefore(scanDiv, container.firstChild);
    
    // Keep only last 50 items
    while (container.children.length > 50) {
        container.removeChild(container.lastChild);
    }
}

function clearFeed() {
    document.getElementById('feedContainer').innerHTML = `
        <div class="text-center text-muted py-4">
            <i class="fas fa-broadcast-tower fa-3x mb-3"></i>
            <h5>Feed dibersihkan</h5>
            <p>Menunggu aktivitas RFID baru...</p>
        </div>
    `;
    feedCount = 0;
}

// Update every 2 seconds
setInterval(updateStats, 2000);
setInterval(loadRecentScans, 2000);

// Initial load
updateStats();
loadRecentScans();
</script>
</body>
</html>
