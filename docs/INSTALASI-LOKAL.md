# Instalasi Kalawa Service

Panduan lengkap Download ZIP/clone, XAMPP, akun role, pemasangan dan pengujian ada pada [README](../README.md).

## Demo publik

Tidak memerlukan backup privat atau Git jika memakai Download ZIP. Dari root hasil ekstraksi:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Periksa-Repo.ps1
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Pasang-Demo.ps1
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Pasang-Demo.ps1 -Pasang
```

Gunakan -DenganPassword untuk admin MariaDB berpassword. Parameter lainnya: -Target, -Php, -Port dan -AdminUser. Installer membuat schema/data fiktif pada tujuan kosong dan mempertahankan 66 source R05.

Login pada /kalawa-service-f1-korporat-uji/login.php. Password akun demo 123456781234; username utama owner_demo, admin_demo, kasir_demo, mekanik_demo, gudang_demo dan auditor_demo.

## Snapshot kelompok

Untuk memakai data arsip asli tugas, peroleh paket lengkap secara privat:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Pasang-Dari-Clone.ps1 -PaketFinal 'D:\KALAWA_T4_SIAP_PASANG'
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Pasang-Dari-Clone.ps1 -PaketFinal 'D:\KALAWA_T4_SIAP_PASANG' -Pasang
```

Kedua profil memakai identifier aplikasi yang sama, sehingga installer menolak jika Kalawa/config/database/user tersebut sudah ada. Akun snapshot tetap seperti backup; akun demo hanya dibuat pada instalasi demo baru. Gunakan pemeriksa final untuk laptop pemilik yang sudah terpasang.
