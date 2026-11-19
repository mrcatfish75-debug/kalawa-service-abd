# Pengujian

Bukti R05 yang disampaikan kelompok telah menunjukkan **442 PASS, 0 FAIL, 0 BLOCKED**, dengan source 66 berkas. Hasil rinci dan backup recovery disimpan terpisah karena dapat memuat data/kredensial salinan uji.

`docs/VALIDASI-PAKET.md` mempertahankan catatan pemeriksaan R05 sebelumnya, lingkungan pemeriksa dan batas cakupannya. Persiapan GitHub memeriksa kemasan, byte source/migrasi/alat uji serta perilaku checkout Git. Persiapan ini tidak mengulang 442 transaksi aplikasi.

Sebelum commit:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Periksa-Repo.ps1
```

Pemeriksaan mencakup 66 hash, jumlah source, empat migrasi, nama/path berkas privat yang lazim, serta SQL di luar template yang ditinjau. Ini tidak menjamin mendeteksi semua rahasia baru yang mungkin ditambahkan kemudian; tetap baca perubahan sebelum commit.

Untuk website XAMPP R05 yang telah terpasang, nyalakan Apache/MariaDB dan jalankan dari root repository:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Uji-Final-T4.ps1
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Uji-Final-T4.ps1 -Jalankan -HasilRoot D:\KALAWA_HASIL_UJI_GITHUB
```

Tambahkan `-DenganPassword` untuk admin MariaDB berpassword. Hindari transaksi pengguna lain selama snapshot diuji. Suite membuat salinan database/website serta fixture; tidak mereset password akun asli. Hasil disimpan di `D:\KALAWA_HASIL_UJI_GITHUB`, di luar repo.

Cakupan enam role, cabang/penugasan, CRUD, WO, stok, status, pembayaran, korporat, laporan, audit, kasus negatif dan recovery dijelaskan di `uji/CAKUPAN-DAN-ROLE.md`. Hasil lulus memerlukan FAIL=0 dan BLOCKED=0.

Pemeriksaan browser/cetak tetap mengikuti checklist di `docs/`. CLI tidak menggantikan pengujian Apache/browser atau menguji setiap kemungkinan input/perangkat.
