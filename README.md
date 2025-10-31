# Sistem Kehadiran RFID Enterprise

Sistem kehadiran terpadu yang memadukan **aplikasi web (PHP + MySQL)** dengan **perangkat ESP32 + modul RFID RC522** untuk menangkap UID kartu dan mencatat kehadiran secara real-time.

---

## Fitur Utama

- 🔐 Otentikasi berbasis peran (Admin, Guru, Siswa)
- 📊 Dashboard statistik kehadiran & grafik 7 hari terakhir
- 🛰️ Monitoring real-time & status perangkat RFID
- 📝 CRUD kehadiran manual + dukungan pengajuan izin/sakit
- 🗓️ Kalender libur mingguan & libur khusus
- 📬 Notifikasi otomatis/manual & tampilan mobile untuk siswa
- 🔧 Skrip setup terstruktur di `web/setup/` (migrasi, seed, debugging)
- 📁 Backup/restore database & lampiran

---

## Teknologi

- **Back-end**: PHP 8.x, PDO, MySQL/MariaDB
- **Front-end**: Bootstrap 5, Chart.js
- **Perangkat**: ESP32 DevKit v1, RFID RC522, OLED SSD1306 (opsional)
- **Lainnya**: REST API (ingest/device_config), LocalStorage (preferensi sidebar)

---

## Struktur Direktori

```
kehadiran/
├─ docs/                     → Dokumentasi pengguna & pengembang
├─ firmware/                 → Firmware ESP32 (Arduino sketch, tools, contoh SD)
├─ tools/                    → Skrip utilitas global (mis. test_ingest.php)
├─ web/
│  ├─ api/                   → Endpoint JSON (ingest, realtime, dashboard, dll)
│  ├─ classes/               → Helper / service class PHP
│  ├─ public/                → Halaman antarmuka (login, dashboard, izin, dsb.)
│  ├─ setup/                 → Skrip instalasi & maintenance (lihat README di sana)
│  ├─ sql/                   → Berkas schema & seed SQL
│  ├─ tools/                 → Skrip CLI terkait web (seed_demo_data, create_admin, …)
│  ├─ bootstrap.php          → Bootstrap aplikasi (helper + koneksi DB)
│  ├─ config.php             → Konfigurasi database & opsi inti
│  └─ uploads/               → Direktori lampiran (diabaikan Git)
└─ README_import.txt         → Catatan impor awal (dipertahankan apa adanya)
```

---

## Persiapan Lingkungan

1. **Instalasi yang disarankan**

   - PHP 8.0+ (aktifkan `pdo_mysql`, `mbstring`, `openssl`, `curl`)
   - MySQL/MariaDB 10.4+
   - Apache/Nginx (contoh: XAMPP/LAMPP)

2. **Kloning repository**
   ```bash
   git clone https://github.com/Anamsyahrul/kehadiran.git
   cd kehadiran
   ```

---

## Konfigurasi & Setup

1. **Atur kredensial database**
   Edit `web/config.php`:

   ```php
   'DB_HOST' => '127.0.0.1',
   'DB_NAME' => 'kehadiran_db',
   'DB_USER' => 'root',
   'DB_PASS' => '',
   'TIMEZONE' => 'Asia/Jakarta',
   ```

2. **Buat database & jalankan migrasi**

   ```bash
   php web/setup/scripts/create_database.php
   ```

   atau akses `http://localhost/kehadiran/web/create_db.php`

3. **Opsional: Seed data contoh**

   ```bash
   php web/setup/scripts/seed_sample_data.php
   ```

4. **Cek kesehatan sistem**
   ```bash
   php web/setup/scripts/check_system.php
   ```

---

## Kredensial Bawaan

| Peran          | Username           | Password   |
| -------------- | ------------------ | ---------- |
| Admin          | `admin`            | `admin`    |
| Guru (sample)  | `Dr. Muhammad Ali` | `guru123`  |
| Siswa (sample) | `Ahmad Fauzi`      | `siswa123` |

> Pengguna baru yang dibuat via halaman **Manajemen Siswa** otomatis menggunakan **nama lengkap** sebagai username. Jika kolom password dibiarkan kosong saat aktivasi pertama, sistem mengisi password default sesuai peran (siswa/guru).

---

## Firmware & Perangkat

1. Buka `firmware/kehadiran_esp32/`
2. Sesuaikan `config.h` (SSID, password WiFi, `DEVICE_ID`, `DEVICE_SECRET`)
3. Upload `kehadiran_esp32.ino` ke ESP32 (Arduino IDE/PlatformIO)
4. Pastikan perangkat dapat mengakses `web/api/device_config.php`
5. Opsional: sinkronisasi data siswa via SD card (`students_dump.php`)

---

## Skrip Maintenance Penting

Tersedia di `web/setup/` (lihat `web/setup/README.md`):

- `install/migrate.php` – jalankan migrasi standar (schema + seed)
- `install/rebuild_schema.php` – drop & rebuild penuh
- `install/rebuild_without_fk.php` – rebuild tanpa foreign key
- `install/rebuild_manual.php` – rebuild manual tabel satu per satu
- `scripts/create_admin.php` – buat/reset admin `admin/admin`
- `scripts/debug_login.php` – pastikan admin siap pakai
- `scripts/seed_sample_data.php` – populasi data contoh

---

## Akses Aplikasi

Setelah setup selesai:
👉 `http://localhost/kehadiran/web/public/login.php`

Gunakan akun bawaan atau akun baru yang sudah dibuat.

---

## Dokumentasi Tambahan

- `docs/README.md` – Panduan pengguna siswa & guru
- `docs/ARCHITECTURE.md` – Rangkuman struktur teknis & kredensial
- `docs/FIRMWARE.md`, `docs/WIRING.md` – Instruksi perangkat & wiring

---

## Kontribusi

1. Fork & kloning repository
2. Buat branch baru (`git checkout -b feature/nama-feature`)
3. Pastikan `php -l` untuk file PHP yang diubah
4. Kirim pull request dengan deskripsi perubahan

---

## Lisensi

Belum ditentukan (default semua hak dilindungi). Tambahkan berkas LICENSE jika ingin menerapkan lisensi tertentu.

---

Semoga memudahkan implementasi sistem kehadiran berbasis RFID. Selamat berkontribusi! 🚀
