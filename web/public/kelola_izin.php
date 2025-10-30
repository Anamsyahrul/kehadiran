<?php
require __DIR__ . '/_auth.php';
requireRole(['admin', 'teacher']);

$pdo = pdo();
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$message = $_GET['msg'] ?? null;
$messageType = $_GET['type'] ?? 'success';

$statuses = ['pending', 'approved', 'rejected'];
$filterStatus = $_GET['status'] ?? 'pending';
if (!in_array($filterStatus, array_merge(['all'], $statuses), true)) {
    $filterStatus = 'pending';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['request_id'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if ($id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
        header('Location: /kehadiran/web/public/kelola_izin.php?msg=Permintaan tidak valid&type=danger');
        exit;
    }

    $stmt = $pdo->prepare('SELECT lr.*, u.name as student_name, u.kelas, u.id as user_id, u.uid_hex FROM leave_requests lr JOIN users u ON u.id = lr.user_id WHERE lr.id = ? LIMIT 1');
    $stmt->execute([$id]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$leave) {
        header('Location: /kehadiran/web/public/kelola_izin.php?msg=Data pengajuan tidak ditemukan&type=danger');
        exit;
    }
    if ($leave['status'] !== 'pending') {
        header('Location: /kehadiran/web/public/kelola_izin.php?msg=Pengajuan sudah diproses sebelumnya&type=warning');
        exit;
    }

    $pdo->beginTransaction();
    try {
        if ($action === 'approve') {
            $pdo->prepare('UPDATE leave_requests SET status="approved", processed_by=?, processed_at=NOW(), notes=? WHERE id=?')->execute([$currentUserId, $note, $id]);

            // Upsert attendance record
            $userId = (int)$leave['user_id'];
            $leaveDate = $leave['leave_date'];
            $statusFlag = $leave['leave_type'] === 'sick' ? 'sick' : 'excused';

            $attendanceStmt = $pdo->prepare('SELECT * FROM kehadiran WHERE user_id = ? AND DATE(ts) = ? LIMIT 1');
            $attendanceStmt->execute([$userId, $leaveDate]);
            $attendance = $attendanceStmt->fetch(PDO::FETCH_ASSOC);

            $payload = [
                'type' => 'manual_leave',
                'status' => $statusFlag,
                'leave_request_id' => $id,
                'processed_by' => $currentUserId,
                'notes' => $note,
                'source' => 'leave_request',
                'entered_at' => (new DateTime())->format(DateTime::ATOM)
            ];
            $uidHex = $leave['uid_hex'] ?: sprintf('LEAVE-%05d', $userId);
            if ($attendance) {
                $existingPayload = json_decode($attendance['raw_json'] ?? '[]', true) ?: [];
                $payload = array_merge($existingPayload, $payload);
                $pdo->prepare('UPDATE kehadiran SET raw_json=?, device_id=NULL WHERE id=?')->execute([
                    json_encode($payload, JSON_UNESCAPED_UNICODE),
                    $attendance['id']
                ]);
            } else {
                $ts = $leaveDate . ' 07:00:00';
                $pdo->prepare('INSERT INTO kehadiran (user_id, device_id, ts, uid_hex, raw_json) VALUES (?,?,?, ?, ?)')
                    ->execute([
                        $userId,
                        null,
                        $ts,
                        $uidHex,
                        json_encode($payload, JSON_UNESCAPED_UNICODE)
                    ]);
            }

            notifyUser($userId, 'Permohonan Izin Disetujui', sprintf('Permohonan %s tanggal %s telah disetujui.', $leave['leave_type'] === 'sick' ? 'Sakit' : 'Izin', formatDateIndo($leaveDate)), 'success');
            writeAudit($currentUserId, 'leave_request_approve', ['id' => $id, 'user_id' => $userId, 'leave_date' => $leaveDate]);
        } else {
            $pdo->prepare('UPDATE leave_requests SET status="rejected", processed_by=?, processed_at=NOW(), notes=? WHERE id=?')->execute([$currentUserId, $note, $id]);
            notifyUser((int)$leave['user_id'], 'Permohonan Izin Ditolak', sprintf('Permohonan %s tanggal %s ditolak.%s', $leave['leave_type'] === 'sick' ? 'Sakit' : 'Izin', formatDateIndo($leave['leave_date']), $note ? ' Catatan: ' . $note : ''), 'error');
            writeAudit($currentUserId, 'leave_request_reject', ['id' => $id, 'user_id' => (int)$leave['user_id']]);
        }

        $pdo->commit();
        header('Location: /kehadiran/web/public/kelola_izin.php?msg=Pengajuan diperbarui&type=success');
        exit;
    } catch (Throwable $e) {
        $pdo->rollBack();
        header('Location: /kehadiran/web/public/kelola_izin.php?msg=Gagal memproses pengajuan&type=danger');
        exit;
    }
}

// Load data
$query = 'SELECT lr.*, u.name AS student_name, u.kelas, processor.name AS processed_name
          FROM leave_requests lr
          JOIN users u ON u.id = lr.user_id
          LEFT JOIN users processor ON processor.id = lr.processed_by
          WHERE 1=1';
$params = [];
if ($filterStatus !== 'all') {
    $query .= ' AND lr.status = ?';
    $params[] = $filterStatus;
}
$query .= ' ORDER BY lr.status="pending" DESC, lr.leave_date DESC, lr.id DESC';
$dataStmt = $pdo->prepare($query);
$dataStmt->execute($params);
$requests = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

function statusBadge(string $status): string {
    switch ($status) {
        case 'pending': return 'warning';
        case 'approved': return 'success';
        case 'rejected': return 'danger';
        default: return 'secondary';
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <title>Persetujuan Izin/Sakit - Sistem RFID Enterprise</title>
  <style>
    body { background-color: #f6f8fc; }
    .card { border: none; border-radius: 18px; box-shadow: 0 15px 35px rgba(15, 26, 46, 0.08); }
    .badge { font-weight: 600; letter-spacing: 0.3px; }
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
              <i class="fas fa-clipboard-list"></i>
            </span>
            <div class="page-heading__label">
              <h2>Persetujuan Izin / Sakit</h2>
              <p class="page-heading__description">Tinjau dan proses permohonan izin atau sakit yang diajukan siswa.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-body">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3 gap-2">
          <div>
            <h5 class="card-title mb-0">Daftar Pengajuan</h5>
            <small class="text-muted">Pilih status untuk mempermudah peninjauan.</small>
          </div>
          <form method="get" class="d-flex align-items-center gap-2">
            <select class="form-select" name="status" onchange="this.form.submit()">
              <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Semua Status</option>
              <?php foreach ($statuses as $status): ?>
                <option value="<?= $status ?>" <?= $filterStatus === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($filterStatus !== 'all'): ?>
              <a href="/kehadiran/web/public/kelola_izin.php" class="btn btn-outline-secondary">Reset</a>
            <?php endif; ?>
          </form>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Nama</th>
                <th>Kelas</th>
                <th>Jenis</th>
                <th>Alasan</th>
                <th>Lampiran</th>
                <th>Status</th>
                <th>Diproses Oleh</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($requests)): ?>
                <tr>
                  <td colspan="9" class="text-center text-muted py-4">Tidak ada pengajuan untuk ditampilkan.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($requests as $req): ?>
                  <tr>
                    <td>
                      <div><strong><?= htmlspecialchars(formatDateIndo($req['leave_date'], true)) ?></strong></div>
                      <small class="text-muted">Diajukan: <?= htmlspecialchars(formatDateTimeIndo($req['request_date'] . ' 00:00:00')) ?></small>
                    </td>
                    <td><?= htmlspecialchars($req['student_name']) ?></td>
                    <td><?= htmlspecialchars($req['kelas'] ?? '-') ?></td>
                    <td><span class="badge bg-secondary"><?= $req['leave_type'] === 'sick' ? 'Sakit' : 'Izin' ?></span></td>
                    <td style="min-width: 200px;"><?= nl2br(htmlspecialchars($req['reason'])) ?></td>
                    <td>
                      <?php if ($req['attachment']): ?>
                        <a href="/kehadiran/web/public/view_leave_attachment.php?id=<?= (int)$req['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-paperclip"></i> Lihat</a>
                      <?php else: ?>
                        <span class="text-muted">-</span>
                      <?php endif; ?>
                    </td>
                    <td><span class="badge bg-<?= statusBadge($req['status']) ?>"><?= ucfirst($req['status']) ?></span></td>
                    <td>
                      <?php if ($req['processed_at']): ?>
                        <small><?= htmlspecialchars(formatDateTimeIndo($req['processed_at'], true, true)) ?><br />oleh <?= htmlspecialchars($req['processed_name'] ?? '-') ?></small>
                      <?php else: ?>
                        <small class="text-muted">-</small>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($req['status'] === 'pending'): ?>
                        <div class="d-flex flex-column gap-1">
                          <form method="post">
                            <?= csrf_input() ?>
                            <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>" />
                            <input type="hidden" name="action" value="approve" />
                            <input type="hidden" name="note" value="" />
                            <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i>Setujui</button>
                          </form>
                          <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal" data-id="<?= (int)$req['id'] ?>">
                            <i class="fas fa-times me-1"></i>Tolak
                          </button>
                        </div>
                      <?php else: ?>
                        <small class="text-muted">-</small>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <?= csrf_input() ?>
      <input type="hidden" name="request_id" id="rejectRequestId" value="0" />
      <input type="hidden" name="action" value="reject" />
      <div class="modal-header">
        <h5 class="modal-title" id="rejectModalLabel">Tolak Pengajuan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Catatan Penolakan (opsional)</label>
          <textarea name="note" class="form-control" rows="3" placeholder="Berikan alasan penolakan jika diperlukan."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-danger">Tolak Pengajuan</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const rejectModal = document.getElementById('rejectModal');
rejectModal.addEventListener('show.bs.modal', event => {
  const button = event.relatedTarget;
  const requestId = button.getAttribute('data-id');
  rejectModal.querySelector('#rejectRequestId').value = requestId;
});
</script>
</body>
</html>
