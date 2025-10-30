# Perencanaan Proyek Perangkat Lunak  
## Sistem Kehadiran RFID Enterprise

---

## 1. Observasi pada Estimasi

| **Elemen**                 | **Observasi Utama**                                                                 | **Dampak terhadap Estimasi**                                                                 |
|---------------------------|--------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------|
| Kompleksitas Fungsional   | Modul web (dashboard, laporan, konfigurasi) terintegrasi dengan perangkat IoT (ESP32) | Estimasi dipisah per domain: backend, frontend, firmware, dokumentasi                       |
| Ketergantungan Eksternal  | Bergantung jaringan Wi-Fi, server PHP/MySQL, dan perangkat RFID fisik                | Sisipkan buffer untuk uji lapangan & debugging hardware                                      |
| Tim Pengembang            | Kombinasi dev web & embedded dengan tingkat pengalaman berbeda                       | Perlu jadwal sinkronisasi lintas disiplin dan waktu onboarding                               |
| Ketersediaan Data         | Data siswa/perangkat belum rapi pada awal implementasi                               | Alokasikan effort import & validasi data                                                     |
| Perubahan Kebijakan       | Jadwal libur, prosedur kehadiran dapat berubah sewaktu-waktu                         | Fitur & dokumentasi harus mudah diubah; tambah cadangan estimasi                             |

**Langkah mitigasi estimasi**
- Gunakan *three-point estimation* (optimistis, realistis, pesimistis) untuk fitur mayor.
- Sisihkan cadangan waktu (contingency) ±15% untuk perubahan kebijakan & regresi hardware.
- Lakukan *prototype spike* untuk fitur yang pertama kali digarap (contoh: notifikasi otomatis lintas kanal).

---

## 2. Tujuan Perencanaan Proyek

1. Menyediakan rencana terintegrasi antara tim perangkat lunak dan tim perangkat IoT.
2. Menghasilkan jadwal realistis yang meminimalkan downtime sistem kehadiran.
3. Menjamin setiap kebutuhan sekolah (hari libur fleksibel, laporan, notifikasi) terpetakan dalam backlog.
4. Menetapkan baseline kualitas: standar kode, pengujian, dan dokumentasi hardware/software.
5. Menyusun mekanisme monitoring progres dan manajemen risiko selama implementasi.

---

## 3. Ruang Lingkup Perangkat Lunak

| Domain | Lingkup Pengembangan | Catatan |
| --- | --- | --- |
| Backend (PHP) | API ingest + device config HMAC, manajemen pengguna, laporan, pengaturan hari libur mingguan, sistem notifikasi | Fokus pada keamanan (HMAC, CSRF, audit log). |
| Frontend (Bootstrap) | Dashboard real-time, laporan analitik, form pengaturan, dokumentasi interaktif | Responsif untuk admin/guru/siswa. |
| Firmware (ESP32 + RC522) | Pembacaan RFID, caching offline, sinkron konfigurasi server, indikator OLED/LED/buzzer | Konfigurasi via `config.h`, antrian RAM, retry logic. |
| Dokumentasi | Panduan pemasangan, firmware, wiring, troubleshooting | Format Markdown + halaman dokumentasi web. |
| DevOps | Backup & restore data, skrip seed pengujian, monitoring log | Termasuk check error Apache/PHP dan cron backup. |

*Out of scope (fase ini)*: integrasi SMS gateway, aplikasi mobile native, modul pembayaran.

---

## 4. Sumber Daya

### 4.1 Sumber Daya Manusia
- **Project Manager / Scrum Master** – koordinasi backlog & timeline.
- **Backend Engineer (PHP/MySQL)** – API, keamanan, laporan.
- **Frontend Engineer** – UI responsif, UX dokumentasi.
- **Firmware Engineer** – C++/Arduino, integrasi hardware.
- **QA Engineer** – testing fungsional & lapangan.
- **Teknisi Lapangan** – instalasi perangkat, pemeliharaan fisik.
- **Admin Sekolah (SME)** – validasi kebutuhan domain & uji terima.

### 4.2 Perangkat Keras & Lunak
- Server (atau VM) dengan PHP 8+, MySQL 10.4+, Apache/Nginx.
- ESP32 DevKit v1, modul MFRC522, OLED SSD1306, RTC DS3231, LED & buzzer (tanpa microSD).
- Laptop untuk upload firmware (Arduino IDE / PlatformIO).
- Software pendukung: Git, VS Code, Postman, tool testing jaringan.

---

## 5. Estimasi Proyek Perangkat Lunak

### 5.1 Tugas Utama & Estimasi Effort

| **Fase**                   | **Deliverable Kunci**                                                   | **Durasi (hari)** |
|---------------------------|--------------------------------------------------------------------------|-------------------|
| Analisis + Perencanaan    | Requirement final, backlog prioritas, rencana sprint                     | 5                 |
| Pengembangan Backend      | Endpoint ingest & device config (HMAC), manajemen pengaturan, laporan    | 15                |
| Pengembangan Frontend     | Dashboard real-time, UI pengaturan, halaman dokumentasi                  | 10                |
| Firmware & Hardware Test  | Update firmware (HMAC, weekly holiday), validasi queue, uji lapangan     | 12                |
| Integrasi & QA            | Pengujian end-to-end web + perangkat, regression, perbaikan bug          | 8                 |
| Dokumentasi & Training    | Panduan admin, manual instalasi hardware, materi pelatihan               | 5                 |
| Buffer Risiko             | Cadangan untuk perubahan requirement / isu perangkat                     | 5                 |

**Total estimasi** ≈ **60** hari kerja (±12 minggu, asumsi 5 hari kerja/minggu).

### 5.2 Jadwal Tingkat Tinggi
1. **Sprint 0 (2 minggu):** finalisasi requirement, setup environment, baseline firmware + API.
2. **Sprint 1 (2 minggu):** fitur pengaturan libur mingguan, notifikasi otomatis, UI pengaturan.
3. **Sprint 2 (2 minggu):** dashboard & laporan, dokumentasi firmware terbaru.
4. **Sprint 3 (2 minggu):** uji lapangan perangkat, optimasi queue, revisi docs.
5. **Sprint 4 (2 minggu):** QA regresi, training user, release candidate.
6. **Stabilisasi (2 minggu):** pilot run, bugfix, prepare Go-Live.

---

## 6. Manajemen Proyek Perangkat Lunak yang Efektif

| **Area**               | **Praktik yang Dianjurkan**                                                                                           |
|-----------------------|------------------------------------------------------------------------------------------------------------------------|
| Metodologi            | Scrum/Kanban hybrid: sprint 2 minggu, board terpisah untuk tugas hardware                                               |
| Komunikasi            | Daily stand-up lintas tim (≤10 menit); sprint review melibatkan admin sekolah; laporan risiko mingguan                 |
| Pengendalian Perubahan| Gunakan RFC ringan; perubahan mayor = update backlog + revisi estimasi                                                  |
| Manajemen Risiko      | Daftar risiko (hardware delay, jaringan, perubahan kebijakan) + trigger & mitigasi                                     |
| Quality Assurance     | Coding standard (PHP-CS-Fixer), unit test API, regression web manual, uji perangkat onsite                              |
| Dokumentasi           | Repository `docs/` + halaman dokumentasi web; update tiap ada perubahan firmware/pengaturan kritikal                    |
| Monitoring            | Pantau log server (access/error), metrik jumlah tap & antrean offline; manfaatkan dashboard internal                    |
| Onboarding/Training   | Pelatihan admin/guru mengenai pengaturan perangkat, hari libur, backup & restore                                       |

---

## 7. Catatan Lanjutan
- Pastikan secret device diganti per unit dan disimpan aman (audit log mencatat perubahan).
- Rencanakan sprint khusus untuk integrasi channel notifikasi tambahan (email/SMS) bila dibutuhkan.
- Evaluasi kapasitas hardware (ESP32) jika beban queue dan modul tambahan (mis. sensor pintu) meningkat.

Dokumen ini dapat dijadikan baseline dan diperbarui setelah setiap sprint review untuk menjaga akurasi estimasi serta kepatuhan terhadap kebutuhan sekolah.
