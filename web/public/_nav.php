<?php
// Global navigation dengan fitur minimize yang lebih bagus
$currentPage = basename($_SERVER['PHP_SELF']);
$schoolName = getSetting('sekolah_NAME', 'SMA Peradaban Bumiayu');
?>
<style>
.navbar-collapse.collapsing {
    transition: height 0.35s ease;
}
.navbar-brand {
    font-size: 1.4rem;
    font-weight: 700;
    background: linear-gradient(45deg, #007bff, #0056b3);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex: 1 1 auto;
    min-width: 0;
}
.navbar-brand span {
    display: inline-flex;
    align-items: center;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.nav-link {
    font-weight: 500;
    transition: all 0.3s ease;
    border-radius: 8px;
    margin: 0 2px;
}
.nav-link:hover {
    background-color: rgba(255,255,255,0.1);
    transform: translateY(-1px);
}
.nav-link.active {
    background-color: rgba(255,255,255,0.2);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.navbar-toggler {
    margin-left: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    padding: 0;
    border: 0;
    border-radius: 12px;
    background: transparent;
    color: rgba(255,255,255,0.85);
    flex-shrink: 0;
    transition: color 0.3s ease, background 0.3s ease, transform 0.3s ease;
}
.navbar-toggler:hover {
    background: rgba(255,255,255,0.12);
    color: #ffffff;
    transform: translateY(-1px);
}
.navbar-toggler:focus,
.navbar-toggler:focus-visible {
    outline: none;
    background: rgba(255,255,255,0.18);
    color: #ffffff;
}
.navbar-toggler .navbar-toggler-icon {
    position: relative;
    display: block;
    width: 20px;
    height: 2px;
    background: currentColor;
    border-radius: 1px;
    background-image: none;
    transition: background 0.3s ease;
}
.navbar-toggler .navbar-toggler-icon::before,
.navbar-toggler .navbar-toggler-icon::after {
    content: '';
    position: absolute;
    left: 0;
    width: 20px;
    height: 2px;
    background: currentColor;
    border-radius: 1px;
    transition: transform 0.3s ease, top 0.3s ease;
}
.navbar-toggler .navbar-toggler-icon::before {
    top: -6px;
}
.navbar-toggler .navbar-toggler-icon::after {
    top: 6px;
}
.navbar-toggler[aria-expanded="true"] .navbar-toggler-icon {
    background: transparent;
}
.navbar-toggler[aria-expanded="true"] .navbar-toggler-icon::before {
    top: 0;
    transform: rotate(45deg);
}
.navbar-toggler[aria-expanded="true"] .navbar-toggler-icon::after {
    top: 0;
    transform: rotate(-45deg);
}
.dropdown-menu {
    border: none;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border-radius: 10px;
}
.dropdown-item {
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
}
.dropdown-item:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
}
.badge {
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}
.navbar {
    backdrop-filter: blur(10px);
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%) !important;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.navbar-brand:hover {
    transform: scale(1.05);
    transition: transform 0.3s ease;
}
</style>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="/kehadiran/web/public/dashboard.php">
      <i class="fas fa-id-card me-2"></i>
      <span class="d-none d-md-inline">Sistem Kehadiran RFID Enterprise</span>
      <span class="d-md-none">RFID Enterprise</span>
    </a>
    
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="/kehadiran/web/public/dashboard.php">
            <i class="fas fa-tachometer-alt me-1"></i>
            <span class="d-none d-lg-inline">Dashboard</span>
          </a>
        </li>
        
        <li class="nav-item">
          <a class="nav-link <?= $currentPage === 'real_time.php' ? 'active' : '' ?>" href="/kehadiran/web/public/real_time.php">
            <i class="fas fa-broadcast-tower me-1"></i>
            <span class="d-none d-lg-inline">Real-time</span>
          </a>
        </li>
        
        <li class="nav-item">
          <a class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>" href="/kehadiran/web/public/reports.php">
            <i class="fas fa-chart-bar me-1"></i>
            <span class="d-none d-lg-inline">Laporan</span>
          </a>
        </li>
        
        <li class="nav-item">
          <a class="nav-link <?= $currentPage === 'notifications.php' ? 'active' : '' ?>" href="/kehadiran/web/public/notifications.php">
            <i class="fas fa-bell me-1"></i>
            <span class="d-none d-lg-inline">Notifikasi</span>
            <?php
            // Count unread notifications
            if (isset($_SESSION['user_id'])) {
                $pdo = pdo();
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0');
                $stmt->execute([$_SESSION['user_id']]);
                $unreadCount = (int)$stmt->fetchColumn();
                if ($unreadCount > 0) {
                    echo '<span class="badge bg-danger ms-1 position-relative" style="font-size: 0.6rem;">' . $unreadCount . '</span>';
                }
            }
            ?>
          </a>
        </li>
        
        <li class="nav-item">
          <a class="nav-link <?= $currentPage === 'mobile_app.php' ? 'active' : '' ?>" href="/kehadiran/web/public/mobile_app.php">
            <i class="fas fa-mobile-alt me-1"></i>
            <span class="d-none d-lg-inline">Mobile</span>
          </a>
        </li>
        
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-users me-1"></i>
            <span class="d-none d-lg-inline">Manajemen</span>
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="/kehadiran/web/public/users.php"><i class="fas fa-user-friends me-2"></i>Manajemen Siswa</a></li>
            <li><a class="dropdown-item" href="/kehadiran/web/public/devices.php"><i class="fas fa-microchip me-2"></i>Perangkat RFID</a></li>
            <li><a class="dropdown-item" href="/kehadiran/web/public/holiday_calendar.php"><i class="fas fa-calendar-alt me-2"></i>Kalender Libur</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="/kehadiran/web/public/settings.php"><i class="fas fa-cog me-2"></i>Pengaturan Sekolah</a></li>
            <li><a class="dropdown-item" href="/kehadiran/web/public/audit_logs.php"><i class="fas fa-clipboard-list me-2"></i>Audit Log</a></li>
          </ul>
        </li>
        
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-database me-1"></i>
            <span class="d-none d-lg-inline">Data</span>
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="/kehadiran/web/public/backup.php"><i class="fas fa-download me-2"></i>Backup Data</a></li>
            <li><a class="dropdown-item" href="/kehadiran/web/public/restore.php"><i class="fas fa-upload me-2"></i>Restore Data</a></li>
          </ul>
        </li>
        <?php endif; ?>
        
        <li class="nav-item">
          <a class="nav-link <?= $currentPage === 'docs.php' ? 'active' : '' ?>" href="/kehadiran/web/public/docs.php">
            <i class="fas fa-book me-1"></i>
            <span class="d-none d-lg-inline">Dokumentasi</span>
          </a>
        </li>
      </ul>
      
      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="d-flex align-items-center">
              <div class="rounded-circle bg-light text-dark d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                <i class="fas fa-user" style="font-size: 0.8rem;"></i>
              </div>
              <div class="d-none d-md-block">
                <div class="fw-bold"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></div>
                <small class="text-light opacity-75"><?= ucfirst($_SESSION['role'] ?? 'guest') ?></small>
              </div>
            </div>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header">
              <i class="fas fa-school me-1"></i><?= htmlspecialchars($schoolName) ?>
            </h6></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="/kehadiran/web/public/logout.php">
              <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Add top margin to body content -->
<style>
body {
  padding-top: 80px;
}
@media (max-width: 991.98px) {
  body {
    padding-top: 70px;
  }
}
</style>