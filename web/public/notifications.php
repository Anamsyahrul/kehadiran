<?php
require __DIR__ . '/_auth.php';

$pdo = pdo();
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$composeErrors = [];
$composeOld = [
    'title' => '',
    'message' => '',
    'type' => 'info',
    'target_scope' => 'all',
    'target_user' => ''
];
$successMessage = null;

// Mark notification as read
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read' && isset($_POST['id'])) {
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?');
        $stmt->execute([(int)$_POST['id'], $_SESSION['user_id']]);
        header('Location: /kehadiran/web/public/notifications.php');
        exit;
    } elseif ($action === 'mark_all_read') {
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$_SESSION['user_id']]);
        header('Location: /kehadiran/web/public/notifications.php');
        exit;
    } elseif ($action === 'create_notification' && $isAdmin) {
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $type = strtolower($_POST['type'] ?? 'info');
        $targetScope = $_POST['target_scope'] ?? 'all';
        $targetUser = (int)($_POST['target_user'] ?? 0);

        $composeOld = [
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'target_scope' => $targetScope,
            'target_user' => $targetUser
        ];

        $allowedTypes = ['info','success','warning','error'];
        if ($title === '') {
            $composeErrors[] = 'Judul notifikasi harus diisi.';
        }
        if ($message === '') {
            $composeErrors[] = 'Isi pesan notifikasi harus diisi.';
        }
        if (!in_array($type, $allowedTypes, true)) {
            $composeErrors[] = 'Tipe notifikasi tidak valid.';
        }
        if ($targetScope === 'user' && $targetUser <= 0) {
            $composeErrors[] = 'Pilih pengguna tujuan.';
        }

        if (empty($composeErrors)) {
            if ($targetScope === 'all') {
                createNotification(null, $title, $message, $type);
            } else {
                notifyUser($targetUser, $title, $message, $type);
            }
            header('Location: /kehadiran/web/public/notifications.php?created=1');
            exit;
        }
    }
}

// Get notifications
$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC');
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

// Count unread
$stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0');
$stmt->execute([$_SESSION['user_id']]);
$unreadCount = (int)$stmt->fetchColumn();

if (isset($_GET['created'])) {
    $successMessage = 'Notifikasi baru berhasil dikirim.';
}

$userOptions = [];
if ($isAdmin) {
    $userStmt = $pdo->query('SELECT id, name, username, role FROM users WHERE is_active = 1 ORDER BY name ASC');
    $userOptions = $userStmt->fetchAll();
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <title>Notifikasi - Sistem RFID Enterprise</title>
  <style>
    body {
      background-color: #f8f9fa;
    }
    .card {
      border: none;
      border-radius: 15px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .notification-item {
      transition: all 0.3s ease;
    }
.notification-item:hover {
  transform: translateX(5px);
}
.notification-compose .card-header {
  background: linear-gradient(135deg, rgba(30,60,114,0.08), rgba(42,82,152,0.2));
}
.notification-compose .form-label {
  font-weight: 600;
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  color: #0f2246;
}
.notification-compose textarea {
  min-height: 140px;
}
.notification-compose .list-group-item {
  cursor: pointer;
}
@media (min-width: 992px) {
  .notification-compose .row.g-3 > [class*="col-"] {
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
  }
  .notification-compose textarea {
    min-height: 180px;
  }
}
@media (max-width: 768px) {
  .notification-compose .card-body {
    padding: 1.25rem;
  }
  .notification-compose .form-label {
    font-size: 0.82rem;
  }
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
              <i class="fas fa-bell"></i>
            </span>
            <div class="page-heading__label">
              <h2>Notifikasi</h2>
              <p class="page-heading__description">Pusat informasi terbaru untuk pengguna sistem</p>
            </div>
          </div>
          <?php if ($unreadCount > 0): ?>
            <div class="page-heading__actions">
              <form method="post" class="d-inline">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button class="btn btn-outline-primary">
                  <i class="fas fa-check-double me-1"></i>Tandai Semua Dibaca
                </button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="row gy-3 mt-1">
      <div class="col-12">
        <?php if ($isAdmin): ?>
          <div class="alert alert-info mb-0">
            <i class="fas fa-info-circle me-1"></i>
            Admin dapat mengirim notifikasi ke semua pengguna atau ke pengguna tertentu melalui formulir berikut.
          </div>
        <?php endif; ?>
        <?php if ($unreadCount > 0): ?>
          <div class="alert alert-info mt-3 mb-0">
            <i class="fas fa-info-circle me-1"></i>
            Anda memiliki <strong><?= $unreadCount ?></strong> notifikasi belum dibaca
          </div>
        <?php endif; ?>
        <?php if ($successMessage): ?>
          <div class="alert alert-success mt-3 mb-0">
            <i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($successMessage) ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($composeErrors)): ?>
          <div class="alert alert-danger mt-3 mb-0">
            <i class="fas fa-exclamation-triangle me-1"></i>
            <?= implode('<br>', array_map('htmlspecialchars', $composeErrors)) ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($isAdmin): ?>
      <div class="row mt-4">
        <div class="col-12">
          <div class="card mb-4 notification-compose">
            <div class="card-header">
              <h5 class="card-title mb-0">Kirim Notifikasi Baru</h5>
            </div>
            <div class="card-body">
              <form method="post" class="row g-3">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="create_notification">
                <div class="col-lg-6 col-md-6 col-12">
                  <label class="form-label">Judul</label>
                  <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($composeOld['title']) ?>" required>
                </div>
                <div class="col-lg-3 col-md-4 col-sm-6">
                  <label class="form-label">Tipe</label>
                  <select name="type" class="form-select" required>
                    <option value="info" <?= $composeOld['type'] === 'info' ? 'selected' : '' ?>>Info</option>
                    <option value="success" <?= $composeOld['type'] === 'success' ? 'selected' : '' ?>>Sukses</option>
                    <option value="warning" <?= $composeOld['type'] === 'warning' ? 'selected' : '' ?>>Peringatan</option>
                    <option value="error" <?= $composeOld['type'] === 'error' ? 'selected' : '' ?>>Error</option>
                  </select>
                </div>
                <div class="col-lg-3 col-md-4 col-sm-6">
                  <label class="form-label">Tujuan</label>
                  <select name="target_scope" id="targetScope" class="form-select" required>
                    <option value="all" <?= $composeOld['target_scope'] === 'all' ? 'selected' : '' ?>>Semua pengguna</option>
                    <option value="user" <?= $composeOld['target_scope'] === 'user' ? 'selected' : '' ?>>Pengguna tertentu</option>
                  </select>
                </div>
                <div class="col-12" id="targetUserWrapper" style="<?= $composeOld['target_scope'] === 'user' ? '' : 'display:none;' ?>">
                  <label class="form-label">Pilih Pengguna</label>
                  <div class="position-relative">
                    <input type="text" id="userSearch" class="form-control mb-2" placeholder="Ketik nama atau username..." autocomplete="off">
                    <div class="list-group position-absolute w-100 shadow-sm d-none" id="userSearchResults" style="z-index: 1050; max-height: 220px; overflow-y: auto;">
                    </div>
                  </div>
                  <select name="target_user" id="targetUserSelect" class="form-select mt-2">
                    <option value="">-- Pilih pengguna --</option>
                    <?php foreach ($userOptions as $user): ?>
                      <option value="<?= (int)$user['id'] ?>" <?= (int)$composeOld['target_user'] === (int)$user['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($user['name'] ?? $user['username']) ?> (<?= htmlspecialchars($user['role']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label">Isi Pesan</label>
                  <textarea name="message" rows="4" class="form-control" required><?= htmlspecialchars($composeOld['message']) ?></textarea>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                  <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane me-1"></i>Kirim
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="row">
      <div class="col-12">
        <?php if (empty($notifications)): ?>
          <div class="card">
            <div class="card-body text-center py-5">
              <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
              <h5 class="text-muted">Tidak ada notifikasi</h5>
              <p class="text-muted">Semua notifikasi akan muncul di sini</p>
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($notifications as $notif): ?>
            <div class="card mb-3 notification-item <?= $notif['is_read'] ? '' : 'border-primary' ?>">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                  <div class="flex-grow-1">
                    <div class="d-flex align-items-center mb-2">
                      <span class="badge bg-<?= $notif['type'] === 'error' ? 'danger' : ($notif['type'] === 'warning' ? 'warning' : ($notif['type'] === 'success' ? 'success' : 'info')) ?> me-2">
                        <?= ucfirst($notif['type']) ?>
                      </span>
                      <?php if (!$notif['is_read']): ?>
                        <span class="badge bg-primary">Baru</span>
                      <?php endif; ?>
                    </div>
                    <h6 class="card-title mb-1"><?= htmlspecialchars($notif['title']) ?></h6>
                    <p class="card-text mb-2"><?= htmlspecialchars($notif['message']) ?></p>
                    <small class="text-muted">
                      <i class="fas fa-clock me-1"></i>
                      <?= htmlspecialchars(formatDateTimeIndo($notif['created_at'])) ?>
                      <?php if ($notif['read_at']): ?>
                        | <i class="fas fa-check me-1"></i>Dibaca: <?= htmlspecialchars(formatDateTimeIndo($notif['read_at'])) ?>
                      <?php endif; ?>
                    </small>
                  </div>
                  <?php if (!$notif['is_read']): ?>
                    <form method="post" class="ms-3">
                      <?= csrf_input() ?>
                      <input type="hidden" name="action" value="mark_read">
                      <input type="hidden" name="id" value="<?= $notif['id'] ?>">
                      <button class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-check me-1"></i>Tandai Dibaca
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const scopeSelect = document.getElementById('targetScope');
    if (!scopeSelect) return;
    const userWrapper = document.getElementById('targetUserWrapper');
    const userSearchInput = document.getElementById('userSearch');
    const userSearchResults = document.getElementById('userSearchResults');
    const userSelect = document.getElementById('targetUserSelect');
    const userOptions = userSelect ? Array.from(userSelect.options).filter(opt => opt.value !== '') : [];

    const syncSearchInput = () => {
      if (!userSearchInput || !userSelect) return;
      const selected = userSelect.options[userSelect.selectedIndex];
      userSearchInput.value = selected && selected.value ? selected.textContent.trim() : '';
    };

    scopeSelect.addEventListener('change', function() {
      if (this.value === 'user') {
        userWrapper.style.display = '';
        if (userSearchInput) {
          userSearchInput.focus();
        }
      } else {
        userWrapper.style.display = 'none';
        if (userSelect) {
          userSelect.value = '';
        }
        if (userSearchInput) {
          userSearchInput.value = '';
        }
        if (userSearchResults) {
          userSearchResults.classList.add('d-none');
        }
        document.body.classList.remove('show-user-search');
      }
    });

    if (userSearchInput && userSearchResults && userSelect) {
      userSearchInput.addEventListener('input', function() {
        const query = this.value.trim().toLowerCase();
        userSearchResults.innerHTML = '';
        if (query === '') {
          userSearchResults.classList.add('d-none');
          return;
        }
        const matches = userOptions.filter(opt => opt.text.toLowerCase().includes(query)).slice(0, 8);
        if (!matches.length) {
          const empty = document.createElement('div');
          empty.className = 'list-group-item list-group-item-action disabled';
          empty.textContent = 'Tidak ada hasil';
          userSearchResults.appendChild(empty);
        } else {
          matches.forEach(opt => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action';
            item.dataset.value = opt.value;
            item.textContent = opt.text;
            item.addEventListener('click', () => {
              userSelect.value = opt.value;
              userSearchInput.value = opt.text;
              userSearchResults.classList.add('d-none');
            });
            userSearchResults.appendChild(item);
          });
        }
        userSearchResults.classList.remove('d-none');
      });

      userSelect.addEventListener('change', syncSearchInput);
      syncSearchInput();

      document.addEventListener('click', (evt) => {
        if (userWrapper && !userWrapper.contains(evt.target)) {
          userSearchResults.classList.add('d-none');
        }
      });
    }
  });
</script>
</body>
</html>
