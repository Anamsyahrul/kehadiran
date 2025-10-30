<?php
require __DIR__ . '/_auth.php';
requireRole(['admin']);

$pdo = pdo();
$currentUserStmt = $pdo->prepare('SELECT username, name FROM users WHERE id = ?');
$currentUserStmt->execute([$_SESSION['user_id']]);
$currentUser = $currentUserStmt->fetch() ?: ['username' => 'admin', 'name' => 'Administrator'];

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$search = trim($_GET['search'] ?? '');
$searchLike = '%' . $search . '%';
$hasSearch = $search !== '';

$message = $_GET['msg'] ?? null;
$messageType = $_GET['type'] ?? 'success';

// Handle create/update
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $attendanceId = (int)($_POST['attendance_id'] ?? 0);
    $userId = (int)($_POST['user_id'] ?? 0);
    $eventType = $_POST['event_type'] ?? 'checkin';
    $statusFlag = $_POST['status_flag'] ?? 'present';
    $notes = trim($_POST['notes'] ?? '');
    $timestampInput = $_POST['timestamp'] ?? ($date . 'T07:00');
    $timestamp = DateTime::createFromFormat('Y-m-d\TH:i', $timestampInput);
    if (!$timestamp) {
        $timestamp = new DateTime($date . ' 07:00');
    }

    $studentStmt = $pdo->prepare('SELECT id, name, uid_hex FROM users WHERE id = ? AND role = "student"');
    $studentStmt->execute([$userId]);
    $student = $studentStmt->fetch();

    if ($student) {
        $uidHex = $student['uid_hex'] ?: sprintf('MANUAL-%05d', $student['id']);
        $rawPayload = [
            'type' => $eventType,
            'status' => $statusFlag,
            'notes' => $notes,
            'source' => 'manual',
            'entered_by' => $currentUser['username'],
            'entered_at' => (new DateTime())->format(DateTime::ATOM)
        ];

        if ($action === 'update' && $attendanceId > 0) {
            $existingStmt = $pdo->prepare('SELECT * FROM kehadiran WHERE id = ?');
            $existingStmt->execute([$attendanceId]);
            $existing = $existingStmt->fetch();
            if ($existing) {
                $existingPayload = json_decode($existing['raw_json'] ?? '[]', true) ?: [];
                $rawPayload = array_merge($existingPayload, $rawPayload);
                $updateStmt = $pdo->prepare('UPDATE kehadiran SET user_id = ?, device_id = NULL, ts = ?, uid_hex = ?, raw_json = ? WHERE id = ?');
                $updateStmt->execute([
                    $student['id'],
                    $timestamp->format('Y-m-d H:i:s'),
                    $uidHex,
                    json_encode($rawPayload, JSON_UNESCAPED_UNICODE),
                    $attendanceId
                ]);
                writeAudit((int)($_SESSION['user_id'] ?? 0), 'manual_attendance_update', ['attendance_id' => $attendanceId, 'user_id' => $student['id']]);
                header('Location: /kehadiran/web/public/kehadiran_manual.php?date=' . $date . '&msg=Perubahan tersimpan&type=success');
                exit;
            }
        } elseif ($action === 'create') {
            $insertStmt = $pdo->prepare('INSERT INTO kehadiran (user_id, device_id, ts, uid_hex, raw_json) VALUES (NULLIF(?,0), NULL, ?, ?, ?)');
            $insertStmt->execute([
                $student['id'],
                $timestamp->format('Y-m-d H:i:s'),
                $uidHex,
                json_encode($rawPayload, JSON_UNESCAPED_UNICODE)
            ]);
            writeAudit((int)($_SESSION['user_id'] ?? 0), 'manual_attendance_create', ['user_id' => $student['id']]);
            header('Location: /kehadiran/web/public/kehadiran_manual.php?date=' . $date . '&msg=Data kehadiran ditambahkan&type=success');
            exit;
        }
    }
    header('Location: /kehadiran/web/public/kehadiran_manual.php?date=' . $date . '&msg=Gagal memproses data&type=danger');
    exit;
}

// Handle delete
if (($_GET['action'] ?? '') === 'delete') {
    $deleteId = (int)($_GET['id'] ?? 0);
    if ($deleteId > 0) {
        $delStmt = $pdo->prepare('DELETE FROM kehadiran WHERE id = ?');
        $delStmt->execute([$deleteId]);
        writeAudit((int)($_SESSION['user_id'] ?? 0), 'manual_attendance_delete', ['attendance_id' => $deleteId]);
        header('Location: /kehadiran/web/public/kehadiran_manual.php?date=' . $date . '&msg=Data kehadiran dihapus&type=success');
        exit;
    }
}

// Editing state
$editAttendance = null;
if (($editId = (int)($_GET['edit_id'] ?? 0)) > 0) {
    $editStmt = $pdo->prepare('SELECT * FROM kehadiran WHERE id = ?');
    $editStmt->execute([$editId]);
    $editAttendance = $editStmt->fetch();
    if ($editAttendance) {
        $date = substr($editAttendance['ts'], 0, 10);
    }
}

// Load data
$studentsStmt = $pdo->query('SELECT id, name, kelas, uid_hex FROM users WHERE role = "student" AND is_active = 1 ORDER BY name');
$students = $studentsStmt->fetchAll();

$attendanceStmt = $pdo->prepare('SELECT k.*, u.name AS student_name, u.kelas FROM kehadiran k LEFT JOIN users u ON u.id = k.user_id WHERE DATE(k.ts) = ? ORDER BY k.ts ASC');
$attendanceStmt->execute([$date]);
$attendanceRows = $attendanceStmt->fetchAll();

$attendanceByStudent = [];
foreach ($attendanceRows as $row) {
    if (!$row['user_id']) {
        continue;
    }
    $attendanceByStudent[$row['user_id']][] = $row;
}

$summary = [];
foreach ($students as $student) {
    $records = $attendanceByStudent[$student['id']] ?? [];
    $firstScan = '-';
    $lastScan = '-';
    $status = 'Alpa';
    $statusBadge = 'danger';
    $totalScan = count($records);

    if ($records) {
        $hasPresent = false;
        $hasLate = false;
        $hasExcused = false;
        $hasSick = false;

        $firstScan = formatTimeIndo($records[0]['ts']);
        $lastScan = formatTimeIndo(end($records)['ts']);

        foreach ($records as $rec) {
            $payload = json_decode($rec['raw_json'] ?? '[]', true);
            $statusFlag = strtolower($payload['status'] ?? '');
            $isLate = (bool)($payload['is_late'] ?? false);

            switch ($statusFlag) {
                case 'excused':
                case 'permission':
                    $hasExcused = true;
                    break;
                case 'sick':
                    $hasSick = true;
                    break;
                case 'late':
                    $hasPresent = true;
                    $hasLate = true;
                    break;
                case 'checkout':
                case 'present':
                    $hasPresent = true;
                    break;
                case 'absent':
                    // tetap dianggap alpa
                    break;
                default:
                    if ($isLate) {
                        $hasPresent = true;
                        $hasLate = true;
                    }
                    break;
            }
        }

        if ($hasExcused) {
            $status = 'Izin';
            $statusBadge = 'info';
        } elseif ($hasSick) {
            $status = 'Sakit';
            $statusBadge = 'primary';
        } elseif ($hasPresent) {
            if ($hasLate) {
                $status = 'Terlambat';
                $statusBadge = 'warning';
            } else {
                $status = 'Tepat Waktu';
                $statusBadge = 'success';
            }
        }
    } else {
        $firstScan = '-';
        $lastScan = '-';
    }

    $summary[] = [
        'id' => $student['id'],
        'name' => $student['name'],
        'kelas' => $student['kelas'],
        'first_scan' => $firstScan,
        'last_scan' => $lastScan,
        'status' => $status,
        'badge' => $statusBadge,
        'total_scan' => $totalScan
    ];
}


$formDefaults = [
    'attendance_id' => 0,
    'user_id' => '',
    'timestamp' => $date . 'T07:00',
    'event_type' => 'checkin',
    'status_flag' => 'present',
    'notes' => ''
];

if ($editAttendance) {
    $payload = json_decode($editAttendance['raw_json'] ?? '[]', true) ?: [];
    $formDefaults = [
        'attendance_id' => $editAttendance['id'],
        'user_id' => $editAttendance['user_id'],
        'timestamp' => date('Y-m-d\TH:i', strtotime($editAttendance['ts'])),
        'event_type' => $payload['type'] ?? 'checkin',
        'status_flag' => $payload['status'] ?? 'present',
        'notes' => $payload['notes'] ?? ''
    ];
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <title>Manual Kehadiran - Sistem RFID Enterprise</title>
  <style>
    body { background-color: #f6f8fc; }
    .card {
      border: none;
      border-radius: 18px;
      box-shadow: 0 15px 35px rgba(15, 26, 46, 0.08);
    }
    .table thead th {
      background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
      color: #fff;
      border: none;
    }
    .badge {
      font-weight: 600;
      letter-spacing: 0.3px;
    }
    .form-section {
      border-right: 1px solid rgba(15, 26, 46, 0.08);
    }
    @media (max-width: 991.98px) {
      .form-section { border-right: none; border-bottom: 1px solid rgba(15, 26, 46, 0.08); margin-bottom: 1.5rem; padding-bottom: 1.5rem; }
    }
    .list-group-item {
      cursor: pointer;
    }
    .list-group-item.active, .list-group-item:hover {
      background-color: rgba(42, 82, 152, 0.12);
      color: #1e3c72;
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
              <i class="fas fa-user-check"></i>
            </span>
            <div class="page-heading__label">
              <h2>Manual Kehadiran Siswa</h2>
              <p class="page-heading__description">Pantau status kehadiran seluruh siswa dan lakukan penyesuaian secara manual apabila diperlukan.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <form class="card mb-4" method="get" id="filterForm">
      <div class="card-body d-flex flex-column flex-md-row align-items-md-end gap-3">
        <div>
          <label class="form-label mb-1">Tanggal Kehadiran</label>
          <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date) ?>" />
        </div>
        <div class="flex-grow-1">
          <label class="form-label mb-1">Cari Siswa / Kelas / UID</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" name="search" class="form-control" placeholder="misal: Aisyah, XI IPA 1, MANUAL" value="<?= htmlspecialchars($search) ?>" />
          </div>
        </div>
        <div class="ms-md-auto text-muted small">
          <i class="fas fa-info-circle me-1"></i> Data ditarik langsung dari tabel kehadiran untuk tanggal terpilih.
        </div>
      </div>
    </form>

    <?php if ($message): ?>
      <div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <div class="card mb-4">
      <div class="row g-0">
        <div class="col-lg-5 form-section">
          <div class="card-body">
            <h5 class="card-title mb-3"><?= $formDefaults['attendance_id'] ? 'Ubah Kehadiran Manual' : 'Tambah Kehadiran Manual' ?></h5>
            <form method="post" class="row g-3">
              <?= csrf_input() ?>
              <input type="hidden" name="attendance_id" value="<?= (int)$formDefaults['attendance_id'] ?>" />
              <input type="hidden" name="action" value="<?= $formDefaults['attendance_id'] ? 'update' : 'create' ?>" />
              <div class="col-12">
                <label class="form-label">Siswa</label>
                <div class="position-relative">
                  <input type="text" class="form-control" id="studentSearch" placeholder="Ketik nama atau kelas..." autocomplete="off" value="" />
                  <input type="hidden" name="user_id" id="studentHidden" value="<?= htmlspecialchars($formDefaults['user_id']) ?>" required />
                  <div class="list-group position-absolute w-100 shadow-sm d-none" id="studentList" style="max-height: 220px; overflow-y: auto; z-index: 1050;"></div>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Waktu</label>
                <input type="datetime-local" name="timestamp" class="form-control" value="<?= htmlspecialchars($formDefaults['timestamp']) ?>" required />
              </div>
              <div class="col-md-6">
                <label class="form-label">Jenis Event</label>
                <select class="form-select" name="event_type">
                  <?php
                  $eventOptions = [
                    'checkin' => 'Check-in',
                    'checkout' => 'Check-out',
                    'permission' => 'Izin',
                    'sick' => 'Sakit',
                    'absent' => 'Alpa'
                  ];
                  foreach ($eventOptions as $key => $label):
                  ?>
                    <option value="<?= $key ?>" <?= $formDefaults['event_type'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Status</label>
                <select class="form-select" name="status_flag">
                  <?php
                  $statusOptions = [
                    'present' => 'Hadir',
                    'late' => 'Terlambat',
                    'excused' => 'Izin',
                    'sick' => 'Sakit',
                    'absent' => 'Tidak Hadir'
                  ];
                  foreach ($statusOptions as $key => $label):
                  ?>
                    <option value="<?= $key ?>" <?= $formDefaults['status_flag'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Catatan (opsional)</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Contoh: Penyesuaian manual karena lupa tap kartu."><?= htmlspecialchars($formDefaults['notes']) ?></textarea>
              </div>
              <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-save me-1"></i> Simpan
                </button>
                <?php if ($formDefaults['attendance_id']): ?>
                  <a class="btn btn-outline-secondary" href="/kehadiran/web/public/kehadiran_manual.php?date=<?= htmlspecialchars($date) ?>">
                    Batal Ubah
                  </a>
                <?php endif; ?>
              </div>
            </form>
          </div>
        </div>
        <div class="col-lg-7">
          <div class="card-body">
            <h5 class="card-title mb-3">Ringkasan Kehadiran Siswa (<?= htmlspecialchars(formatDateIndo($date, true)) ?>)</h5>
            <div class="table-responsive">
              <table class="table table-hover align-middle" id="summaryTable">
                <thead>
                  <tr>
                    <th>Nama</th>
                    <th>Kelas</th>
                    <th>Status</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Total Scan</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($summary as $item): ?>
                    <tr>
                      <td><?= htmlspecialchars($item['name']) ?></td>
                      <td><?= htmlspecialchars($item['kelas'] ?? '-') ?></td>
                      <td><span class="badge bg-<?= $item['badge'] ?>"><?= $item['status'] ?></span></td>
                      <td><?= $item['first_scan'] ?></td>
                      <td><?= $item['last_scan'] ?></td>
                      <td><?= $item['total_scan'] ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="text-center text-muted py-4 d-none" id="summaryEmpty">
              <i class="fas fa-search me-1"></i> Tidak ada siswa yang cocok dengan pencarian.
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0">Log Kehadiran (<?= htmlspecialchars(formatDateIndo($date, true)) ?>)</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle" id="attendanceTable">
            <thead>
              <tr>
                <th>Waktu</th>
                <th>Siswa</th>
                <th>Jenis</th>
                <th>Status</th>
                <th>Sumber</th>
                <th>Catatan</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($attendanceRows as $row): ?>
                <?php $payload = json_decode($row['raw_json'] ?? '[]', true) ?: []; ?>
                <tr>
                  <td><?= htmlspecialchars(formatTimeIndo($row['ts'])) ?></td>
                  <td><?= htmlspecialchars($row['student_name'] ?? 'Tidak diketahui') ?></td>
                  <td><span class="badge bg-info text-dark text-uppercase"><?= htmlspecialchars($payload['type'] ?? 'unknown') ?></span></td>
                  <td><?= htmlspecialchars($payload['status'] ?? '-') ?></td>
                  <td>
                    <span class="badge bg-<?= ($payload['source'] ?? '') === 'manual' ? 'warning text-dark' : 'secondary' ?>">
                      <?= ($payload['source'] ?? '') === 'manual' ? 'Manual' : 'Perangkat' ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($payload['notes'] ?? '-') ?></td>
                  <td>
                    <div class="btn-group btn-group-sm" role="group">
                      <a class="btn btn-outline-primary" href="/kehadiran/web/public/kehadiran_manual.php?date=<?= htmlspecialchars($date) ?>&edit_id=<?= $row['id'] ?>">
                        <i class="fas fa-edit"></i>
                      </a>
                      <a class="btn btn-outline-danger" href="/kehadiran/web/public/kehadiran_manual.php?action=delete&date=<?= htmlspecialchars($date) ?>&id=<?= $row['id'] ?>" onclick="return confirm('Hapus entri kehadiran ini?')">
                        <i class="fas fa-trash"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="text-center text-muted py-4 d-none" id="attendanceEmpty">
          <i class="fas fa-search me-1"></i> Tidak ada log kehadiran yang cocok dengan pencarian.
        </div>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const filterForm = document.getElementById('filterForm');
  if (!filterForm) return;

  const dateInput = filterForm.querySelector('input[name="date"]');
  if (dateInput) {
    dateInput.addEventListener('change', () => {
      if (typeof filterForm.requestSubmit === 'function') {
        filterForm.requestSubmit();
      } else {
        filterForm.submit();
      }
    });
  }

  const searchInput = filterForm.querySelector('input[name="search"]');
  const summaryTable = document.getElementById('summaryTable');
  const summaryEmpty = document.getElementById('summaryEmpty');
  const attendanceTable = document.getElementById('attendanceTable');
  const attendanceEmpty = document.getElementById('attendanceEmpty');

  const applyTableFilter = () => {
    if (!searchInput) return;
    const query = searchInput.value.trim().toLowerCase();

    const filterRows = (table, emptyState) => {
      if (!table) return;
      const rows = table.querySelectorAll('tbody tr');
      let visibleCount = 0;
      rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        const show = text.includes(query);
        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
      });
      if (emptyState) {
        emptyState.classList.toggle('d-none', visibleCount > 0);
      }
    };

    filterRows(summaryTable, summaryEmpty);
    filterRows(attendanceTable, attendanceEmpty);
  };

  if (searchInput) {
    applyTableFilter();
    searchInput.addEventListener('input', applyTableFilter);
  }

  const studentSearch = document.getElementById('studentSearch');
  const studentHidden = document.getElementById('studentHidden');
  const studentList = document.getElementById('studentList');
  const studentOptions = <?php echo json_encode(array_map(function($student) {
    return [
      'id' => $student['id'],
      'label' => $student['name'] . ($student['kelas'] ? ' - ' . $student['kelas'] : '')
    ];
  }, $students), JSON_UNESCAPED_UNICODE); ?>;

  if (studentSearch && studentHidden && studentList) {
    const renderList = (items) => {
      studentList.innerHTML = '';
      if (!items.length) {
        const empty = document.createElement('div');
        empty.className = 'list-group-item disabled text-muted';
        empty.textContent = 'Tidak ditemukan';
        studentList.appendChild(empty);
        return;
      }
      items.forEach(item => {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = 'list-group-item list-group-item-action';
        el.textContent = item.label;
        el.dataset.id = item.id;
        studentList.appendChild(el);
      });
    };

    const updateSelection = () => {
      const selected = studentOptions.find(opt => String(opt.id) === String(studentHidden.value));
      studentSearch.value = selected ? selected.label : '';
    };

    updateSelection();

    studentSearch.addEventListener('focus', () => {
      studentList.classList.remove('d-none');
      renderList(studentOptions);
    });

    studentSearch.addEventListener('input', () => {
      studentHidden.value = '';
      const term = studentSearch.value.trim().toLowerCase();
      const filtered = studentOptions.filter(opt => opt.label.toLowerCase().includes(term));
      studentList.classList.remove('d-none');
      renderList(filtered);
    });

    studentList.addEventListener('click', (event) => {
      if (event.target.matches('.list-group-item')) {
        const { id } = event.target.dataset;
        const selected = studentOptions.find(opt => String(opt.id) === id);
        if (selected) {
          studentHidden.value = selected.id;
          studentSearch.value = selected.label;
        }
        studentList.classList.add('d-none');
      }
    });

    document.addEventListener('click', (event) => {
      if (!filterForm.contains(event.target)) {
        studentList.classList.add('d-none');
        updateSelection();
      }
    });
  }
});
</script>
</body>
</html>
