<?php
require __DIR__ . '/_auth.php';

$schoolName = getSetting('sekolah_NAME', 'SMA Peradaban Bumiayu');
$username = $_SESSION['username'] ?? 'Pengguna';
$role = $_SESSION['role'] ?? 'student';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <title>Panduan Pengguna - Sistem Kehadiran RFID Enterprise</title>
  <style>
    body {
      background-color: #f5f7fb;
    }
    .card {
      border: none;
      border-radius: 18px;
      box-shadow: 0 18px 40px rgba(15,32,70,0.12);
    }
    .doc-section {
      margin-bottom: 2rem;
    }
    .doc-section:last-child {
      margin-bottom: 0;
    }
    .doc-section h3 {
      font-size: 1.2rem;
      font-weight: 700;
      color: #1c3570;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 1rem;
    }
    .doc-section h3 i {
      color: #2a5298;
    }
    ul.checklist {
      list-style: none;
      padding-left: 0;
      margin-bottom: 0;
    }
    ul.checklist li {
      position: relative;
      padding-left: 1.75rem;
      margin-bottom: 0.75rem;
      color: rgba(18,37,71,0.78);
      line-height: 1.55;
    }
    ul.checklist li::before {
      content: '\f00c';
      font-family: 'Font Awesome 6 Free';
      font-weight: 900;
      position: absolute;
      left: 0;
      top: 0;
      color: #2a5298;
    }
    .role-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.35rem 0.9rem;
      border-radius: 999px;
      font-size: 0.85rem;
      font-weight: 600;
      background: rgba(42,82,152,0.12);
      color: #1c3570;
      margin-bottom: 1rem;
    }
    .role-pill i {
      color: #2a5298;
    }
    .alert-tip {
      border-radius: 14px;
      background: rgba(42,82,152,0.08);
      color: rgba(18,37,71,0.85);
      padding: 1rem 1.1rem;
      display: flex;
      gap: 0.75rem;
      align-items: flex-start;
    }
    .alert-tip i {
      color: #2a5298;
      font-size: 1.1rem;
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
              <i class="fas fa-book-open"></i>
            </span>
            <div class="page-heading__label">
              <h2>Panduan Pengguna</h2>
              <p class="page-heading__description"><?= htmlspecialchars($schoolName) ?> · Halo, <?= htmlspecialchars($username) ?>! <?= $role === 'admin' ? 'Dokumentasi lengkap untuk administrator sistem.' : 'Ringkasan penggunaan untuk siswa & guru.' ?></p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-body p-4 p-lg-5">
<?php if ($role === 'admin'): ?>
            <div class="doc-section">
              <h3><i class="fas fa-info-circle"></i>Gambaran Umum Sistem</h3>
              <p>Sistem Kehadiran RFID Enterprise memadukan ESP32 + MFRC522 sebagai pembaca kartu, layanan web PHP/MySQL, serta dashboard monitoring real-time. Alur utama:</p>
              <ul class="checklist">
                <li>Perangkat ESP32 membaca UID kartu RFID, menampilkan status pada OLED, dan mengirim catatan ke endpoint REST (<code>/web/api/ingest.php</code>).</li>
                <li>Server memverifikasi HMAC, menyimpan data ke tabel <code>kehadiran</code>, memberi status hadir/terlambat/izin/sakit/alpa, dan memicu notifikasi bila perlu.</li>
                <li>Dashboard admin/guru menampilkan statistik, laporan, dan data real-time; siswa melihat riwayat pribadi melalui tampilan mobile.</li>
              </ul>
            </div>

            <div class="doc-section">
              <h3><i class="fas fa-users-cog"></i>Hak Akses & Peran</h3>
              <div class="row g-3">
                <div class="col-md-4">
                  <div class="card bg-danger text-white h-100">
                    <div class="card-body">
                      <h5 class="mb-3"><i class="fas fa-user-shield me-2"></i>Admin</h5>
                      <ul class="mb-0">
                        <li>Manajemen pengguna, perangkat, pengaturan sekolah</li>
                        <li>Akses penuh ke dashboard, laporan, audit log</li>
                        <li>Backup & Restore database, generate seed data</li>
                        <li>Menyetujui izin, mengirim notifikasi massal</li>
                      </ul>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="card bg-warning text-white h-100">
                    <div class="card-body">
                      <h5 class="mb-3"><i class="fas fa-chalkboard-teacher me-2"></i>Guru</h5>
                      <ul class="mb-0">
                        <li>Dashboard, monitoring real-time, laporan</li>
                        <li>Persetujuan permohonan izin siswa</li>
                        <li>Kirim notifikasi ke siswa tertentu/kelas</li>
                        <li>Akses tampilan mobile untuk riwayat pribadi</li>
                      </ul>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="card bg-info text-white h-100">
                    <div class="card-body">
                      <h5 class="mb-3"><i class="fas fa-user-graduate me-2"></i>Siswa</h5>
                      <ul class="mb-0">
                        <li>Lihat status hadir & riwayat scan</li>
                        <li>Terima notifikasi dan pengumuman sekolah</li>
                        <li>Ajukan izin/sakit dan pantau statusnya</li>
                        <li>Ganti password untuk keamanan akun</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="doc-section">
              <h3><i class="fas fa-server"></i>Langkah Instalasi Server</h3>
              <ol class="ps-3">
                <li><strong>Persiapan Lingkungan</strong>: gunakan Apache/Nginx + PHP 8.0+, aktifkan ekstensi <code>pdo_mysql</code>, <code>openssl</code>, <code>mbstring</code>, <code>curl</code>. Pastikan MySQL/MariaDB siap dengan hak akses penuh ke schema.</li>
                <li><strong>Salin Aplikasi</strong>: taruh folder <code>kehadiran</code> pada web root (misal <code>htdocs/kehadiran</code>).</li>
                <li><strong>Import Database</strong>: jalankan <code>web/sql/schema.sql</code>. Tambahkan <code>essential_tables.sql</code> dan <code>perf_indexes.sql</code> bila memerlukan tabel/indeks tambahan. Skrip <code>schema_complete.sql</code> menyediakan paket lengkap satu kali import.</li>
                <li><strong>Konfigurasi Database</strong>: sesuaikan kredensial di <code>web/config.php</code> (host, nama DB, user, password, timezone).</li>
                <li><strong>Akun Awal</strong>: login admin default via <code>/web/public/login.php</code> lalu segera ubah password.</li>
              </ol>
            </div>

            <div class="doc-section">
              <h3><i class="fas fa-sliders-h"></i>Konfigurasi Sistem</h3>
              <ul class="checklist">
                <li>Buka menu <strong>Pengaturan Sekolah</strong> untuk mengisi nama sekolah, jam mulai/selesai, toleransi terlambat, hari libur mingguan, serta opsi wajib checkout.</li>
                <li><strong>Mode Registrasi</strong> diaktifkan saat pendaftaran kartu, mempersilakan firmware menerima UID baru.</li>
                <li>Gunakan menu <strong>Perangkat RFID</strong> untuk menambahkan device ID & secret. Nilai ini dimasukkan ke <code>firmware/config.h</code>.</li>
                <li><strong>Hari Libur Khusus</strong> dapat diberikan via menu Kalender Libur (<code>holiday_calendar.php</code>).</li>
                <li>Menu <strong>Backup</strong> dan <strong>Restore</strong> menghasilkan arsip SQL + file lampiran; simpan secara berkala.</li>
              </ul>
            </div>

            <div class="doc-section">
              <h3><i class="fas fa-microchip"></i>Firmware & Perangkat</h3>
              <p>Lihat detail di <code>docs/FIRMWARE.md</code> dan <code>docs/WIRING.md</code>. Ringkasan koneksi utama:</p>
              <div class="table-responsive">
                <table class="table table-bordered align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>Modul</th><th>Pin</th><th>GPIO ESP32</th><th>Keterangan</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr><td rowspan="5">RFID MFRC522</td><td>SDA / SS</td><td>GPIO5</td><td>Chip Select</td></tr>
                    <tr><td>SCK</td><td>GPIO18</td><td>Clock SPI</td></tr>
                    <tr><td>MOSI</td><td>GPIO23</td><td>Data keluar</td></tr>
                    <tr><td>MISO</td><td>GPIO19</td><td>Data masuk</td></tr>
                    <tr><td>RST</td><td>GPIO27</td><td>Reset modul</td></tr>
                    <tr><td>OLED SSD1306</td><td>SDA / SCL</td><td>GPIO21 / GPIO22</td><td>I2C display status</td></tr>
                    <tr><td>LED & Buzzer</td><td>Hijau / Merah / Buzzer</td><td>GPIO25 / GPIO26 / GPIO32</td><td>Feedback hadir/tolak</td></tr>
                  </tbody>
                </table>
              </div>
              <p>Firmware mendukung mode tanpa microSD. Data scan disimpan di memori sementara saat offline dan dikirim ulang ketika koneksi pulih. Endpoint <code>device_config.php</code> menyediakan pengaturan terbaru (mode registrasi, versi data siswa, dll.).</p>
            </div>

            <div class="doc-section">
              <h3><i class="fas fa-chart-bar"></i>Monitoring & Pelaporan</h3>
              <ul class="checklist">
                <li><strong>Dashboard</strong>: statistik harian, grafik 7 hari, daftar notifikasi terbaru.</li>
                <li><strong>Real-time</strong>: feed scan terkini, status perangkat, dan total siswa hadir.</li>
                <li><strong>Laporan</strong>: filter berdasarkan kelas/tanggal, export PDF/print.</li>
                <li><strong>Mobile View</strong>: tampilan ringkas yang otomatis disesuaikan dengan peran (siswa/guru).</li>
                <li><strong>Persetujuan Izin</strong>: catatan status “Izin” & “Sakit” mempengaruhi laporan kehadiran.</li>
              </ul>
            </div>

            <div class="doc-section">
              <h3><i class="fas fa-shield-alt"></i>Keamanan</h3>
              <ul class="checklist">
                <li>Semua endpoint perangkat memakai HMAC SHA-256 dengan <code>DEVICE_SECRET</code>. Timestamp disinkronkan via NTP; drift di atas 5 menit ditolak.</li>
                <li>Login dilindungi CSRF token, pembatasan percobaan, dan audit log (<code>audit_logs</code>).</li>
                <li>Token “ingat saya” (remember me) disimpan di tabel <code>remember_tokens</code> dan dihapus ketika password diganti.</li>
                <li>Gunakan menu <strong>Ganti Password</strong> secara berkala dan pastikan admin lain melakukan hal yang sama.</li>
                <li>Jaga izin folder <code>web/uploads/</code> agar hanya web server yang dapat menulis.</li>
              </ul>
            </div>

            <div class="doc-section">
              <div class="alert-tip">
                <i class="fas fa-life-ring"></i>
                <div>
                  <strong>Solusi Cepat</strong>
                  <ul class="mb-0">
                    <li>Gunakan <code>web/tools/create_admin.php</code> bila akun admin terkunci/hilang.</li>
                    <li>Jalankan <code>web/tools/seed_demo_data.php</code> atau <code>seed_random_data.php</code> untuk data uji.</li>
                    <li>Gunakan <code>tools/test_ingest.php</code> guna memastikan endpoint menerima payload sebelum perangkat dipasang.</li>
                  </ul>
                </div>
              </div>
            </div>
<?php else: ?>
            <div class="doc-section">
              <h3><i class="fas fa-compass"></i>Halaman Menu Utama</h3>
              <p>Setelah login, kartu-kartu menu memudahkan akses fitur. Gunakan tombol <strong>Menu Utama</strong> di kiri atas untuk kembali, dan tombol <strong>Keluar</strong> di kanan atas untuk mengakhiri sesi.</p>
            </div>

            <div class="doc-section">
              <span class="role-pill"><i class="fas fa-user-graduate"></i>Untuk Siswa</span>
              <ul class="checklist">
                <li>Buka kartu <strong>Profil Kehadiran</strong> untuk memantau status hari ini, jam scan pertama/terakhir, serta rekap 30 hari terakhir.</li>
                <li>Cek <strong>Notifikasi</strong> secara rutin. Ikon merah menandakan ada pesan baru dari sekolah.</li>
                <li>Ajukan <strong>Izin</strong> atau <strong>Sakit</strong> dari menu yang sama. Lengkapi tanggal & alasan; tambahkan lampiran bila diminta.</li>
                <li>Jika kartu tidak terbaca atau status tidak berubah, laporkan ke guru agar data diperiksa.</li>
                <li>Gunakan menu <strong>Ganti Password</strong> saat diminta atau ketika merasa akun kurang aman.</li>
              </ul>
            </div>

            <div class="doc-section">
              <span class="role-pill"><i class="fas fa-chalkboard-teacher"></i>Untuk Guru / Wali Kelas</span>
              <ul class="checklist">
                <li>Pantau rekap kelas melalui <strong>Dashboard</strong> dan grafik 7 hari.</li>
                <li><strong>Real-time Monitoring</strong> membantu melihat scan terbaru serta status perangkat.</li>
                <li>Gunakan <strong>Laporan</strong> untuk filter per kelas, ekspor PDF, atau cetak.</li>
                <li>Kelola permintaan di <strong>Persetujuan Izin</strong> agar status siswa otomatis diperbarui.</li>
                <li>Kirim pengumuman melalui <strong>Notifikasi</strong> ke siswa tertentu atau seluruh siswa.</li>
                <li>Ganti password secara berkala via menu <strong>Ganti Password</strong>, terutama jika perangkat dipakai bersama.</li>
              </ul>
            </div>

            <div class="doc-section">
              <h3><i class="fas fa-key"></i>Cara Ganti Password</h3>
              <ol class="ps-3">
                <li>Pilih kartu <strong>Ganti Password</strong> pada Menu Utama (admin juga bisa melalui sidebar).</li>
                <li>Isi password lama, masukkan password baru minimal 8 karakter, lalu konfirmasi ulang.</li>
                <li>Klik <strong>Simpan Password Baru</strong>. Sistem secara otomatis mengeluarkan sesi perangkat lain demi keamanan.</li>
              </ol>
            </div>

            <div class="doc-section">
              <div class="alert-tip">
                <i class="fas fa-circle-info"></i>
                <div>
                  <strong>Butuh bantuan?</strong>
                  <p class="mb-1">Jika lupa password atau menemukan data kehadiran yang tidak sesuai, hubungi admin sekolah dan sertakan detail waktunya.</p>
                  <p class="mb-0">Kartu RFID rusak/hilang? Segera laporkan supaya UID lama dinonaktifkan dan diganti yang baru.</p>
                </div>
              </div>
            </div>
<?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
