# Pengujian dan cakupan bukti

## Pemeriksaan repository

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Periksa-Repo.ps1
```

Memeriksa 66 hash source, migrasi yang tersedia serta nama/path berkas privat yang lazim. Pemeriksaan ini bukan pemindai rahasia lengkap.

## Kesamaan source GitHub dengan htdocs

Pada laptop pemilik:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Periksa-Final-Lokal.ps1 -PaketFinal 'D:\KALAWA_T4_SIAP_PASANG' -SimpanBukti
```

Gunakan path paket yang sebenarnya. Alat fetch origin/main, mensyaratkan HEAD cocok dengan remote, membandingkan blob Git dengan 66 berkas htdocs, memeriksa SHA-256 source/migrasi dan 123 berkas paket final. Source runtime tambahan di htdocs di luar manifest dihentikan untuk ditinjau; folder metadata pengembangan seperti .git/.agents tidak dianggap source aplikasi.

Metadata ditulis ke BUKTI-FINAL-LOKAL.json dan BUKTI-BACKUP.md. Alat tidak membaca konfigurasi berpassword atau database live. Ia tidak mengklaim data live sama dengan snapshot backup 17 November.

Setelah commit/push, jalankan ulang tanpa -SimpanBukti agar working tree tetap bersih. Commit dokumentasi berikutnya dapat berbeda dari commit yang tercatat pada bukti; hash tree website tetap sama bila source tidak diubah.

## Uji transaksi dan recovery

Pada website XAMPP yang sudah terpasang:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Uji-Final-T4.ps1
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Uji-Final-T4.ps1 -Jalankan -HasilRoot 'D:\KALAWA_HASIL_UJI'
```

Gunakan -DenganPassword jika admin MariaDB berpassword dan sesuaikan -Target/-Php untuk lokasi XAMPP lain. Hasil uji disimpan di luar repo karena dapat berisi backup dan kredensial salinan.

Suite memakai salinan database/website, fixture sintetis, enam role, kasus negatif, kontrol cabang/penugasan, CRUD, WO, stok, status, pembayaran, korporat, laporan, audit dan recovery. Lihat [cakupan](../uji/CAKUPAN-DAN-ROLE.md). Hindari transaksi pengguna lain selama snapshot diuji.

Bukti arsip paket final R05 menunjukkan 442 PASS, 0 FAIL, 0 BLOCKED. Bukti restore full export/import mencakup 26 tabel dan 38 definisi objek total, termasuk tabel tersebut, dengan baris/hash identik. Waktu pengujian arsip tidak disamakan dengan waktu pemeriksaan GitHub.

Catatan validasi patch sebelumnya dipertahankan pada VALIDASI-PAKET.md. Browser/cetak tetap mengikuti checklist; CLI tidak menguji setiap input, perangkat atau printer fisik.


## Demo publik R03

Demo telah dipasang nyata pada MariaDB 10.4.32, PHP 8.4.27 dan PowerShell 7.4.19 di Linux terisolasi. Sebelas akun demo login melalui HTTP ke halaman role yang benar. Suite yang sama menghasilkan 442 PASS, 0 FAIL, 0 BLOCKED pada data fiktif. Pemasangan ulang ditolak dan source/config tetap utuh.

Lingkungan uji PHP memakai buffering/session/timezone yang sesuai baseline XAMPP. Pemeriksaan ini tidak menggantikan eksekusi Windows/Apache di laptop teman. Source aplikasi 66 berkas tidak diubah. Rincian ada pada VALIDASI-DEMO-R03.md.

## Eksekusi bukti baru — 21 November 2026

Pemilik telah menjalankan paket pengumpulan R02 di Windows dengan PHP 8.2.12 dan MariaDB 10.4.32, pukul 21:15:48–21:17:24 WIB. Hasilnya442 PASS/0 FAIL/0 BLOCKED,22 login fixture,18 kelompok gambar dan tujuh PDF. Satu salinan dipakai untuk screenshot, satu lagi untuk suite regresi; server HTTP keduanya memakai PHP CLI. Ini bukan pengujian lengkap Apache aktif atau laptop kedua.

[Pemeriksaan hasil](BUKTI-T4-2026-11-21.md) mencakup validasi 138 checksum isi, kecocokan 66 source dan empat migrasi dengan GitHub, pemeriksaan 84 PNG/tujuh PDF, invoice dua WO yang benar-benar dilunasi, full export/import identik 26 tabel/38 definisi, dan simulasi kehilangan satu tabel. [Hasil kasus publik](HASIL-UJI-T4-20261121.json) tidak menyertakan konfigurasi, cookie atau dump database.

Satu asumsi alat uji diperbaiki: pemeriksaan katalog kini mencari **kode barang fixture** dan memeriksa sel tabel, sehingga tidak bergantung pada barang muncul di halaman pertama ketika data latihan bertambah. Jumlah kasus tetap 442. Perubahan ini telah dipakai pada run bukti dan diuji dengan/tanpa fixture latihan lama; source website tetap66 berkas R05.

Kriteria build: lint PHP lulus,66 hash source sesuai dan koneksi/objek yang diperlukan tersedia. Kriteria eksekusi suite: nol FAIL/BLOCKED, transaksi sah tersimpan, penolakan sesuai aturan, keutuhan sumber lulus, recovery data/objek identik. Lima skenario minimum dan peta bukti dijelaskan pada [checklist T4](CHECKLIST-T4.md). Pemeriksaan manual tambahan tetap dibedakan dari suite yang sudah PASS.
