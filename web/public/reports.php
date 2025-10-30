<?php
require __DIR__ . '/_auth.php';
requireRole(['admin', 'teacher']);

$pdo = pdo();
$from = $_GET['from'] ?? (new DateTime('first day of this month'))->format('Y-m-01');
$to   = $_GET['to']   ?? (new DateTime('last day of this month'))->format('Y-m-t');
$kelas= $_GET['kelas'] ?? '';
$reportType = $_GET['type'] ?? 'daily';

$schoolName = getSetting('sekolah_NAME', 'SMA Peradaban Bumiayu');

$startDateObj = new DateTime($from);
$endDateObj = new DateTime($to);
$endDateObj->setTime(23, 59, 59);
$period = new DatePeriod((clone $startDateObj)->setTime(0, 0, 0), new DateInterval('P1D'), (clone $endDateObj)->modify('+1 day'));
$workingDays = 0;
foreach ($period as $day) {
    if (isWeekend($day)) continue;
    if (isHoliday($day)) continue;
    $workingDays++;
}

// Query berdasarkan tipe laporan (gunakan klasifikasi Hadir dan Tidak Hadir)
$studentSql = 'SELECT id, name, kelas FROM users WHERE role="student" AND is_active=1';
$studentParams = [];
if ($kelas !== '') {
    $studentSql .= ' AND kelas = ?';
    $studentParams[] = $kelas;
}
$studentSql .= ' ORDER BY kelas, name';
$studentStmt = $pdo->prepare($studentSql);
$studentStmt->execute($studentParams);
$students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);
$studentMap = [];
foreach ($students as $student) {
    $studentMap[$student['id']] = $student;
}

$recordsByStudentDay = [];
if ($studentMap) {
    $attendanceParams = [$from . ' 00:00:00', $to . ' 23:59:59'];
    $attendanceSql = 'SELECT a.user_id,
                             a.ts,
                             DATE(a.ts) AS day,
                             COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.raw_json, "$.status")), "") AS status_raw,
                             JSON_EXTRACT(a.raw_json, "$.is_late") AS is_late
                      FROM kehadiran a
                      JOIN users u ON u.id = a.user_id
                      WHERE a.ts BETWEEN ? AND ?';
    if ($kelas !== '') {
        $attendanceSql .= ' AND u.kelas = ?';
        $attendanceParams[] = $kelas;
    }
    $attendanceStmt = $pdo->prepare($attendanceSql);
    $attendanceStmt->execute($attendanceParams);
    foreach ($attendanceStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $userId = (int)$row['user_id'];
        if (!isset($studentMap[$userId])) {
            continue;
        }
        $day = $row['day'];
        $statusRaw = strtolower(trim($row['status_raw'] ?? ''));
        $isLate = filter_var($row['is_late'], FILTER_VALIDATE_BOOLEAN);
        $recordsByStudentDay[$userId][$day][] = [
            'ts' => $row['ts'],
            'status' => $statusRaw,
            'is_late' => $isLate
        ];
    }
}

// Daftar hari efektif
$workingDayList = [];
foreach ($period as $dayObj) {
    if (isWeekend($dayObj) || isHoliday($dayObj)) continue;
    $workingDayList[] = $dayObj->format('Y-m-d');
}
$workingDays = count($workingDayList);

// Ringkasan per siswa
$studentSummaries = [];
foreach ($students as $student) {
    $userId = (int)$student['id'];
    $dailyRecords = $recordsByStudentDay[$userId] ?? [];

    $onTimeDays = 0;
    $lateDays = 0;
    $excusedDays = 0;
    $sickDays = 0;
    $alpaDays = 0;
    $firstScanDT = null;
    $lastScanDT = null;

    foreach ($workingDayList as $dayStr) {
        $entries = $dailyRecords[$dayStr] ?? [];
        if (empty($entries)) {
            $alpaDays++;
            continue;
        }

        $hasPresent = false;
        $hasLate = false;
        $hasExcused = false;
        $hasSick = false;

        foreach ($entries as $entry) {
            $status = $entry['status'];
            $isLateEntry = (bool)$entry['is_late'];
            if ($status === '') {
                $status = $isLateEntry ? 'late' : 'present';
            }
            switch ($status) {
                case 'excused':
                case 'permission':
                    $hasExcused = true;
                    break;
                case 'sick':
                    $hasSick = true;
                    break;
                case 'absent':
                    // dihitung sebagai alpa jika tidak ada status lain
                    break;
                case 'late':
                    $hasPresent = true;
                    $hasLate = true;
                    break;
                case 'checkout':
                case 'present':
                    $hasPresent = true;
                    break;
                default:
                    if ($isLateEntry) {
                        $hasPresent = true;
                        $hasLate = true;
                    }
                    break;
            }

            if ($hasPresent) {
                $tsObj = new DateTime($entry['ts']);
                if (!$firstScanDT || $tsObj < $firstScanDT) {
                    $firstScanDT = clone $tsObj;
                }
                if (!$lastScanDT || $tsObj > $lastScanDT) {
                    $lastScanDT = clone $tsObj;
                }
            }
        }

        if ($hasExcused) {
            $excusedDays++;
        } elseif ($hasSick) {
            $sickDays++;
        } elseif ($hasPresent) {
            if ($hasLate) {
                $lateDays++;
            } else {
                $onTimeDays++;
            }
        } else {
            $alpaDays++;
        }
    }

    if ($workingDays > 0) {
        $computedAlpa = $workingDays - ($onTimeDays + $lateDays + $excusedDays + $sickDays);
        if ($computedAlpa > 0) {
            $alpaDays += $computedAlpa;
        }
    } else {
        $alpaDays = 0;
    }

    $studentSummaries[] = [
        'id' => $userId,
        'name' => $student['name'],
        'kelas' => $student['kelas'],
        'on_time_days' => $onTimeDays,
        'late_days' => $lateDays,
        'excused_days' => $excusedDays,
        'sick_days' => $sickDays,
        'alpa_days' => $alpaDays,
        'present_days' => $onTimeDays + $lateDays,
        'first_scan' => $firstScanDT ? $firstScanDT->format('Y-m-d H:i:s') : null,
        'last_scan' => $lastScanDT ? $lastScanDT->format('Y-m-d H:i:s') : null,
        'working_days' => $workingDays
    ];
}

// Ringkasan per kelas
$classSummaries = [];
foreach ($studentSummaries as $summary) {
    $kelasKey = $summary['kelas'] ?? '-';
    if (!isset($classSummaries[$kelasKey])) {
        $classSummaries[$kelasKey] = [
            'kelas' => $kelasKey,
            'total_siswa' => 0,
            'on_time_days' => 0,
            'late_days' => 0,
            'excused_days' => 0,
            'sick_days' => 0,
            'alpa_days' => 0
        ];
    }
    $classSummaries[$kelasKey]['total_siswa']++;
    $classSummaries[$kelasKey]['on_time_days'] += $summary['on_time_days'];
    $classSummaries[$kelasKey]['late_days'] += $summary['late_days'];
    $classSummaries[$kelasKey]['excused_days'] += $summary['excused_days'];
    $classSummaries[$kelasKey]['sick_days'] += $summary['sick_days'];
    $classSummaries[$kelasKey]['alpa_days'] += $summary['alpa_days'];
}

// Data akhir sesuai tipe laporan
if ($reportType === 'class') {
    $rows = [];
    foreach ($classSummaries as $kelasKey => $data) {
        $totalEffectiveDays = $workingDays * $data['total_siswa'];
        $totalHadirDays = $data['on_time_days'] + $data['late_days'];
        $rows[] = [
            'kelas' => $kelasKey,
            'total_siswa' => $data['total_siswa'],
            'on_time_days' => $data['on_time_days'],
            'late_days' => $data['late_days'],
            'excused_days' => $data['excused_days'],
            'sick_days' => $data['sick_days'],
            'alpa_days' => $data['alpa_days'],
            'total_hadir_days' => $totalHadirDays,
            'total_effective_days' => $totalEffectiveDays,
            'attendance_rate' => $totalEffectiveDays > 0 ? round(($totalHadirDays / $totalEffectiveDays) * 100, 1) : 0.0
        ];
    }
    $headers = ['Kelas', 'Total Siswa', 'Tepat Waktu (hari)', 'Terlambat (hari)', 'Izin (hari)', 'Sakit (hari)', 'Alpa (hari)', 'Total Hadir (hari)', 'Total Hari Efektif', 'Persentase Kehadiran'];
} else {
    $rows = $studentSummaries;
    $headers = ['Nama', 'Kelas', 'Tepat Waktu (hari)', 'Terlambat (hari)', 'Izin (hari)', 'Sakit (hari)', 'Alpa (hari)', 'Total Hadir (hari)', 'Scan Pertama', 'Scan Terakhir'];
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan_kehadiran_' . date('Y-m-d') . '.csv"');
    
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Laporan Kehadiran - ' . $schoolName]);
    fputcsv($out, ['Periode: ' . formatDateIndo($from) . ' s/d ' . formatDateIndo($to)]);
    fputcsv($out, []);
    fputcsv($out, $headers);
    
    foreach ($rows as $r) {
        if ($reportType === 'class') {
            fputcsv($out, [
                $r['kelas'],
                $r['total_siswa'],
                $r['on_time_days'],
                $r['late_days'],
                $r['excused_days'],
                $r['sick_days'],
                $r['alpa_days'],
                $r['total_hadir_days'],
                $r['total_effective_days'],
                $r['attendance_rate'] . '%'
            ]);
        } else {
            fputcsv($out, [
                $r['name'],
                $r['kelas'],
                $r['on_time_days'],
                $r['late_days'],
                $r['excused_days'],
                $r['sick_days'],
                $r['alpa_days'],
                $r['present_days'],
                $r['first_scan'] ? formatDateTimeIndo($r['first_scan']) : '',
                $r['last_scan'] ? formatDateTimeIndo($r['last_scan']) : ''
            ]);
        }
    }
    fclose($out);
    exit;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <title>Laporan Kehadiran - Sistem RFID Enterprise</title>
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
              <i class="fas fa-chart-bar"></i>
            </span>
            <div class="page-heading__label">
              <h2>Laporan Kehadiran</h2>
              <p class="page-heading__description"><?= htmlspecialchars($schoolName) ?></p>
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
          <div class="col-md-2">
            <label class="form-label">Kelas</label>
            <input type="text" name="kelas" value="<?= htmlspecialchars($kelas) ?>" placeholder="Semua Kelas" class="form-control" />
          </div>
          <div class="col-md-2">
            <label class="form-label">Tipe Laporan</label>
            <select name="type" class="form-select">
              <option value="daily" <?= $reportType === 'daily' ? 'selected' : '' ?>>Harian per Siswa</option>
              <option value="class" <?= $reportType === 'class' ? 'selected' : '' ?>>Per Kelas</option>
            </select>
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <button class="btn btn-primary me-2"><i class="fas fa-search me-1"></i>Filter</button>
            <a class="btn btn-success" href="?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&kelas=<?= urlencode($kelas) ?>&type=<?= urlencode($reportType) ?>&export=csv">
              <i class="fas fa-download me-1"></i>Export CSV
            </a>
            <a class="btn btn-info ms-2" href="reports_print.php?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&kelas=<?= urlencode($kelas) ?>&type=<?= urlencode($reportType) ?>" target="_blank">
              <i class="fas fa-print me-1"></i>Print
            </a>
          </div>
        </form>
      </div>
    </div>

    <!-- Results Table -->
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <?= $reportType === 'class' ? 'Laporan Per Kelas' : 'Laporan Harian per Siswa' ?>
          <small class="text-muted">(<?= count($rows) ?> data)</small>
        </h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped table-hover">
            <thead class="table-dark">
              <tr>
                <?php foreach ($headers as $header): ?>
                  <th><?= htmlspecialchars($header) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach($rows as $r): ?>
                <tr>
                  <?php if ($reportType === 'class'): ?>
                    <td><strong><?= htmlspecialchars($r['kelas']) ?></strong></td>
                    <td><?= (int)$r['total_siswa'] ?></td>
                    <td><span class="badge bg-success"><?= (int)$r['on_time_days'] ?> hari</span></td>
                    <td><span class="badge bg-warning"><?= (int)$r['late_days'] ?> hari</span></td>
                    <td><span class="badge bg-info text-dark"><?= (int)$r['excused_days'] ?> hari</span></td>
                    <td><span class="badge bg-primary"><?= (int)$r['sick_days'] ?> hari</span></td>
                    <td><span class="badge bg-danger"><?= (int)$r['alpa_days'] ?> hari</span></td>
                    <td><?= (int)$r['total_hadir_days'] ?> hari</td>
                    <td><?= (int)$r['total_effective_days'] ?> hari</td>
                    <td>
                      <?php 
                      $persentase = $r['attendance_rate'];
                      $color = $persentase >= 90 ? 'success' : ($persentase >= 70 ? 'warning' : 'danger');
                      ?>
                      <span class="badge bg-<?= $color ?>"><?= $persentase ?>%</span>
                    </td>
                  <?php else: ?>
                    <td><?= htmlspecialchars($r['name']) ?></td>
                    <td><?= htmlspecialchars($r['kelas']) ?></td>
                    <td><span class="badge bg-success"><?= (int)$r['on_time_days'] ?> hari</span></td>
                    <td><span class="badge bg-warning text-dark"><?= (int)$r['late_days'] ?> hari</span></td>
                    <td><span class="badge bg-info text-dark"><?= (int)$r['excused_days'] ?> hari</span></td>
                    <td><span class="badge bg-primary"><?= (int)$r['sick_days'] ?> hari</span></td>
                    <td><span class="badge bg-danger"><?= (int)$r['alpa_days'] ?> hari</span></td>
                    <td><?= (int)$r['present_days'] ?> hari</td>
                    <td><?= $r['first_scan'] ? htmlspecialchars(formatDateTimeIndo($r['first_scan'])) : '-' ?></td>
                    <td><?= $r['last_scan'] ? htmlspecialchars(formatDateTimeIndo($r['last_scan'])) : '-' ?></td>
                  <?php endif; ?>
                </tr>
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
