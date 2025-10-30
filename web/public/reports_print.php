<?php
require __DIR__ . '/_auth.php';
requireRole(['admin', 'teacher']);

$pdo = pdo();
$from = $_GET['from'] ?? (new DateTime('first day of this month'))->format('Y-m-01');
$to   = $_GET['to']   ?? (new DateTime('last day of this month'))->format('Y-m-t');
$kelas= $_GET['kelas'] ?? '';

$type = $_GET['type'] ?? 'daily';
if (!in_array($type, ['daily', 'class'], true)) {
  $type = 'daily';
}

$startDateObj = new DateTime($from);
$endDateObj = new DateTime($to);
$endDateObj->setTime(23, 59, 59);
$period = new DatePeriod((clone $startDateObj)->setTime(0, 0, 0), new DateInterval('P1D'), (clone $endDateObj)->modify('+1 day'));
$workingDayList = [];
foreach ($period as $day) {
  if (isWeekend($day) || isHoliday($day)) continue;
  $workingDayList[] = $day->format('Y-m-d');
}
$workingDays = count($workingDayList);

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
    if (!isset($studentMap[$userId])) continue;
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
    'name' => $student['name'],
    'kelas' => $student['kelas'],
    'on_time_days' => $onTimeDays,
    'late_days' => $lateDays,
    'excused_days' => $excusedDays,
    'sick_days' => $sickDays,
    'alpa_days' => $alpaDays,
    'present_days' => $onTimeDays + $lateDays,
    'first_scan' => $firstScanDT ? $firstScanDT->format('Y-m-d H:i:s') : null,
    'last_scan' => $lastScanDT ? $lastScanDT->format('Y-m-d H:i:s') : null
  ];
}

$classRows = [];
if ($type === 'class') {
  $classAggregates = [];
  foreach ($studentSummaries as $summary) {
    $kelasKey = $summary['kelas'] ?? '-';
    if (!isset($classAggregates[$kelasKey])) {
      $classAggregates[$kelasKey] = [
        'kelas' => $kelasKey,
        'total_siswa' => 0,
        'on_time_days' => 0,
        'late_days' => 0,
        'excused_days' => 0,
        'sick_days' => 0,
        'alpa_days' => 0
      ];
    }
    $classAggregates[$kelasKey]['total_siswa']++;
    $classAggregates[$kelasKey]['on_time_days'] += $summary['on_time_days'];
    $classAggregates[$kelasKey]['late_days'] += $summary['late_days'];
    $classAggregates[$kelasKey]['excused_days'] += $summary['excused_days'];
    $classAggregates[$kelasKey]['sick_days'] += $summary['sick_days'];
    $classAggregates[$kelasKey]['alpa_days'] += $summary['alpa_days'];
  }
  foreach ($classAggregates as $kelasKey => $data) {
    $totalEffective = $workingDays * $data['total_siswa'];
    $totalHadirDays = $data['on_time_days'] + $data['late_days'];
    $classRows[] = [
      'kelas' => $kelasKey,
      'total_siswa' => $data['total_siswa'],
      'on_time_days' => $data['on_time_days'],
      'late_days' => $data['late_days'],
      'excused_days' => $data['excused_days'],
      'sick_days' => $data['sick_days'],
      'alpa_days' => $data['alpa_days'],
      'total_hadir_days' => $totalHadirDays,
      'total_effective_days' => $totalEffective,
      'attendance_rate' => $totalEffective > 0 ? round(($totalHadirDays / $totalEffective) * 100, 1) : 0.0
    ];
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Print Rekap</title>
  <style>
    body { font-family: Arial, sans-serif; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #333; padding: 6px; font-size: 12px; }
    th { background: #eee; }
    .meta { margin-bottom: 10px; font-size: 12px; }
  </style>
</head>
<body onload="window.print()">
  <div class="meta">
    Periode: <?= htmlspecialchars(formatDateIndo($from)) ?> s/d <?= htmlspecialchars(formatDateIndo($to)) ?>
    <?php if ($kelas !== ''): ?> | Kelas: <?= htmlspecialchars($kelas) ?><?php endif; ?>
  </div>
  <?php if ($type === 'class'): ?>
    <table>
      <thead>
        <tr>
          <th>Kelas</th>
          <th>Total Siswa</th>
          <th>Tepat Waktu (hari)</th>
          <th>Terlambat (hari)</th>
          <th>Izin (hari)</th>
          <th>Sakit (hari)</th>
          <th>Alpa (hari)</th>
          <th>Total Hadir (hari)</th>
          <th>Total Hari Efektif</th>
          <th>Persentase Kehadiran</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($classRows as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['kelas']) ?></td>
          <td><?= (int)$row['total_siswa'] ?></td>
          <td><?= (int)$row['on_time_days'] ?></td>
          <td><?= (int)$row['late_days'] ?></td>
          <td><?= (int)$row['excused_days'] ?></td>
          <td><?= (int)$row['sick_days'] ?></td>
          <td><?= (int)$row['alpa_days'] ?></td>
          <td><?= (int)$row['total_hadir_days'] ?></td>
          <td><?= (int)$row['total_effective_days'] ?></td>
          <td><?= $row['attendance_rate'] ?>%</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Nama</th>
          <th>Kelas</th>
          <th>Tepat Waktu (hari)</th>
          <th>Terlambat (hari)</th>
          <th>Izin (hari)</th>
          <th>Sakit (hari)</th>
          <th>Alpa (hari)</th>
          <th>Total Hadir (hari)</th>
          <th>Scan Pertama</th>
          <th>Scan Terakhir</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($studentSummaries as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['name']) ?></td>
          <td><?= htmlspecialchars($row['kelas']) ?></td>
          <td><?= (int)$row['on_time_days'] ?></td>
          <td><?= (int)$row['late_days'] ?></td>
          <td><?= (int)$row['excused_days'] ?></td>
          <td><?= (int)$row['sick_days'] ?></td>
          <td><?= (int)$row['alpa_days'] ?></td>
          <td><?= (int)$row['present_days'] ?></td>
          <td><?= $row['first_scan'] ? htmlspecialchars(formatDateTimeIndo($row['first_scan'])) : '-' ?></td>
          <td><?= $row['last_scan'] ? htmlspecialchars(formatDateTimeIndo($row['last_scan'])) : '-' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</body>
</html>
