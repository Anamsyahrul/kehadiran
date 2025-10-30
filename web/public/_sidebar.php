<?php
// Sidebar menu yang bisa disembunyikan dengan toggle
$currentPage = basename($_SERVER['PHP_SELF']);
$schoolName = getSetting('sekolah_NAME', 'SMA Peradaban Bumiayu');
$role = $_SESSION['role'] ?? 'student';
$hasSidebar = $role === 'admin';
$pdoNav = pdo();
$sidebarUnread = 0;
if (isset($_SESSION['user_id'])) {
    $stmtNav = $pdoNav->prepare('SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0');
    $stmtNav->execute([$_SESSION['user_id']]);
    $sidebarUnread = (int)$stmtNav->fetchColumn();
}
?>
<style>
body.has-overlay {
    overflow: hidden;
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: 280px;
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    color: white;
    transition: transform 0.3s ease;
    z-index: 1000;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.sidebar.collapsed {
    transform: translateX(-100%);
}

.sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    background: rgba(0,0,0,0.1);
    position: relative;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
    color: white;
}

.sidebar-brand .brand-icon {
    width: 54px;
    height: 54px;
    border-radius: 14px;
    background: rgba(255,255,255,0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: #ffffff;
    box-shadow: inset 0 0 12px rgba(255,255,255,0.12);
}

.sidebar-brand .brand-text {
    display: flex;
    flex-direction: column;
    line-height: 1.15;
}

.sidebar-brand .brand-text .title {
    font-size: 1.05rem;
    font-weight: 700;
    letter-spacing: 0.4px;
}

.sidebar-brand .brand-text .subtitle {
    font-size: 0.75rem;
    font-weight: 500;
    opacity: 0.75;
}

.sidebar-brand:hover {
    color: white;
    text-decoration: none;
}

.page-heading {
    display: grid;
    grid-template-columns: 1fr;
    align-items: center;
    gap: 1rem;
    background: linear-gradient(135deg, rgba(30,60,114,0.08) 0%, rgba(42,82,152,0.16) 100%);
    border: 1px solid rgba(30,60,114,0.18);
    border-radius: 18px;
    padding: 1.35rem 1.75rem;
    margin-bottom: 2.25rem;
    box-shadow: 0 18px 36px rgba(30,60,114,0.14);
    position: relative;
    overflow: hidden;
    z-index: 1;
}
.page-heading::after {
    content: '';
    position: absolute;
    inset: -1px;
    background: radial-gradient(circle at 85% -20%, rgba(255,255,255,0.55), rgba(255,255,255,0) 55%), radial-gradient(circle at 10% 120%, rgba(255,255,255,0.25), rgba(255,255,255,0) 60%);
    pointer-events: none;
}
.page-heading h2 {
    margin: 0;
    font-size: 1.65rem;
    font-weight: 700;
    color: #0f2246;
}
.page-heading p {
    margin: 0;
    color: rgba(19,37,75,0.7);
}
.page-heading .text-muted {
    color: rgba(19,37,75,0.55) !important;
}
.page-heading--with-toggle {
    grid-template-columns: auto 1fr;
    grid-template-areas: "toggle content";
    align-items: center;
    column-gap: 1.5rem;
}
.page-heading--with-toggle .page-toggle-btn {
    grid-area: toggle;
}
.page-heading--with-toggle .page-heading__content {
    grid-area: content;
}
.page-heading__content {
    display: grid;
    gap: 0.5rem;
    align-content: start;
    min-width: 0;
}
.page-heading__content > *:last-child {
    margin-bottom: 0;
}
.page-heading__title {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.page-heading__icon {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(30,60,114,0.2), rgba(42,82,152,0.32));
    color: #ffffff;
    box-shadow: inset 0 0 12px rgba(255,255,255,0.25), 0 12px 22px rgba(30,60,114,0.18);
    font-size: 1.6rem;
}
.page-heading__label {
    display: grid;
    gap: 0.4rem;
}
.page-heading__description {
    color: rgba(15,34,70,0.65);
    font-size: 0.98rem;
}
.page-heading__description .page-heading__meta {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.page-heading__description .page-heading__meta + .page-heading__meta {
    margin-left: 1.25rem;
}
.page-heading__actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
}
.page-toggle-btn {
    border: 1px solid rgba(30,60,114,0.24);
    background: linear-gradient(135deg, rgba(30,60,114,0.12), rgba(42,82,152,0.22));
    color: #1c3570;
    border-radius: 14px;
    width: 52px;
    height: 52px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.25s ease, color 0.25s ease, border 0.25s ease, box-shadow 0.25s ease;
    box-shadow: 0 14px 28px rgba(30,60,114,0.18);
    align-self: center;
}
.page-toggle-btn i {
    font-size: 1.25rem;
}
.page-toggle-btn:hover {
    background: linear-gradient(135deg, rgba(30,60,114,0.2), rgba(42,82,152,0.3));
    border-color: rgba(30,60,114,0.36);
    color: #13254b;
    box-shadow: 0 18px 34px rgba(30,60,114,0.22);
}
.page-toggle-btn:focus {
    outline: none;
    box-shadow: 0 0 0 0.18rem rgba(30,60,114,0.32);
}
.page-toggle-btn.collapsed {
    background: linear-gradient(135deg, rgba(30,60,114,0.06), rgba(42,82,152,0.12));
    box-shadow: 0 10px 22px rgba(30,60,114,0.16);
}

.sidebar-overlay {
    position: fixed;
    inset: 0;
    background: rgba(12,26,54,0.85);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
    z-index: 950;
}

.sidebar-overlay.active {
    opacity: 1;
    pointer-events: auto;
}

.sidebar-nav {
    padding: 1rem 0;
    flex: 1 1 auto;
    overflow-y: auto;
    padding-bottom: 5rem;
}

.nav-item {
    margin: 0.25rem 0;
}

.nav-link {
    color: rgba(255,255,255,0.8);
    padding: 0.75rem 1.5rem;
    display: flex;
    align-items: center;
    text-decoration: none;
    transition: all 0.3s ease;
    border-radius: 0;
    margin: 0;
}

.nav-link:hover {
    background-color: rgba(255,255,255,0.1);
    color: white;
    transform: translateX(5px);
}

.nav-link.active {
    background-color: rgba(255,255,255,0.2);
    color: white;
    border-right: 3px solid #fff;
}

.nav-link i {
    width: 20px;
    margin-right: 0.75rem;
    text-align: center;
}

.dropdown-menu {
    background-color: rgba(0,0,0,0.3);
    border: none;
    border-radius: 0;
    box-shadow: none;
    margin-left: 1rem;
}

.dropdown-item {
    color: rgba(255,255,255,0.8);
    padding: 0.5rem 1rem;
    transition: all 0.3s ease;
}

.dropdown-item:hover {
    background-color: rgba(255,255,255,0.1);
    color: white;
    transform: translateX(5px);
}

.dropdown-item i {
    width: 16px;
    margin-right: 0.5rem;
}

.user-profile {
    padding: 1rem 1.5rem;
    background: rgba(0,0,0,0.2);
    border-top: 1px solid rgba(255,255,255,0.1);
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
}

.main-content {
    margin-left: 280px;
    transition: margin-left 0.3s ease, padding 0.3s ease;
    min-height: 100vh;
    padding-left: 2rem;
    padding-right: 2rem;
}

.main-content.expanded {
    margin-left: 0;
    padding-left: 4.5rem;
    padding-right: 2rem;
}

.main-toggle i {
    font-size: 1rem;
}

.badge {
    font-size: 0.6rem;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

@media (max-width: 768px) {
    body:not(.sidebar-ready) #sidebar {
        transform: translateX(-100%);
    }
}

@media (max-width: 768px) {
    .sidebar {
        width: min(75vw, 300px);
        box-shadow: 6px 0 18px rgba(0,0,0,0.25);
        background: linear-gradient(135deg, #223f78 0%, #14336b 100%);
        transform: translateX(-100%);
    }
    
    .sidebar:not(.collapsed) {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0;
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }
    
    .main-content.expanded {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }
    
    .page-heading--with-toggle {
        grid-template-columns: auto 1fr;
        grid-template-areas: "toggle content";
        column-gap: 1rem;
        row-gap: 0.6rem;
        align-items: start;
        text-align: left;
    }
    
    .page-heading--with-toggle .page-toggle-btn {
        width: 44px;
        height: 44px;
        margin: 0;
        position: relative;
        justify-self: flex-start;
        z-index: 1201;
    }
    
    .page-heading__title {
        justify-content: flex-start;
        text-align: left;
        gap: 0.6rem;
    }
    
    .page-heading__icon {
        display: none;
    }
    
    .page-heading__actions {
        justify-content: flex-start;
        width: 100%;
    }
    
    .page-heading__content {
        justify-items: flex-start;
        text-align: left;
        gap: 0.6rem;
    }
    
    .page-heading__description {
        font-size: 0.9rem;
    }
    
    .page-heading__label h2 {
        font-size: 1.35rem;
    }
    
    .page-heading__description .page-heading__meta {
        display: block;
        margin-left: 0 !important;
        gap: 0.3rem;
    }
    
    .page-heading__description .page-heading__meta + .page-heading__meta {
        margin-top: 0.2rem;
    }
}

body.no-sidebar {
    overflow-x: hidden;
}

body.no-sidebar .main-content {
    margin-left: 0;
    padding-left: min(4vw, 1.5rem);
    padding-right: min(4vw, 1.5rem);
}

body.no-sidebar .main-content.expanded {
    padding-left: min(4vw, 1.5rem);
    padding-right: min(4vw, 1.5rem);
}

body.no-sidebar .page-heading {
    margin-bottom: 1.9rem;
}

body.no-sidebar .page-heading--with-toggle {
    grid-template-columns: 1fr;
}

body.no-sidebar .page-heading--with-toggle .page-toggle-btn {
    display: none !important;
}

.page-menu-btn {
    border-radius: 14px;
    border: none;
    background: linear-gradient(135deg, rgba(30,60,114,0.85), rgba(42,82,152,0.95));
    color: #ffffff;
    box-shadow: 0 16px 30px rgba(30,60,114,0.25);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 1.45rem;
    font-weight: 600;
    font-size: 0.95rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.page-menu-btn:hover,
.page-menu-btn:focus {
    color: #ffffff;
    text-decoration: none;
    transform: translateY(-1px);
    box-shadow: 0 20px 36px rgba(30,60,114,0.32);
    background: linear-gradient(135deg, rgba(30,60,114,0.92), rgba(42,82,152,0.99));
}

.page-menu-btn i {
    font-size: 1rem;
}

.page-logout-btn {
    border-radius: 14px;
    border: 1px solid rgba(220,53,69,0.18);
    background: linear-gradient(135deg, rgba(220,53,69,0.9), rgba(201,35,53,0.98));
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.6rem 1.35rem;
    font-weight: 600;
    font-size: 0.93rem;
    box-shadow: 0 16px 30px rgba(220,53,69,0.28);
    transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.page-logout-btn:hover,
.page-logout-btn:focus {
    color: #ffffff;
    text-decoration: none;
    transform: translateY(-1px);
    box-shadow: 0 20px 36px rgba(220,53,69,0.34);
    background: linear-gradient(135deg, rgba(220,53,69,0.95), rgba(201,35,53,1));
}

.page-logout-btn i {
    font-size: 1rem;
}

@media (max-width: 768px) {
    .page-menu-btn {
        width: 100%;
        justify-content: center;
    }
    .page-logout-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>


<?php if ($hasSidebar): ?>
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="/kehadiran/web/public/dashboard.php" class="sidebar-brand">
            <div class="brand-icon">
                <i class="fas fa-id-card"></i>
            </div>
            <div class="brand-text">
                <span class="title">RFID Enterprise</span>
                <span class="subtitle">Sistem Kehadiran</span>
            </div>
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <?php if ($role === 'student'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'mobile_app.php' ? 'active' : '' ?>" href="/kehadiran/web/public/mobile_app.php">
                        <i class="fas fa-mobile-alt"></i>
                        <span>Mobile App</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'notifications.php' ? 'active' : '' ?>" href="/kehadiran/web/public/notifications.php">
                        <i class="fas fa-bell"></i>
                        <span>Notifikasi</span>
                        <?php if ($sidebarUnread > 0): ?>
                            <span class="badge bg-danger ms-auto"><?= $sidebarUnread ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'pengajuan_izin.php' ? 'active' : '' ?>" href="/kehadiran/web/public/pengajuan_izin.php">
                        <i class="fas fa-clipboard-check"></i>
                        <span>Pengajuan Izin</span>
                    </a>
                </li>
            <?php elseif ($role === 'teacher'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="/kehadiran/web/public/dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'real_time.php' ? 'active' : '' ?>" href="/kehadiran/web/public/real_time.php">
                        <i class="fas fa-broadcast-tower"></i>
                        <span>Real-time</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>" href="/kehadiran/web/public/reports.php">
                        <i class="fas fa-chart-bar"></i>
                        <span>Laporan</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'notifications.php' ? 'active' : '' ?>" href="/kehadiran/web/public/notifications.php">
                        <i class="fas fa-bell"></i>
                        <span>Notifikasi</span>
                        <?php if ($sidebarUnread > 0): ?>
                            <span class="badge bg-danger ms-auto"><?= $sidebarUnread ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'kelola_izin.php' ? 'active' : '' ?>" href="/kehadiran/web/public/kelola_izin.php">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Persetujuan Izin</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'mobile_app.php' ? 'active' : '' ?>" href="/kehadiran/web/public/mobile_app.php">
                        <i class="fas fa-user-circle"></i>
                        <span>Profil Saya</span>
                    </a>
                </li>
            <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="/kehadiran/web/public/dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'real_time.php' ? 'active' : '' ?>" href="/kehadiran/web/public/real_time.php">
                        <i class="fas fa-broadcast-tower"></i>
                        <span>Real-time</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>" href="/kehadiran/web/public/reports.php">
                        <i class="fas fa-chart-bar"></i>
                        <span>Laporan</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'notifications.php' ? 'active' : '' ?>" href="/kehadiran/web/public/notifications.php">
                        <i class="fas fa-bell"></i>
                        <span>Notifikasi</span>
                        <?php if ($sidebarUnread > 0): ?>
                            <span class="badge bg-danger ms-auto"><?= $sidebarUnread ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'kelola_izin.php' ? 'active' : '' ?>" href="/kehadiran/web/public/kelola_izin.php">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Persetujuan Izin</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>" href="/kehadiran/web/public/users.php">
                        <i class="fas fa-users"></i>
                        <span>Manajemen Siswa</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'devices.php' ? 'active' : '' ?>" href="/kehadiran/web/public/devices.php">
                        <i class="fas fa-microchip"></i>
                        <span>Perangkat RFID</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'kehadiran_manual.php' ? 'active' : '' ?>" href="/kehadiran/web/public/kehadiran_manual.php">
                        <i class="fas fa-user-check"></i>
                        <span>Kehadiran Manual</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'holiday_calendar.php' ? 'active' : '' ?>" href="/kehadiran/web/public/holiday_calendar.php">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Kalender Libur</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>" href="/kehadiran/web/public/settings.php">
                        <i class="fas fa-cog"></i>
                        <span>Pengaturan Sekolah</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'audit_logs.php' ? 'active' : '' ?>" href="/kehadiran/web/public/audit_logs.php">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Audit Log</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'backup.php' ? 'active' : '' ?>" href="/kehadiran/web/public/backup.php">
                        <i class="fas fa-download"></i>
                        <span>Backup Data</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'restore.php' ? 'active' : '' ?>" href="/kehadiran/web/public/restore.php">
                        <i class="fas fa-upload"></i>
                        <span>Restore Data</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'mobile_app.php' ? 'active' : '' ?>" href="/kehadiran/web/public/mobile_app.php">
                        <i class="fas fa-mobile-alt"></i>
                        <span>Mobile View</span>
                    </a>
                </li>
            <?php endif; ?>

            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'docs.php' ? 'active' : '' ?>" href="/kehadiran/web/public/docs.php">
                    <i class="fas fa-book"></i>
                    <span>Dokumentasi</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="user-profile">
        <div class="d-flex align-items-center">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div>
                <div class="fw-bold"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></div>
                <small class="opacity-75"><?= ucfirst($_SESSION['role'] ?? 'guest') ?></small>
            </div>
        </div>
        <div class="mt-2">
            <a href="/kehadiran/web/public/logout.php" class="btn btn-outline-light btn-sm w-100">
                <i class="fas fa-sign-out-alt me-1"></i>Logout
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($hasSidebar): ?>
    document.body.classList.add('sidebar-ready');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const pageHeading = document.querySelector('.page-heading');
    const collapsedKey = 'sidebarCollapsed';
    let overlay = document.getElementById('sidebarOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'sidebarOverlay';
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }

    if (!sidebar || !mainContent || !pageHeading) {
        return;
    }

    let contentWrapper = pageHeading.querySelector('.page-heading__content');
    if (!contentWrapper) {
        const wrapper = document.createElement('div');
        wrapper.className = 'page-heading__content';
        const fragment = document.createDocumentFragment();
        while (pageHeading.firstChild) {
            const child = pageHeading.firstChild;
            if (child.nodeType === Node.TEXT_NODE && !child.textContent.trim()) {
                pageHeading.removeChild(child);
                continue;
            }
            fragment.appendChild(child);
        }
        wrapper.appendChild(fragment);
        pageHeading.appendChild(wrapper);
        contentWrapper = wrapper;
    }

    let headingToggle = pageHeading.querySelector('.page-toggle-btn');
    let headingIcon = headingToggle ? headingToggle.querySelector('i') : null;

    pageHeading.classList.add('page-heading--with-toggle');

    if (!headingToggle) {
        headingToggle = document.createElement('button');
        headingToggle.type = 'button';
        headingToggle.className = 'page-toggle-btn';
        headingToggle.setAttribute('aria-controls', 'sidebar');
        headingToggle.setAttribute('aria-expanded', 'true');
        headingToggle.setAttribute('aria-label', 'Sembunyikan menu');
        headingToggle.title = 'Sembunyikan menu';
        headingToggle.innerHTML = '<i class="fas fa-xmark"></i>';
        pageHeading.insertBefore(headingToggle, contentWrapper);
        headingIcon = headingToggle.querySelector('i');
    } else {
        if (headingToggle.parentElement !== pageHeading) {
            pageHeading.insertBefore(headingToggle, contentWrapper);
        }
        if (!headingToggle.hasAttribute('aria-controls')) {
            headingToggle.setAttribute('aria-controls', 'sidebar');
        }
        if (!headingToggle.hasAttribute('aria-expanded')) {
            headingToggle.setAttribute('aria-expanded', sidebar.classList.contains('collapsed') ? 'false' : 'true');
        }
        headingToggle.setAttribute('aria-label', headingToggle.getAttribute('aria-expanded') === 'false' ? 'Tampilkan menu' : 'Sembunyikan menu');
        const strayLabel = headingToggle.querySelector('span');
        if (strayLabel) {
            strayLabel.remove();
        }
        headingIcon = headingToggle.querySelector('i');
        if (!headingIcon) {
            headingIcon = document.createElement('i');
            headingIcon.className = sidebar.classList.contains('collapsed') ? 'fas fa-bars' : 'fas fa-xmark';
            headingToggle.appendChild(headingIcon);
        }
    }

    const applySidebarState = (collapsed, persist = true) => {
        if (collapsed) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
            headingToggle.classList.add('collapsed');
            if (headingIcon) {
                headingIcon.className = 'fas fa-bars';
            }
            headingToggle.title = 'Tampilkan menu';
            headingToggle.setAttribute('aria-expanded', 'false');
            headingToggle.setAttribute('aria-label', 'Tampilkan menu');
            if (overlay) {
                overlay.classList.remove('active');
            }
            document.body.classList.remove('has-overlay');
        } else {
            sidebar.classList.remove('collapsed');
            mainContent.classList.remove('expanded');
            headingToggle.classList.remove('collapsed');
            if (headingIcon) {
                headingIcon.className = 'fas fa-xmark';
            }
            headingToggle.title = 'Sembunyikan menu';
            headingToggle.setAttribute('aria-expanded', 'true');
            headingToggle.setAttribute('aria-label', 'Sembunyikan menu');
            if (overlay && window.innerWidth <= 768) {
                overlay.classList.add('active');
            }
            if (window.innerWidth <= 768) {
                document.body.classList.add('has-overlay');
            }
        }
        if (persist) {
            localStorage.setItem(collapsedKey, collapsed);
        }
    };

    const stored = localStorage.getItem(collapsedKey);
    let collapsedState = stored === 'true';
    if (stored === null && window.innerWidth <= 768) {
        collapsedState = true;
    }
    applySidebarState(collapsedState, stored !== null);

    headingToggle.addEventListener('click', () => {
        const isCollapsed = sidebar.classList.contains('collapsed');
        applySidebarState(!isCollapsed);
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            applySidebarState(false, false);
            if (overlay) {
                overlay.classList.remove('active');
            }
            document.body.classList.remove('has-overlay');
        } else {
            const remembered = localStorage.getItem(collapsedKey);
            const shouldCollapse = remembered ? remembered === 'true' : true;
            applySidebarState(shouldCollapse, false);
        }
    });

    if (overlay) {
        overlay.addEventListener('click', () => applySidebarState(true));
    }

    const navLinks = sidebar.querySelectorAll('.nav-link');
    navLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            if (window.innerWidth <= 768) {
                event.preventDefault();
                const destination = link.getAttribute('href');
                applySidebarState(true);
                setTimeout(() => {
                    window.location.href = destination;
                }, 180);
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (window.innerWidth <= 768 && !sidebar.contains(event.target) && !headingToggle.contains(event.target)) {
            applySidebarState(true);
        }
    });
<?php else: ?>
    document.body.classList.add('no-sidebar');
    const pageHeading = document.querySelector('.page-heading');
    if (!pageHeading) {
        return;
    }
    let actions = pageHeading.querySelector('.page-heading__actions');
    if (!actions) {
        actions = document.createElement('div');
        actions.className = 'page-heading__actions';
        pageHeading.appendChild(actions);
    }
    const hideMenuButton = pageHeading.hasAttribute('data-hide-menu-button');
    if (!hideMenuButton) {
        let menuButton = pageHeading.querySelector('.page-menu-btn');
        if (!menuButton) {
            menuButton = document.createElement('a');
            menuButton.href = '/kehadiran/web/public/menu.php';
            menuButton.className = 'page-menu-btn';
            menuButton.setAttribute('role', 'button');
            menuButton.innerHTML = '<i class="fas fa-th-large"></i><span>Menu Utama</span>';
            actions.insertBefore(menuButton, actions.firstChild || null);
        }
    }
    let logoutButton = pageHeading.querySelector('.page-logout-btn');
    if (!logoutButton) {
        logoutButton = document.createElement('a');
        logoutButton.href = '/kehadiran/web/public/logout.php';
        logoutButton.className = 'page-logout-btn';
        logoutButton.setAttribute('role', 'button');
        logoutButton.innerHTML = '<i class="fas fa-sign-out-alt"></i><span>Keluar</span>';
        actions.appendChild(logoutButton);
    }
<?php endif; ?>
});
</script>
