# Validasi demo publik R03

Pemasangan nyata dan suite HTTP/SQL telah dijalankan menggunakan MariaDB 10.4.32, PHP 8.4.27 dan PowerShell 7.4.19 pada Linux terisolasi.

| Pemeriksaan | Hasil |
|---|---|
| Pemeriksaan tanpa memasang | PASS; tidak membuat website |
| Pemasangan database baru dan 66 source | PASS |
| Pemasangan ulang ke tujuan yang sudah ada | Ditolak; source/config tetap sama |
| Login sebelas akun demo melalui HTTP | PASS; tujuan halaman sesuai role |
| WO siap bayar, bukti lunas dan persediaan demo | PASS |
| Suite regresi aplikasi | 442 PASS, 0 FAIL, 0 BLOCKED |
| Source/config setelah regresi | Tetap sama |
| Source aplikasi terhadap final R05 | Seluruh 66 berkas identik |

MariaDB dijalankan TCP lokal tanpa Unix socket sesuai pembatasan lingkungan uji. Konfigurasi PHP uji memakai output buffering 4096, session.save_path yang dapat ditulis dan Asia/Jakarta. Windows XAMPP menggunakan konfigurasi lokalnya; rekomendasi baseline tetap PHP 8.2.12/MariaDB 10.4.32 yang sudah dipakai kelompok.

Data demo dibuat baru dan tidak memuat baris backup asli. Source website/migrasi tidak diubah. Hasil ini menguji demo pada lingkungan terisolasi, bukan menyatakan laptop teman telah diuji sebelum mereka memasangnya.
