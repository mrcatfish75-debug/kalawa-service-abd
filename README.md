# Kalawa Service

Sistem operasional bengkel multi-cabang untuk proyek Administrasi Basis Data (ABD), Tugas 4. Implementasi melanjutkan rancangan, aturan bisnis, dan objek database Tugas 1–3.

Versi source: **CETAK_R05**, terdiri dari **66 berkas aplikasi**. Persiapan repository mempertahankan seluruh byte aplikasi, migrasi, dan alat uji dari paket final yang telah diperiksa. Tidak ada perubahan fitur, tampilan, otorisasi, atau data.

## Teknologi dan fitur

PHP dengan PDO MySQL, MariaDB, HTML/CSS dan JavaScript. Lingkungan kelompok: Windows/XAMPP, PHP 8.2.12, MariaDB 10.4.32.

- Login serta pembatasan enam role: Owner, Admin Cabang, Kasir, Mekanik, Petugas Gudang dan Auditor.
- CRUD pelanggan, motor, jasa servis, suku cadang dan supplier sesuai izin.
- Pendaftaran, WO, rincian jasa/barang, mekanik utama/pembantu, stok dan urutan status pekerjaan.
- Pembayaran individual, diskon, penerimaan kas dan bukti pembayaran.
- Pelanggan korporat, kontrak, plafon kredit dan tagihan kolektif bulanan.
- Empat laporan JOIN: riwayat servis, penerimaan pembayaran per cabang/periode, pemakaian barang per WO dan stok di bawah minimum.
- Jejak audit, rincian servis, navigasi responsif, pencarian menu dan dokumen cetak dengan kop perusahaan.

Lihat [pembagian role](uji/CAKUPAN-DAN-ROLE.md) dan [matriks tugas](docs/MATRIKS-TUGAS.md).

## Struktur

| Lokasi | Isi |
|---|---|
| `website/` | Source lengkap, logo, CSS dan JavaScript |
| `sql/` | Empat migrasi perubahan requirement |
| `uji/` | Alat uji dan fixture sintetis untuk salinan terisolasi |
| `tools/final_common.php` | Fungsi pendukung pengujian |
| `docs/` | Panduan instalasi, migrasi, pengujian dan pengumpulan |
| `konfigurasi-contoh.php` | Contoh tanpa password koneksi aktual |
| `manifest-final.json` | Hash 66 berkas aplikasi R05 |
| `Periksa-Repo.ps1` | Pemeriksaan berkas sebelum commit |

## Instalasi dan data

**Repository ini menyediakan source dan migrasi, tanpa database baseline beserta data/login.** Empat migrasi historis tidak menggantikan skema awal atau dump final. Clone saja belum menghasilkan aplikasi yang bisa dipakai login.

Untuk anggota kelompok/penguji yang berwenang, gunakan paket lengkap **KALAWA_T4_SIAP_PASANG** yang dibagikan secara privat. Paket itu berisi database serta installer yang telah diuji. Ikuti [instalasi lokal](docs/INSTALASI-LOKAL.md). Password akun aplikasi disampaikan secara privat; installer membuat password koneksi database baru.

Website kelompok tetap di `C:\xampp\htdocs\kalawa-service-f1-korporat-uji`, dengan login `http://localhost/kalawa-service-f1-korporat-uji/login.php`. Nama repository boleh `kalawa-service-abd`, tetapi folder website, database, akun koneksi dan path sesi R05 masih memiliki pengaman kompatibilitas. Jangan menggantinya hanya untuk menyamakan nama repository.

GitHub menyimpan source. PHP/MariaDB dijalankan melalui XAMPP atau server yang mendukungnya. GitHub Pages tidak menjalankan PHP.

## Verifikasi

Pemeriksaan repository tidak memerlukan XAMPP:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Periksa-Repo.ps1
```

Pada website XAMPP yang telah terpasang sesuai R05:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Uji-Final-T4.ps1
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Uji-Final-T4.ps1 -Jalankan -HasilRoot D:\KALAWA_HASIL_UJI_GITHUB
```

Tambahkan `-DenganPassword` pada pengujian `-Jalankan` jika admin MariaDB berpassword. Output disimpan di `D:\KALAWA_HASIL_UJI_GITHUB`, di luar repo, karena dapat memuat backup penuh dan kredensial salinan uji.

Bukti R05 yang telah diperiksa menunjukkan **442 PASS, 0 FAIL, 0 BLOCKED**. Ini hasil pengujian sebelumnya; pengemasan repository tidak mengulang transaksi HTTP/SQL. Baca [konteks pengujian](docs/PENGUJIAN.md).

## Migrasi dan pengumpulan

Keempat migrasi di `sql/` sudah diterapkan pada database final 26 tabel. Jangan menjalankan ulang pada database final. Baca [riwayat migrasi](docs/RIWAYAT-MIGRASI.md).

Source/repository dan migrasi disertakan melalui repository ini. Laporan PDF, file/lokasi backup privat, video, logbook dan identitas kelompok mengikuti mekanisme pengumpulan dosen. Lihat [pemetaan pengumpulan](docs/PENGUMPULAN-T4.md).

Backup SQL, konfigurasi aktual, password login, hasil transaksi dan PDF pelanggan tidak dimasukkan ke repository. Kredensial contoh tidak memberikan akses ke situs kelompok.
