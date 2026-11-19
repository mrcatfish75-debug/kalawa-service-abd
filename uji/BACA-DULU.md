# Uji Kalawa Cetak R05

Jalankan `Uji-Final-T4.ps1 -Jalankan` dari folder induk paket. Petunjuk pemasangan, pemulihan dan cetak berada di `../BACA-DULU.md`.

Manifest memeriksa 66 file. Cakupan pengujian mencakup seluruh 419 pemeriksaan UI R04 sebelumnya, ditambah 23 pemeriksaan kop, konteks dokumen, judul, cakupan alamat cabang, status pembayaran, pembatasan data Gudang, metadata yang di-escape dan keutuhan tabel. Target: **442 PASS, 0 FAIL, 0 BLOCKED**.

Skenario tagihan belum lunas memakai fixture tagihan nyata yang dibuat di salinan uji bila belum tersedia; tidak memerlukan data belum lunas pada website asli. Semua akun/record fixture dibuat di salinan terisolasi. Password dan data akun asli tidak direset.

Uji otomatis ini memeriksa HTTP/SQL/markup; bukan pengganti pemeriksaan pratinjau cetak di Chrome Windows pengguna. Pengujian PDF/browser pembuat paket dijelaskan di `../docs/VALIDASI-PAKET.md`.

Alat uji/builder lama memakai hash R04 dan akan menolak source cetak yang diperbarui. Gunakan alat di paket R05. Utilitas perbaikan SQL historis hanya untuk diagnosis khusus; polish cetak ini tidak membutuhkan migrasi atau GRANT baru.
