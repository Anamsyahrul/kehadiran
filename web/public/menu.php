<?php
require __DIR__ . '/_auth.php';

$role = $_SESSION['role'] ?? 'student';
if ($role === 'admin') {
    header('Location: /kehadiran/web/public/dashboard.php');
    exit;
}

$pdo = pdo();
$userId = (int)($_SESSION['user_id'] ?? 0);
$username = $_SESSION['username'] ?? 'Pengguna';
$schoolName = getSetting('sekolah_NAME', 'SMA Peradaban Bumiayu');
$unreadCount = 0;

if ($userId > 0) {
    $notifStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0');
    $notifStmt->execute([$userId]);
    $unreadCount = (int)$notifStmt->fetchColumn();
}

$isTeacher = $role === 'teacher';

$menuItems = [];

if ($isTeacher) {
    $menuItems = [
        [
            'title' => 'Dashboard',
            'description' => 'Ringkasan statistik kehadiran harian dan tren kelas.',
            'icon' => 'fa-tachometer-alt',
            'href' => '/kehadiran/web/public/dashboard.php',
            'accent' => 'accent-primary'
        ],
        [
            'title' => 'Real-time Monitoring',
            'description' => 'Pantau scan terbaru dan status perangkat secara langsung.',
            'icon' => 'fa-broadcast-tower',
            'href' => '/kehadiran/web/public/real_time.php',
            'accent' => 'accent-cyan'
        ],
        [
            'title' => 'Laporan',
            'description' => 'Analisis kehadiran per siswa dan unduh laporan rekap.',
            'icon' => 'fa-chart-bar',
            'href' => '/kehadiran/web/public/reports.php',
            'accent' => 'accent-purple'
        ],
        [
            'title' => 'Notifikasi',
            'description' => 'Kelola dan kirim pemberitahuan ke siswa.',
            'icon' => 'fa-bell',
            'href' => '/kehadiran/web/public/notifications.php',
            'accent' => 'accent-amber',
            'badge' => $unreadCount > 0 ? $unreadCount : null
        ],
        [
            'title' => 'Pengajuan Izin',
            'description' => 'Ajukan izin atau sakit untuk diri sendiri.',
            'icon' => 'fa-clipboard-check',
            'href' => '/kehadiran/web/public/pengajuan_izin.php',
            'accent' => 'accent-emerald'
        ],
        [
            'title' => 'Persetujuan Izin',
            'description' => 'Tinjau dan setujui permohonan izin siswa.',
            'icon' => 'fa-clipboard-list',
            'href' => '/kehadiran/web/public/kelola_izin.php',
            'accent' => 'accent-indigo'
        ],
        [
            'title' => 'Profil Kehadiran',
            'description' => 'Lihat riwayat scan pribadi dan status harian.',
            'icon' => 'fa-user-circle',
            'href' => '/kehadiran/web/public/mobile_app.php',
            'accent' => 'accent-sky'
        ],
        [
            'title' => 'Ganti Password',
            'description' => 'Perbarui kata sandi akun Anda secara berkala.',
            'icon' => 'fa-key',
            'href' => '/kehadiran/web/public/change_password.php',
            'accent' => 'accent-rose'
        ],
        [
            'title' => 'Dokumentasi Siswa',
            'description' => 'Panduan singkat cara menggunakan aplikasi bagi siswa.',
            'icon' => 'fa-book-open',
            'href' => '/kehadiran/web/public/docs.php',
            'accent' => 'accent-slate'
        ],
    ];
} else {
    $menuItems = [
        [
            'title' => 'Profil Kehadiran',
            'description' => 'Pantau status hari ini dan riwayat scan 30 hari terakhir.',
            'icon' => 'fa-mobile-alt',
            'href' => '/kehadiran/web/public/mobile_app.php',
            'accent' => 'accent-primary'
        ],
        [
            'title' => 'Notifikasi',
            'description' => 'Baca pengumuman terbaru dari sekolah.',
            'icon' => 'fa-bell',
            'href' => '/kehadiran/web/public/notifications.php',
            'accent' => 'accent-amber',
            'badge' => $unreadCount > 0 ? $unreadCount : null
        ],
        [
            'title' => 'Pengajuan Izin',
            'description' => 'Kirim permohonan izin atau sakit dengan lampiran bukti.',
            'icon' => 'fa-clipboard-check',
            'href' => '/kehadiran/web/public/pengajuan_izin.php',
            'accent' => 'accent-emerald'
        ],
        [
            'title' => 'Ganti Password',
            'description' => 'Segera ubah kata sandi jika merasa akun tidak aman.',
            'icon' => 'fa-key',
            'href' => '/kehadiran/web/public/change_password.php',
            'accent' => 'accent-rose'
        ],
        [
            'title' => 'Panduan Pengguna',
            'description' => 'Baca langkah penggunaan aplikasi bagi siswa.',
            'icon' => 'fa-book-open',
            'href' => '/kehadiran/web/public/docs.php',
            'accent' => 'accent-slate'
        ],
    ];
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <title>Menu Utama - Sistem Kehadiran RFID Enterprise</title>
  <style>
    body {
        background: radial-gradient(circle at 10% 20%, rgba(42, 82, 152, 0.08), transparent 60%), 
                   radial-gradient(circle at 80% 0%, rgba(30, 60, 114, 0.12), transparent 55%),
                   #f4f6fb;
    }
    .menu-grid {
        display: grid;
        gap: 1.4rem;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    }
    .menu-card {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 1.1rem;
        padding: 1.35rem 1.4rem;
        border-radius: 18px;
        border: 1px solid rgba(30,60,114,0.08);
        background: rgba(255,255,255,0.95);
        text-decoration: none;
        color: inherit;
        box-shadow: 0 18px 32px rgba(15,32,70,0.08);
        transition: transform 0.18s ease, box-shadow 0.18s ease, border 0.18s ease;
    }
    .menu-card:hover,
    .menu-card:focus {
        text-decoration: none;
        transform: translateY(-4px);
        border-color: rgba(30,60,114,0.18);
        box-shadow: 0 24px 44px rgba(15,32,70,0.14);
    }
    .menu-card__icon {
        width: 58px;
        height: 58px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
    }
    .menu-card__body h5 {
        font-size: 1.1rem;
        font-weight: 700;
        color: #122547;
        margin-bottom: 0.45rem;
    }
    .menu-card__body p {
        margin: 0;
        color: rgba(18, 37, 71, 0.68);
        font-size: 0.95rem;
        line-height: 1.5;
    }
    .menu-card__cta {
        margin-top: auto;
        font-weight: 600;
        color: #2a5298;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    .menu-card__badge {
        position: absolute;
        top: 16px;
        right: 16px;
        background: #dc3545;
        color: #fff;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 0.25rem 0.55rem;
        border-radius: 999px;
        box-shadow: 0 6px 14px rgba(220,53,69,0.35);
    }
    .menu-card__icon.accent-primary { background: rgba(42, 82, 152, 0.14); color: #2a5298; }
    .menu-card__icon.accent-emerald { background: rgba(25, 135, 84, 0.14); color: #198754; }
    .menu-card__icon.accent-amber { background: rgba(255, 193, 7, 0.18); color: #cc8a00; }
    .menu-card__icon.accent-cyan { background: rgba(13, 202, 240, 0.18); color: #0d8db5; }
    .menu-card__icon.accent-purple { background: rgba(111, 66, 193, 0.16); color: #6130b4; }
    .menu-card__icon.accent-indigo { background: rgba(88, 86, 214, 0.16); color: #4c49c9; }
    .menu-card__icon.accent-sky { background: rgba(59, 130, 246, 0.18); color: #2563eb; }
    .menu-card__icon.accent-slate { background: rgba(71, 85, 105, 0.16); color: #3f4a5a; }
    .menu-card__icon.accent-rose { background: rgba(244, 63, 94, 0.18); color: #e11d48; }

    @media (max-width: 768px) {
        .menu-grid {
            grid-template-columns: 1fr;
        }
        .menu-card {
            padding: 1.2rem 1.25rem;
        }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main-content">
  <div class="container-fluid py-4">
    <div class="row">
      <div class="col-12 page-heading" data-hide-menu-button="true">
        <div class="page-heading__content">
          <div class="page-heading__title">
            <span class="page-heading__icon">
              <i class="fas fa-th-large"></i>
            </span>
            <div class="page-heading__label">
              <h2>Menu Utama</h2>
              <p class="page-heading__description"><?= htmlspecialchars($schoolName) ?> · Halo, <?= htmlspecialchars($username) ?></p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="menu-grid">
      <?php foreach ($menuItems as $item): ?>
        <a class="menu-card" href="<?= htmlspecialchars($item['href']) ?>">
          <div class="menu-card__icon <?= htmlspecialchars($item['accent']) ?>">
            <i class="fas <?= htmlspecialchars($item['icon']) ?>"></i>
          </div>
          <?php if (!empty($item['badge'])): ?>
            <span class="menu-card__badge"><?= htmlspecialchars((string)$item['badge']) ?></span>
          <?php endif; ?>
          <div class="menu-card__body">
            <h5><?= htmlspecialchars($item['title']) ?></h5>
            <p><?= htmlspecialchars($item['description']) ?></p>
          </div>
          <div class="menu-card__cta">
            <span>Buka</span>
            <i class="fas fa-arrow-right"></i>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
