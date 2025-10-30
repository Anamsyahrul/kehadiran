<?php
require __DIR__ . '/_auth.php';

$pdo = pdo();
$userId = (int)($_SESSION['user_id'] ?? 0);
$username = $_SESSION['username'] ?? 'Pengguna';

$errorMessage = null;
$successMessage = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $errorMessage = 'Semua kolom wajib diisi.';
    } elseif (strlen($newPassword) < 8) {
        $errorMessage = 'Password baru minimal 8 karakter.';
    } elseif ($newPassword !== $confirmPassword) {
        $errorMessage = 'Konfirmasi password baru tidak sesuai.';
    } else {
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !password_verify($currentPassword, $row['password'])) {
            $errorMessage = 'Password lama tidak sesuai.';
        } elseif (password_verify($newPassword, $row['password'])) {
            $errorMessage = 'Password baru tidak boleh sama dengan password lama.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $update->execute([$hash, $userId]);

            $deleteTokens = $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
            $deleteTokens->execute([$userId]);

            session_regenerate_id(true);
            writeAudit($userId, 'change_password', null);

            $successMessage = 'Password berhasil diperbarui.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <title>Ganti Password - Sistem Kehadiran RFID Enterprise</title>
  <style>
    body {
      background-color: #f8f9fa;
    }
    .card {
      border: none;
      border-radius: 16px;
      box-shadow: 0 16px 34px rgba(15,32,70,0.12);
    }
    .form-control {
      border-radius: 12px;
      padding: 0.75rem 1rem;
    }
    .form-control:focus {
      box-shadow: 0 0 0 0.2rem rgba(42,82,152,0.15);
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
              <i class="fas fa-key"></i>
            </span>
            <div class="page-heading__label">
              <h2>Ganti Password</h2>
              <p class="page-heading__description">Perbarui kata sandi akun <?= htmlspecialchars($username) ?> untuk menjaga keamanan.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row justify-content-center">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <?php if ($errorMessage): ?>
              <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="fas fa-triangle-exclamation me-2"></i>
                <div><?= htmlspecialchars($errorMessage) ?></div>
              </div>
            <?php elseif ($successMessage): ?>
              <div class="alert alert-success d-flex align-items-center" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <div><?= htmlspecialchars($successMessage) ?></div>
              </div>
            <?php endif; ?>

            <form method="post" novalidate>
              <?= csrf_input() ?>
              <div class="mb-3">
                <label class="form-label" for="current_password">Password Saat Ini</label>
                <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password" />
              </div>
              <div class="mb-3">
                <label class="form-label" for="new_password">Password Baru</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" autocomplete="new-password" />
                <div class="form-text">Gunakan minimal 8 karakter dan kombinasi huruf, angka, atau simbol.</div>
              </div>
              <div class="mb-4">
                <label class="form-label" for="confirm_password">Konfirmasi Password Baru</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password" />
              </div>
              <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                  <i class="fas fa-save me-2"></i>Simpan Password Baru
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
