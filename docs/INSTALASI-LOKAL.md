# Instalasi dari clone

Panduan lengkap ada pada bagian **Instalasi pada laptop Windows lain dari clone** di [README](../README.md). Urutannya: siapkan XAMPP baseline, clone repository, peroleh paket database privat, periksa, pasang, login, lalu uji.

## Parameter peluncur

| Parameter | Nilai bawaan/kegunaan |
|---|---|
| `-PaketFinal` | Wajib: folder paket lengkap yang memuat MANIFEST-PAKET.json dan database |
| `-Target` | C:\xampp\htdocs\kalawa-service-f1-korporat-uji |
| `-Php` | C:\xampp\php\php.exe |
| `-Port` | 3306, port MariaDB |
| `-AdminUser` | root, akun admin MariaDB lokal |
| `-DenganPassword` | Minta password admin secara tersembunyi |
| `-Pasang` | Lakukan pemasangan; tanpa switch ini hanya pemeriksaan |

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Pasang-Dari-Clone.ps1 -PaketFinal 'D:\KALAWA_T4_SIAP_PASANG'
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Pasang-Dari-Clone.ps1 -PaketFinal 'D:\KALAWA_T4_SIAP_PASANG' -Pasang
```

Peluncur memeriksa 66 source dan 123 berkas paket, lalu menggunakan installer final di paket tersebut. Source yang diinstal dari paket sama dengan clone. Tujuan harus kosong dan nama folder situs tetap mengikuti pengaman R05.

Password aplikasi dipertahankan dari backup dan dibagikan privat. Installer membuat password SQL service user baru dan menyimpan konfigurasi di luar htdocs.

Pada laptop pemilik yang sudah menjalankan Kalawa, gunakan pemeriksa final untuk membandingkan source, bukan peluncur pemasangan ulang.
