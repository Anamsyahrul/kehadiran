<?php
require __DIR__ . '/_auth.php';

$pdo = pdo();
$schoolName = getSetting('sekolah_NAME', 'SMA Peradaban Bumiayu');
$userId = (int)($_SESSION['user_id'] ?? 0);
$today = date('Y-m-d');

$startDateObj = new DateTime('today');
$startDateObj->modify('-29 days');
$startDate = $startDateObj->format('Y-m-d');

$historyStmt = $pdo->prepare('SELECT ts, raw_json FROM kehadiran WHERE user_id = ? AND ts >= ? ORDER BY ts DESC');
$historyStmt->execute([$userId, $startDate . ' 00:00:00']);
$records = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

$daily = [];
foreach ($records as $record) {
    $ts = $record['ts'];
    $date = substr($ts, 0, 10);
    if (!isset($daily[$date])) {
        $daily[$date] = [
            'first_ts' => null,
            'last_ts' => null,
            'has_present' => false,
            'has_late' => false,
            'has_excused' => false,
            'has_sick' => false
        ];
    }
    if ($daily[$date]['first_ts'] === null || $ts < $daily[$date]['first_ts']) {
        $daily[$date]['first_ts'] = $ts;
    }
    if ($daily[$date]['last_ts'] === null || $ts > $daily[$date]['last_ts']) {
        $daily[$date]['last_ts'] = $ts;
    }
    $payload = json_decode($record['raw_json'] ?? '[]', true) ?: [];
    $status = strtolower($payload['status'] ?? '');
    $isLateEntry = !empty($payload['is_late']) || $status === 'late';
    switch ($status) {
        case 'excused':
        case 'permission':
            $daily[$date]['has_excused'] = true;
            break;
        case 'sick':
            $daily[$date]['has_sick'] = true;
            break;
        case 'late':
            $daily[$date]['has_present'] = true;
            $daily[$date]['has_late'] = true;
            break;
        case 'checkout':
        case 'present':
            $daily[$date]['has_present'] = true;
            break;
        case 'absent':
            break;
        default:
            if ($isLateEntry) {
                $daily[$date]['has_present'] = true;
                $daily[$date]['has_late'] = true;
            } elseif ($status !== '') {
                $daily[$date]['has_present'] = true;
            }
            break;
    }
}

if (!isset($daily[$today])) {
    $daily[$today] = [
        'first_ts' => null,
        'last_ts' => null,
        'has_present' => false,
        'has_late' => false,
        'has_excused' => false,
        'has_sick' => false
    ];
}

krsort($daily);

$statsCounts = [
    'on_time' => 0,
    'late' => 0,
    'excused' => 0,
    'sick' => 0,
    'alpa' => 0
];
$historyEntries = [];
$historyIndex = [];

foreach ($daily as $date => $info) {
    $hasScans = $info['first_ts'] !== null;
    $statusKey = 'alpa';
    $statusLabel = 'Alpa';
    $badge = 'danger';

    if ($info['has_excused']) {
        $statusKey = 'excused';
        $statusLabel = 'Izin';
        $badge = 'info';
    } elseif ($info['has_sick']) {
        $statusKey = 'sick';
        $statusLabel = 'Sakit';
        $badge = 'primary';
    } elseif ($info['has_present']) {
        if ($info['has_late']) {
            $statusKey = 'late';
            $statusLabel = 'Terlambat';
            $badge = 'warning';
        } else {
            $statusKey = 'on_time';
            $statusLabel = 'Tepat Waktu';
            $badge = 'success';
        }
    }

    $firstTime = $hasScans ? formatTimeIndo($info['first_ts']) : '-';
    $lastTime = $hasScans ? formatTimeIndo($info['last_ts']) : '-';

    $entry = [
        'date' => $date,
        'status_label' => $statusLabel,
        'badge' => $badge,
        'first_time' => $firstTime,
        'last_time' => $lastTime,
        'status_key' => $statusKey,
        'has_scans' => $hasScans
    ];
    $historyEntries[] = $entry;
    $historyIndex[$date] = count($historyEntries) - 1;
    $statsCounts[$statusKey]++;
}

$todayStatus = $historyEntries[$historyIndex[$today]] ?? [
    'date' => $today,
    'status_label' => 'Alpa',
    'badge' => 'danger',
    'first_time' => '-',
    'last_time' => '-',
    'status_key' => 'alpa',
    'has_scans' => false
];

$statMeta = [
    'on_time' => ['label' => 'Tepat Waktu', 'class' => 'text-success', 'icon' => 'fas fa-check-circle'],
    'late' => ['label' => 'Terlambat', 'class' => 'text-warning', 'icon' => 'fas fa-clock'],
    'excused' => ['label' => 'Izin', 'class' => 'text-info', 'icon' => 'fas fa-file-signature'],
    'sick' => ['label' => 'Sakit', 'class' => 'text-primary', 'icon' => 'fas fa-briefcase-medical'],
    'alpa' => ['label' => 'Alpa', 'class' => 'text-danger', 'icon' => 'fas fa-times-circle']
];

$statusMeta = [
    'on_time' => ['icon' => 'fas fa-check-circle', 'text_class' => 'text-success'],
    'late' => ['icon' => 'fas fa-clock', 'text_class' => 'text-warning'],
    'excused' => ['icon' => 'fas fa-file-signature', 'text_class' => 'text-info'],
    'sick' => ['icon' => 'fas fa-briefcase-medical', 'text_class' => 'text-primary'],
    'alpa' => ['icon' => 'fas fa-times-circle', 'text_class' => 'text-danger']
];

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <title>Mobile App - Sistem RFID Enterprise</title>
  <style>
    body {
      background-color: #f8f9fa;
    }
    .card {
      border: none;
      border-radius: 15px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .mobile-card { border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .status-badge { font-size: 0.8rem; }
    .attendance-item { border-left: 4px solid #dee2e6; }
    .attendance-item.status-on_time { border-left-color: #198754; }
    .attendance-item.status-late { border-left-color: #ffc107; }
    .attendance-item.status-excused { border-left-color: #0dcaf0; }
    .attendance-item.status-sick { border-left-color: #0d6efd; }
    .attendance-item.status-alpa { border-left-color: #dc3545; }
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
              <i class="fas fa-mobile-alt"></i>
            </span>
            <div class="page-heading__label">
              <h2>Mobile App View</h2>
              <p class="page-heading__description"><?= htmlspecialchars($schoolName) ?> · Informasi pribadi kehadiran Anda</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Today Status Card -->
    <div class="row mb-4">
      <div class="col-12">
        <div class="card mobile-card">
          <div class="card-body text-center">
            <?php $todayMeta = $statusMeta[$todayStatus['status_key']] ?? $statusMeta['alpa']; ?>
            <div class="mb-3">
              <i class="<?= $todayMeta['icon'] ?> fa-3x <?= $todayMeta['text_class'] ?>"></i>
            </div>
            <h4 class="<?= $todayMeta['text_class'] ?>"><?= htmlspecialchars($todayStatus['status_label']) ?></h4>
            <p class="text-muted mb-2">Tanggal <?= htmlspecialchars(formatDateIndo($todayStatus['date'] ?? $today, true)) ?></p>
            <span class="badge bg-<?= $todayStatus['badge'] ?> status-badge">Status <?= htmlspecialchars($todayStatus['status_label']) ?></span>
            <div class="mt-3">
              <?php if (in_array($todayStatus['status_key'], ['on_time','late'], true) && $todayStatus['has_scans']): ?>
                <p class="text-muted mb-1"><i class="fas fa-sign-in-alt me-2"></i>Scan pertama: <?= htmlspecialchars($todayStatus['first_time']) ?></p>
                <p class="text-muted mb-0"><i class="fas fa-sign-out-alt me-2"></i>Scan terakhir: <?= htmlspecialchars($todayStatus['last_time']) ?></p>
              <?php elseif ($todayStatus['status_key'] === 'excused'): ?>
                <p class="text-muted mb-0">Pengajuan izin untuk hari ini telah tercatat.</p>
              <?php elseif ($todayStatus['status_key'] === 'sick'): ?>
                <p class="text-muted mb-0">Status sakit untuk hari ini telah tercatat.</p>
              <?php else: ?>
                <p class="text-muted mb-0">Belum ada catatan kehadiran ataupun pengajuan untuk hari ini.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-3 mb-4">
      <?php foreach ($statMeta as $key => $meta): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="card mobile-card text-center">
            <div class="card-body">
              <h6 class="card-title text-muted mb-2"><i class="<?= $meta['icon'] ?> me-1"></i><?= $meta['label'] ?></h6>
              <h4 class="<?= $meta['class'] ?> mb-0"><?= (int)$statsCounts[$key] ?></h4>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Attendance History -->
    <div class="row">
      <div class="col-12">
        <div class="card mobile-card">
          <div class="card-header">
            <h5 class="card-title mb-0">Riwayat Kehadiran (maks. 30 hari terakhir)</h5>
          </div>
          <div class="card-body">
            <?php if (empty($historyEntries)): ?>
              <div class="text-center py-4">
                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Belum ada riwayat kehadiran</h5>
                <p class="text-muted">Riwayat akan tampil setelah Anda memiliki catatan kehadiran atau pengajuan izin/sakit.</p>
              </div>
            <?php else: ?>
              <?php foreach ($historyEntries as $entry): ?>
                <?php $meta = $statusMeta[$entry['status_key']] ?? $statusMeta['alpa']; ?>
                <div class="attendance-item status-<?= $entry['status_key'] ?> mb-3 p-3 bg-white rounded">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <h6 class="mb-1"><?= htmlspecialchars(formatDateIndo($entry['date'], true)) ?></h6>
                      <?php if (in_array($entry['status_key'], ['on_time','late'], true) && $entry['has_scans']): ?>
                        <p class="mb-1 text-muted">
                          <i class="fas fa-sign-in-alt me-1"></i><?= htmlspecialchars($entry['first_time']) ?>
                          <?php if ($entry['last_time'] !== '-' && $entry['last_time'] !== $entry['first_time']): ?>
                            - <i class="fas fa-sign-out-alt me-1"></i><?= htmlspecialchars($entry['last_time']) ?>
                          <?php endif; ?>
                        </p>
                      <?php elseif ($entry['status_key'] === 'excused'): ?>
                        <p class="mb-1 text-muted">Pengajuan izin disetujui untuk tanggal ini.</p>
                      <?php elseif ($entry['status_key'] === 'sick'): ?>
                        <p class="mb-1 text-muted">Status sakit dicatat untuk tanggal ini.</p>
                      <?php else: ?>
                        <p class="mb-1 text-muted">Tidak ada catatan kehadiran.</p>
                      <?php endif; ?>
                    </div>
                    <div>
                      <span class="badge bg-<?= $entry['badge'] ?> status-badge"><?= htmlspecialchars($entry['status_label']) ?></span>
                    </div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
