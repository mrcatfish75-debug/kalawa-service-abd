# Instalasi lokal

## Website kelompok yang sudah berjalan

Push source tidak memerlukan pemasangan ulang, migrasi, impor database, penyalinan ke htdocs, atau perubahan password. Folder repository di D: terpisah dari website XAMPP.

## Komputer teman yang belum memiliki Kalawa Service

1. Pasang XAMPP dan aktifkan Apache/MariaDB. Lingkungan kelompok memakai PHP 8.2.12 dan MariaDB 10.4.32.
2. Download/clone repository untuk membaca source. Untuk pemasangan yang langsung dapat login, minta paket **KALAWA_T4_SIAP_PASANG** melalui saluran privat kelompok.
3. Ekstrak paket lengkap di luar htdocs, misalnya `D:\KALAWA_T4_SIAP_PASANG`. Paket lengkap memiliki folder `database/` dan skrip `Pasang-Baru-T4.ps1`.
4. Buka PowerShell dari folder **paket lengkap**, lalu jalankan:

```powershell
Set-Location D:\KALAWA_T4_SIAP_PASANG
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Pasang-Baru-T4.ps1
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Pasang-Baru-T4.ps1 -Pasang
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Uji-Final-T4.ps1 -Jalankan
```

5. Gunakan `-DenganPassword` jika admin MariaDB berpassword. Untuk lokasi XAMPP/port berbeda, baca opsi `-Target`, `-Php` dan `-Port` pada paket lengkap. Nama folder target website tetap `kalawa-service-f1-korporat-uji`.
6. Buka `http://localhost/kalawa-service-f1-korporat-uji/login.php`. Gunakan akun aplikasi yang dibagikan kelompok secara privat. Password aplikasi berbeda dari password admin MariaDB dan password koneksi baru.

Installer hanya untuk tujuan kosong. Ia menolak database, akun koneksi, folder website atau konfigurasi yang sudah ada. Jangan menghapus instalasi/data lama untuk melewati penolakan.

Dump final sudah memuat struktur, data dan objek. Jangan mengulang migrasi 001–004 setelah memasang dump final.

## Batas

Repository source tidak menyediakan skema baseline lengkap atau seed demo login. Jalur pemasangan ulang yang sudah diuji adalah paket final privat. Jika diperlukan demo publik, buat dan uji skema/seed sintetis terpisah; jangan mempublikasikan backup asli.

GitHub Pages tidak menjalankan PHP/MariaDB. URL repository adalah lokasi source, bukan alamat aplikasi yang langsung dapat dipakai.
