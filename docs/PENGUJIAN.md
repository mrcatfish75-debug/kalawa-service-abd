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

Bukti pada paket final R05 menunjukkan 442 PASS, 0 FAIL, 0 BLOCKED. Bukti restore full export/import mencakup 26 tabel dan 38 objek SQL dengan baris/hash identik. Persiapan GitHub tidak mengulang 442 transaksi atau menjalankan ulang restore database.

Catatan validasi patch sebelumnya dipertahankan pada VALIDASI-PAKET.md. Browser/cetak tetap mengikuti checklist; CLI tidak menguji setiap input, perangkat atau printer fisik.
