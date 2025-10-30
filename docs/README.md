# Panduan Pengguna (Siswa & Guru) – Sistem Kehadiran RFID Enterprise

Dokumen ini merangkum langkah penggunaan untuk siswa dan guru. Simpan alamat situs sekolah beserta kredensial Anda agar presensi berjalan lancar.

## 1. Masuk ke Aplikasi
1. Buka alamat `http://localhost/kehadiran/web/public/login.php` (atau alamat yang diberikan sekolah).
2. Masukkan **username** dan **kata sandi** yang dibagikan admin sekolah.
3. Centang “Ingat saya” jika menggunakan perangkat pribadi. Jangan aktifkan di komputer umum.
4. Klik **Masuk**. Setelah berhasil Anda akan langsung melihat **Menu Utama**.

> Catatan: Admin bawaan menggunakan username `admin` dengan kata sandi `admin`. Pengguna baru memakai nama lengkap sebagai username secara otomatis.

## 2. Menu Utama
Menu utama menggantikan sidebar sehingga semua fitur pengguna berada pada satu halaman:
- **Profil Kehadiran** – ringkasan status hari ini dan riwayat 30 hari.
- **Notifikasi** – daftar pengumuman terbaru dari sekolah.
- **Pengajuan Izin** – formulir izin/sakit (menunggu persetujuan guru/admin).
- **Ganti Password** – perbarui kata sandi akun Anda.
- **Panduan Pengguna** – halaman ini bila ingin membaca ulang petunjuk.

Klik salah satu kartu untuk masuk ke halaman yang diinginkan; gunakan tombol **Menu Utama** di bagian atas halaman apa pun untuk kembali.

## 3. Profil Kehadiran
Halaman ini menampilkan:
- **Status hari ini**: Tepat Waktu, Terlambat, Izin, Sakit, atau Alpa.
- **Jam scan** pertama dan terakhir (jika sudah melakukan check-in/check-out).
- **Grafik ringkas** jumlah kehadiran Tepat Waktu, Terlambat, Izin, Sakit, dan Alpa selama 30 hari.
- **Riwayat harian** lengkap dengan keterangan singkat per tanggal.

> Tips: Pastikan kartu RFID Anda di-scan hanya satu kali saat datang dan mengikuti aturan jam yang ditetapkan sekolah. Sistem otomatis menolak scan di luar hari yang sama.

## 4. Panduan Khusus Siswa
- Gunakan **Profil Kehadiran** untuk memantau status hadir/terlambat, jam scan pertama dan terakhir.
- Cek **Notifikasi** secara berkala. Ikon merah menandakan ada pesan belum dibaca.
- Kirim permohonan melalui **Pengajuan Izin** ketika tidak bisa hadir (sertakan lampiran jika diminta).
- Bila kartu RFID tidak terbaca atau status tidak berubah, laporkan ke guru piket.
- Ubah kata sandi lewat **Ganti Password** jika merasa akun kurang aman atau diminta admin.

## 5. Panduan Khusus Guru/Wali
- Buka **Dashboard** dari menu utama untuk melihat statistik hadir/terlambat/tidak hadir per tanggal.
- Gunakan **Real-time Monitoring** untuk memantau scan terbaru serta kondisi perangkat.
- Unduh rekap melalui **Laporan** dan gunakan filter sesuai kebutuhan.
- Tinjau permohonan siswa di **Persetujuan Izin** dan berikan keputusan agar status otomatis tercatat.
- Kirim pengumuman lewat **Notifikasi** (ke semua siswa atau target tertentu).
- Akses **Profil Kehadiran** untuk melihat riwayat pribadi Anda jika juga melakukan scan.
- Perbarui kata sandi secara berkala melalui **Ganti Password**, terutama bila perangkat dipakai bergantian.

## 6. Notifikasi
- Notifikasi baru diberi label merah. Klik kartu atau tombol “Tandai dibaca” (jika tersedia) untuk menghapus tanda tersebut.
- Sistem mengirim notifikasi otomatis untuk keterlambatan, kartu tidak dikenal, atau informasi penting dari sekolah.
- Jika Anda melewatkan pemberitahuan, buka kembali halaman ini dari Menu Utama.

## 7. Pengajuan Izin & Sakit
1. Pilih jenis permohonan: **Izin** atau **Sakit**.
2. Tentukan tanggal mulai dan selesai (boleh satu hari saja).
3. Isi alasan singkat, lalu unggah lampiran (surat dokter, surat orang tua, dsb) jika diminta.
4. Klik **Kirim Permohonan**. Permintaan akan menunggu persetujuan wali kelas/admin.
5. Lihat status pengajuan (Menunggu, Disetujui, atau Ditolak) pada tabel di bagian bawah halaman yang sama.

> Catatan: Sistem menolak file lampiran di atas batas ukuran yang ditentukan sekolah. Gunakan format umum seperti PDF, JPG, atau PNG.

## 8. Ganti Password
1. Dari **Menu Utama**, pilih kartu **Ganti Password** (admin juga bisa menggunakan tautan di sidebar).
2. Masukkan password lama, kemudian password baru minimal 8 karakter dan konfirmasi ulang.
3. Klik **Simpan Password Baru**. Sistem otomatis mengeluarkan sesi perangkat lain demi keamanan.

## 9. FAQ Singkat
- **Lupa kata sandi**: hubungi admin sekolah untuk reset.
- **Scan tidak terbaca**: pastikan kartu ditempel penuh ke pembaca. Jika masih gagal, segera lapor petugas.
- **Status belum berubah setelah scan**: tunggu beberapa detik; dashboard guru akan memperbarui otomatis begitu perangkat mengirim data.
- **Tidak punya akun**: siswa baru wajib mendaftarkan akun melalui admin sekolah sebelum bisa melakukan presensi.

Tetap jaga kartu RFID dan akun Anda. Bila ada kendala lain, segera hubungi guru atau admin yang bertanggung jawab atas sistem kehadiran. Selamat menggunakan!
