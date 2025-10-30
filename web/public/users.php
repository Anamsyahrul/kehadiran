<?php
require __DIR__ . '/_auth.php';
requireRole(['admin']);

$pdo = pdo();

function deriveUsernameFromName(PDO $pdo, string $name, ?int $excludeId = null): string {
    $base = trim($name);
    if ($base === '') {
        $base = 'Pengguna';
    }

    $candidate = $base;
    $suffix = 2;
    $sql = 'SELECT COUNT(*) FROM users WHERE username = ?';
    if ($excludeId) {
        $sql .= ' AND id <> ?';
    }
    $stmt = $pdo->prepare($sql);

    while (true) {
        $stmt->execute($excludeId ? [$candidate, $excludeId] : [$candidate]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $candidate;
        }
        $candidate = $base . ' #' . $suffix;
        $suffix++;
    }
}

// Create/Update user
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'student';
    $kelas = trim($_POST['kelas'] ?? '');
    $uid_hex = trim($_POST['uid_hex'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $defaultPassword = $role === 'teacher'
        ? 'guru123'
        : ($role === 'student' ? 'siswa123' : 'password123');

    if ($name) {
        if ($id > 0) {
            $existingStmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $existingStmt->execute([$id]);
            $existingUser = $existingStmt->fetch(PDO::FETCH_ASSOC);
            if (!$existingUser) {
                header('Location: /kehadiran/web/public/users.php');
                exit;
            }

            $username = $existingUser['username'] ?? '';
            $syncable = $existingUser['username'] === null
                || trim(strtolower($existingUser['username'])) === trim(strtolower($existingUser['name'] ?? ''));
            if ($syncable) {
                $username = deriveUsernameFromName($pdo, $name, $id);
            }
            if ($username === '') {
                $username = deriveUsernameFromName($pdo, $name, $id);
            }
            // Update
            $sql = 'UPDATE users SET name=?, username=?, role=?, kelas=?, uid_hex=?, is_active=?';
            $params = [$name, $username, $role, $kelas, $uid_hex, $is_active];
            $activating = $existingUser && (int)$existingUser['is_active'] === 0 && $is_active === 1;
            if ($password !== '') {
                $sql .= ', password=?';
                $params[] = password_hash($password, PASSWORD_BCRYPT);
            } elseif ($activating) {
                $sql .= ', password=?';
                $params[] = password_hash($defaultPassword, PASSWORD_BCRYPT);
            }
            $sql .= ' WHERE id=?';
            $params[] = $id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            writeAudit((int)($_SESSION['user_id'] ?? 0), 'user_update', ['id'=>$id, 'username'=>$username]);
        } else {
            // Create
            $stmt = $pdo->prepare('INSERT INTO users(name, username, password, role, kelas, uid_hex, is_active) VALUES(?,?,?,?,?,?,?)');
            $passwordToUse = $password !== '' ? $password : $defaultPassword;
            $username = deriveUsernameFromName($pdo, $name, null);
            $stmt->execute([$name, $username, password_hash($passwordToUse, PASSWORD_BCRYPT), $role, $kelas, $uid_hex, $is_active]);
            writeAudit((int)($_SESSION['user_id'] ?? 0), 'user_create', ['username'=>$username]);
        }
    }
    header('Location: /kehadiran/web/public/users.php');
    exit;
}

// Delete user
if (($_GET['action'] ?? '') === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND role != "admin"');
        $stmt->execute([$id]);
        writeAudit((int)($_SESSION['user_id'] ?? 0), 'user_delete', ['id'=>$id]);
    }
    header('Location: /kehadiran/web/public/users.php');
    exit;
}

// Get users
$users = $pdo->query('SELECT * FROM users ORDER BY role, name')->fetchAll();
$pendingUsers = $pdo->query("SELECT * FROM users WHERE is_active = 0 AND uid_hex IS NOT NULL AND uid_hex <> '' ORDER BY id DESC")->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <title>Manajemen Siswa - Sistem RFID Enterprise</title>
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
              <i class="fas fa-users"></i>
            </span>
            <div class="page-heading__label">
              <h2>Manajemen Siswa</h2>
              <p class="page-heading__description">Kelola data siswa, guru, dan admin</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add/Edit Form -->
    <?php if (!empty($pendingUsers)): ?>
    <div class="card mb-4 border-warning">
      <div class="card-header bg-warning text-dark">
        <h5 class="card-title mb-0"><i class="fas fa-id-badge me-2"></i>Kartu Baru Menunggu Data</h5>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">UID berikut otomatis terekam dari perangkat, silakan klik <strong>Isi Data</strong> untuk melengkapi informasi siswa kemudian aktifkan akunnya.</p>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead>
              <tr>
                <th>UID</th>
                <th>Nama Sementara</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pendingUsers as $pending): ?>
                <tr>
                  <td><code><?= htmlspecialchars($pending['uid_hex']) ?></code></td>
                  <td><?= htmlspecialchars($pending['name']) ?></td>
                  <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?= htmlspecialchars(json_encode($pending)) ?>)">
                      <i class="fas fa-pen me-1"></i>Isi Data
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="alert alert-info border-info shadow-sm mb-4">
      <i class="fas fa-info-circle me-2"></i>
      Username otomatis mengikuti <strong>Nama Lengkap</strong> saat membuat akun baru. Admin bawaan tetap memakai username <code>admin</code>.
      Password default: <code>siswa123</code> (siswa) atau <code>guru123</code> (guru) bila kolom password dikosongkan.
    </div>

    <div class="card mb-4" id="userFormCard">
      <div class="card-header">
        <h5 class="card-title mb-0">Tambah/Edit Pengguna</h5>
      </div>
      <div class="card-body">
        <form method="post" class="row g-3" id="userForm">
          <?= csrf_input() ?>
          <input type="hidden" name="id" id="editId" value="0">
          <div class="col-md-3">
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="name" id="editName" class="form-control" required />
          </div>
          <div class="col-md-2">
            <label class="form-label">Password</label>
            <input type="password" name="password" id="editPassword" class="form-control" placeholder="Kosongkan jika tidak diubah" />
            <small class="form-text text-muted">Kosongkan untuk memakai default (siswa: <code>siswa123</code>, guru: <code>guru123</code>, admin: <code>password123</code>).</small>
          </div>
          <div class="col-md-2">
            <label class="form-label">Role</label>
            <select name="role" id="editRole" class="form-select">
              <option value="student">Siswa</option>
              <option value="teacher">Guru</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Kelas</label>
            <input type="text" name="kelas" id="editKelas" class="form-control" placeholder="X IPA 1" />
          </div>
          <div class="col-md-1">
            <label class="form-label">UID Hex</label>
            <input type="text" name="uid_hex" id="editUidHex" class="form-control" placeholder="A1B2C3D4" />
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive" checked>
              <label class="form-check-label" for="editIsActive">Aktif</label>
            </div>
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-primary me-2">Simpan</button>
            <button type="button" class="btn btn-secondary" onclick="clearForm()">Batal</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Users Table -->
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">Daftar Pengguna</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped table-hover">
            <thead class="table-dark">
              <tr>
                <th>Nama</th>
                <th>Role</th>
                <th>Kelas</th>
                <th>UID</th>
                <th>Status</th>
                <th>Login Terakhir</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $user): ?>
                <tr>
                  <td><?= htmlspecialchars($user['name']) ?></td>
                  <td>
                    <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'teacher' ? 'warning' : 'info') ?>">
                      <?= ucfirst($user['role']) ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($user['kelas'] ?? '-') ?></td>
                  <td><code><?= htmlspecialchars($user['uid_hex'] ?? '-') ?></code></td>
                  <td>
                    <span class="badge bg-<?= $user['is_active'] ? 'success' : 'secondary' ?>">
                      <?= $user['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                    </span>
                  </td>
                  <td><?= $user['last_login'] ? htmlspecialchars(formatDateTimeIndo($user['last_login'])) : 'Belum pernah' ?></td>
                  <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">
                      <i class="fas fa-edit"></i>
                    </button>
                    <?php if ($user['role'] !== 'admin'): ?>
                      <a class="btn btn-sm btn-outline-danger" href="?action=delete&id=<?= $user['id'] ?>" onclick="return confirm('Hapus pengguna ini?')">
                        <i class="fas fa-trash"></i>
                      </a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const userForm = document.getElementById('userForm');
const userFormCard = document.getElementById('userFormCard');
const nameInput = document.getElementById('editName');
const passwordInput = document.getElementById('editPassword');
const roleSelect = document.getElementById('editRole');
const kelasInput = document.getElementById('editKelas');
const uidInput = document.getElementById('editUidHex');
const isActiveInput = document.getElementById('editIsActive');
const idInput = document.getElementById('editId');

const setCreateMode = () => {
    if (!userForm) return;
    userForm.reset();
    idInput.value = '0';
    if (isActiveInput) {
        isActiveInput.checked = true;
    }
    if (passwordInput) {
        passwordInput.value = '';
    }
};

function editUser(user) {
    if (!userForm) {
        return;
    }
    idInput.value = user.id;
    if (nameInput) nameInput.value = user.name || '';
    if (passwordInput) passwordInput.value = '';
    if (roleSelect) roleSelect.value = user.role || 'student';
    if (kelasInput) kelasInput.value = user.kelas || '';
    if (uidInput) uidInput.value = user.uid_hex || '';
    if (isActiveInput) isActiveInput.checked = user.is_active == 1;

    if (userFormCard) {
        userFormCard.scrollIntoView({ behavior: 'smooth' });
    }
}

function clearForm() {
    setCreateMode();
    if (nameInput) {
        nameInput.focus();
    }
}

setCreateMode();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
