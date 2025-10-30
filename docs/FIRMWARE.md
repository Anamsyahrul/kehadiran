# Panduan Firmware ESP32 RFID (Tanpa microSD)

## 1. Persiapan Perangkat
- **Board**: ESP32 DevKit v1
- **Pembaca RFID**: MFRC522 (SPI)
- **Penyimpanan**: *Tidak diperlukan microSD* – antrian disimpan sementara di RAM
- **Tampilan/RTC (opsional)**: OLED SSD1306 (I2C), RTC DS3231
- **Indikator**: LED hijau/merah dan buzzer pasif
- **Catu daya**: 5V USB atau adaptor 5V dengan regulator 3V3 yang stabil

Semua pin dapat diubah lewat `config.h`. Default wiring:

```
RC522 (SPI)        → ESP32
SDA/SS             → GPIO5
SCK                → GPIO18
MOSI               → GPIO23
MISO               → GPIO19
RST                → GPIO27
3V3/GND            → 3V3 / GND

OLED / RTC (I2C)   → ESP32
SDA                → GPIO21
SCL                → GPIO22
VCC/GND            → 3V3 / GND
```

## 2. Konfigurasi `config.h`
```cpp
#define WIFI_SSID  "NamaWiFi"
#define WIFI_PASS  "PasswordWiFi"
#define API_BASE   "http://192.168.1.10/kehadiran/web/api"
#define DEVICE_ID  "esp32-01"
#define DEVICE_SECRET "secret-sesuai-di-web"
```

Pastikan `DEVICE_ID` dan `DEVICE_SECRET` persis sama dengan entri pada menu **Perangkat RFID** di aplikasi web.

Parameter lain yang penting:
- `USE_SD_STORAGE` : biarkan `0` (default). Mode microSD tidak lagi digunakan.
- `SCAN_DEBOUNCE_MS` : mencegah tap ganda dalam rentang tertentu.
- `BATCH_SIZE` : jumlah event yang dikirim dalam satu request ke `ingest.php`.
- `REG_MODE_FLAG_FILE` : hanya relevan bila microSD diaktifkan; dalam mode server-only gunakan pengaturan registrasi pada aplikasi web.
- `TIMEZONE_POSIX` : kode zona waktu format POSIX (contoh: `WIB-7`, `WITA-8`, `UTC0`). Ubah sesuai lokasi agar jam OLED cocok.
- Firmware otomatis menyelaraskan jam ke NTP dan juga ke nilai `server_time` dari API, sehingga catatan kehadiran selalu mengikuti waktu server.

## 3. Mekanisme Komunikasi
### 3.1 Sinkronisasi konfigurasi firmware
Firmware memanggil `GET /device_config.php` setiap 30 detik dengan parameter:
```
?device_id=ID&ts=<unix>&nonce=<hex>&hmac=<HMAC_SHA256>
```
Pesan HMAC: `device_id|ts|nonce` dengan kunci `DEVICE_SECRET`. Server membalas JSON:
```json
{
  "ok": true,
  "reg_mode": false,
  "weekly_holidays": "6,7",
  "allow_holiday_scan": false,
  "server_time": "2025-01-01T07:00:00+07:00"
}
```
Hasil `reg_mode` digunakan saat `REG_MODE_FLAG_FILE` tidak tersedia.

### 3.2 Pengiriman data kehadiran
- Endpoint: `POST /ingest.php`
- Payload JSON berisi `device_id`, `ts`, `nonce`, `hmac`, dan array event dengan format `uid` & `ts`.
- HMAC menggunakan pesan `device_id|ts|nonce|<events_json_minified>`.
- Jika jaringan terputus, event di-buffer di RAM (`MAX_RAM_QUEUE`) dan akan hilang bila perangkat dimatikan, sehingga disarankan koneksi stabil.

### 3.3 Logging
- Firmware menampilkan status di OLED dan memberi suara/LED sesuai hasil scan.
- Server memakai waktunya sendiri ketika menyimpan data kehadiran; cap waktu dari perangkat hanya dijadikan catatan tambahan.

## 4. Deployment
1. Kloning folder `kehadiran/firmware/kehadiran_esp32` ke Arduino IDE / PlatformIO.
2. Edit `config.h` menyesuaikan WiFi, API, ID, secret, dan pin jika perlu.
3. Pastikan board manager ESP32 terpasang dan pilih flash `80 MHz`, upload speed `921600` (default memadai).
4. Upload firmware, lalu buka Serial Monitor (115200) untuk memantau boot log.
5. Jika WiFi terhubung dan HMAC benar, firmware akan menampilkan `Batch posted. saved=N` saat data berhasil dikirim ke server.
6. Tidak perlu microSD; firmware langsung mengambil konfigurasi & daftar siswa dari server.

## 5. Troubleshooting
- **401 Invalid signature**: cek kembali `DEVICE_SECRET`, pastikan tidak ada spasi/karakter asing.
- **Tidak ada respon NTP**: pastikan ESP32 memiliki akses internet; firmware otomatis jatuh ke RTC jika tersedia.
- **UID tidak dikenal**: server akan menolak event dan admin mendapat notifikasi. Tambahkan UID melalui menu Pengguna atau aktifkan `REGISTRATION_MODE`.
- **Hasil scan tidak muncul**: pastikan perangkat tetap online; periksa log Serial untuk kegagalan `POST /ingest.php` atau antrian RAM yang penuh.

## 6. Checklist Integrasi
- [ ] Device terdaftar di web dengan status aktif dan secret yang sama.
- [ ] `API_BASE` dapat diakses dari jaringan lokasi perangkat (tes dengan curl/ping).
- [ ] Waktu server dan perangkat sinkron (lihat respon `server_time`).
- [ ] Pengaturan hari libur mingguan telah diubah sesuai sekolah agar ingest menolak tap di hari libur.
- [ ] Mode registrasi diatur sesuai prosedur (flag file atau setting `REGISTRATION_MODE`).

Dengan mengikuti konfigurasi di atas, firmware dan aplikasi web akan bekerja selaras sesuai logika sistem kehadiran.
