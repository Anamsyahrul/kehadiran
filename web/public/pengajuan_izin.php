<?php
require __DIR__ . '/_auth.php';
requireRole(['student', 'teacher']);

$pdo = pdo();
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentUser = null;
if ($currentUserId) {
    $stmt = $pdo->prepare('SELECT id, name, kelas FROM users WHERE id = ? AND role IN ("student","teacher")');
    $stmt->execute([$currentUserId]);
    $currentUser = $stmt->fetch();
}

if (!$currentUser) {
    http_response_code(403);
    echo 'Akses ditolak.';
    exit;
}

$message = $_GET['msg'] ?? null;
$messageType = $_GET['type'] ?? 'success';

$uploadDir = dirname(__DIR__, 1) . '/uploads/leave_requests';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

$allowedTypes = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png'
];
$maxFileSize = 2 * 1024 * 1024; // 2MB

// Handle submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $leaveDate = $_POST['leave_date'] ?? '';
    $leaveType = $_POST['leave_type'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $leaveDate)) {
        header('Location: /kehadiran/web/public/pengajuan_izin.php?msg=Tanggal tidak valid&type=danger');
        exit;
    }
    $validTypes = ['excused', 'sick'];
    if (!in_array($leaveType, $validTypes, true)) {
        header('Location: /kehadiran/web/public/pengajuan_izin.php?msg=Jenis izin tidak valid&type=danger');
        exit;
    }
    if ($reason === '') {
        header('Location: /kehadiran/web/public/pengajuan_izin.php?msg=Alasan wajib diisi&type=danger');
        exit;
    }

    // Prevent duplicate pending/approved for same date
    $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM leave_requests WHERE user_id = ? AND leave_date = ? AND status IN ("pending","approved")');
    $dupStmt->execute([$currentUserId, $leaveDate]);
    if ((int)$dupStmt->fetchColumn() > 0) {
        header('Location: /kehadiran/web/public/pengajuan_izin.php?msg=Sudah ada pengajuan untuk tanggal tersebut&type=warning');
        exit;
    }

    $attachmentPath = null;
    if (!empty($_FILES['attachment']['name'])) {
        if ($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
            header('Location: /kehadiran/web/public/pengajuan_izin.php?msg=Gagal mengunggah lampiran&type=danger');
            exit;
        }
        if ($_FILES['attachment']['size'] > $maxFileSize) {
            header('Location: /kehadiran/web/public/pengajuan_izin.php?msg=Lampiran maksimal 2MB&type=danger');
            exit;
        }
        $mime = mime_content_type($_FILES['attachment']['tmp_name']);
        if (!isset($allowedTypes[$mime])) {
            header('Location: /kehadiran/web/public/pengajuan_izin.php?msg=Format lampiran harus PDF/JPG/PNG&type=danger');
            exit;
        }
        $ext = $allowedTypes[$mime];
        $safeName = sprintf('%d_%s.%s', $currentUserId, bin2hex(random_bytes(8)), $ext);
        $destPath = $uploadDir . '/' . $safeName;
        if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $destPath)) {
            header('Location: /kehadiran/web/public/pengajuan_izin.php?msg=Tidak dapat menyimpan lampiran&type=danger');
            exit;
        }
        $attachmentPath = 'uploads/leave_requests/' . $safeName;
    }

    $insert = $pdo->prepare('INSERT INTO leave_requests (user_id, request_date, leave_date, leave_type, reason, attachment, status, created_at) VALUES (?,?,?,?,?,?, "pending", NOW())');
    $insert->execute([
        $currentUserId,
        date('Y-m-d'),
        $leaveDate,
        $leaveType,
        $reason,
        $attachmentPath
    ]);

    writeAudit($currentUserId, 'leave_request_create', ['id' => $pdo->lastInsertId(), 'leave_date' => $leaveDate, 'type' => $leaveType]);

    header('Location: /kehadiran/web/public/pengajuan_izin.php?msg=Pengajuan izin berhasil dikirim&type=success');
    exit;
}

// Load leave requests for current user
$filterStatus = $_GET['status'] ?? 'all';
$statusOptions = [
    'all' => 'Semua',
    'pending' => 'Pending',
    'approved' => 'Disetujui',
    'rejected' => 'Ditolak'
];
$statusWhere = '';
$statusParam = [];
if ($filterStatus !== 'all' && isset($statusOptions[$filterStatus])) {
    $statusWhere = ' AND status = ?';
    $statusParam[] = $filterStatus;
}
$requestsStmt = $pdo->prepare('SELECT * FROM leave_requests WHERE user_id = ?' . $statusWhere . ' ORDER BY leave_date DESC, id DESC');
$requestsStmt->execute(array_merge([$currentUserId], $statusParam));
$requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);

$defaultLeaveDate = date('Y-m-d');

function leaveStatusBadge(string $status): string {
    switch ($status) {
        case 'pending': return 'warning';
        case 'approved': return 'success';
        case 'rejected': return 'danger';
        default: return 'secondary';
    }
}

function leaveTypeLabel(string $type): string {
    return $type === 'sick' ? 'Sakit' : 'Izin';
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <title>Pengajuan Izin - Sistem RFID Enterprise</title>
  <style>
    body { background-color: #f6f8fc; }
    .card {
      border: none;
      border-radius: 18px;
      box-shadow: 0 15px 35px rgba(15, 26, 46, 0.08);
    }
    .badge { font-weight: 600; letter-spacing: 0.3px; }
    .form-section { border-right: 1px solid rgba(15,26,46,0.08); }
    @media (max-width: 991.98px) {
      .form-section { border-right: none; border-bottom: 1px solid rgba(15,26,46,0.08); margin-bottom: 1.5rem; padding-bottom: 1.5rem; }
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
              <i class="fas fa-clipboard-check"></i>
            </span>
            <div class="page-heading__label">
              <h2>Pengajuan Izin / Sakit</h2>
              <p class="page-heading__description">Ajukan permohonan izin atau sakit dengan bukti yang valid. Permohonan menunggu persetujuan guru/kesiswaan.</p>
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

    <div class="card mb-4">
      <div class="row g-0">
        <div class="col-lg-5 form-section">
          <div class="card-body">
            <h5 class="card-title mb-3">Form Pengajuan</h5>
            <form method="post" enctype="multipart/form-data" class="row g-3">
              <?= csrf_input() ?>
              <div class="col-12">
                <label class="form-label">Tanggal Izin</label>
                <input type="date" name="leave_date" class="form-control" value="<?= htmlspecialchars($defaultLeaveDate) ?>" required />
              </div>
              <div class="col-12">
                <label class="form-label">Jenis</label>
                <select name="leave_type" class="form-select" required>
                  <option value="excused">Izin</option>
                  <option value="sick">Sakit</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Alasan</label>
                <textarea name="reason" class="form-control" rows="3" placeholder="Tuliskan alasan izin atau sakit..." required></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Lampiran (opsional)</label>
                <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png" />
                <small class="text-muted">Format PDF/JPG/PNG, maks 2MB.</small>
              </div>
              <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Kirim Pengajuan</button>
                <a class="btn btn-outline-secondary" href="/kehadiran/web/public/pengajuan_izin.php">Reset</a>
              </div>
            </form>
          </div>
        </div>
        <div class="col-lg-7">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="card-title mb-0">Riwayat Pengajuan</h5>
              <form method="get" class="d-flex align-items-center gap-2">
                <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                  <?php foreach ($statusOptions as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $filterStatus === $key ? 'selected' : '' ?>><?= $label ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if ($filterStatus !== 'all'): ?>
                  <a href="/kehadiran/web/public/pengajuan_izin.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                <?php endif; ?>
              </form>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Tanggal</th>
                    <th>Jenis</th>
                    <th>Alasan</th>
                    <th>Lampiran</th>
                    <th>Status</th>
                    <th>Diproses</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($requests)): ?>
                    <tr>
                      <td colspan="6" class="text-center text-muted py-4">Belum ada pengajuan.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($requests as $req): ?>
                      <tr>
                        <td><?= htmlspecialchars(formatDateIndo($req['leave_date'])) ?></td>
                        <td><span class="badge bg-secondary text-uppercase"><?= leaveTypeLabel($req['leave_type']) ?></span></td>
                        <td><?= nl2br(htmlspecialchars($req['reason'])) ?></td>
                        <td>
                          <?php if ($req['attachment']): ?>
                            <a href="/kehadiran/web/public/view_leave_attachment.php?id=<?= (int)$req['id'] ?>" class="btn btn-sm btn-outline-primary">
                              <i class="fas fa-paperclip"></i> Lihat
                            </a>
                          <?php else: ?>
                            <span class="text-muted">-</span>
                          <?php endif; ?>
                        </td>
                        <td><span class="badge bg-<?= leaveStatusBadge($req['status']) ?>"><?= ucfirst($req['status']) ?></span></td>
                        <td>
                          <?php if ($req['processed_at']): ?>
                            <small><?= htmlspecialchars(formatDateTimeIndo($req['processed_at'], true, true)) ?></small>
                          <?php else: ?>
                            <small class="text-muted">Belum diproses</small>
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
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
