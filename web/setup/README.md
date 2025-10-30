# Setup & Maintenance Scripts

| Script | Keterangan |
| --- | --- |
| `install/migrate.php` | Menjalankan seluruh berkas migrasi standar (schema.sql, auth_tables.sql, essential_tables.sql, perf_indexes.sql). |
| `install/rebuild_schema.php` | Drop semua tabel kemudian menjalankan kembali paket migrasi standar. |
| `install/rebuild_without_fk.php` | Rebuild database tanpa foreign key – berguna untuk pemulihan data rusak. |
| `install/rebuild_fixed_schema.php` | Membangun ulang database menggunakan `schema_fixed.sql`. |
| `install/rebuild_manual.php` | Membuat tabel satu per satu (mode darurat ketika migrasi biasa gagal). |
| `scripts/create_database.php` | Membuat database (jika belum ada) lalu menjalankan `install/migrate.php`. |
| `scripts/check_system.php` | Health check sederhana (database, tabel penting, file, session). |
| `scripts/create_admin.php` | Membuat atau mereset akun admin dengan kredensial `admin/admin`. |
| `scripts/debug_login.php` | Memastikan akun admin valid dan menampilkan informasi login. |
| `scripts/seed_sample_data.php` | Menambahkan data contoh (siswa, guru, perangkat, libur). |

Semua skrip ini dapat dieksekusi melalui CLI, misalnya:

```bash
php web/setup/scripts/create_database.php
php web/setup/scripts/seed_sample_data.php
```

File lama di root (`web/create_db.php`, `web/install_*.php`, dll) tetap tersedia sebagai pembungkus, sehingga link terdahulu tidak rusak.
