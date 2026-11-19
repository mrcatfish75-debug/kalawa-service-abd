# Validasi Template Cetak R05 - 17 November 2026

Baseline adalah source UI R04 dari `KALAWA_T4_SIAP_PASANG(1).zip` yang dilampirkan pengguna. Hash seluruh isi paket dasar diperiksa sebelum dipakai. Logo SVG yang dilampirkan identik dengan logo source; aset yang sama digunakan pada kop tanpa mengubah gambar.

| Pemeriksaan | Hasil |
|---|---|
| Source sebelum patch | PASS; 66 path diperiksa, 9 path berubah (3 file baru) |
| Dry-run, penerapan, penerapan ulang | PASS |
| Uji aplikasi/SQL enam role | **442 PASS, 0 FAIL, 0 BLOCKED** |
| Source/config/data/objek SQL sumber setelah pengujian | PASS; sesuai snapshot |
| Builder paket penuh dan restore dump sementara | PASS; 26 tabel dan 38 objek identik |
| Pemasangan baru ke instance database terpisah | PASS |
| Pengujian setelah pemasangan baru | **442 PASS, 0 FAIL, 0 BLOCKED** |
| Pemulihan source dan penolakan source yang diedit lagi | PASS |
| Pengujian browser cetak | **143 pemeriksaan PASS** |
| Variasi PDF yang dihasilkan dan diperiksa | 15 |
| Title metadata PDF terhadap judul dokumen | PASS |
| Arah kertas tiap PDF | PASS; A4 portrait/landscape sesuai jenis |
| Footer nomor halaman pada seluruh halaman PDF | PASS |
| Pemeriksaan koordinat teks terhadap batas halaman | PASS |
| Visual halaman pertama dan lanjutan | Diperiksa setelah penyesuaian layout |
| Bukti singkat dan rincian singkat fixture | Satu halaman tanpa referensi/garansi yang tertinggal sendiri |

442 kasus terdiri dari seluruh 419 kasus R04 sebelumnya ditambah 23 pemeriksaan cetak: kop, metadata, judul empat laporan yang berbeda, alamat/cabang sesuai role, waktu/posisi stok, hasil kosong/invalid, judul berdasarkan status pembayaran, rincian Gudang tanpa keuangan, pencarian audit, metadata yang di-escape, dan keutuhan data. Tidak ada pengujian lama yang dihapus.

Variasi PDF: riwayat seluruh cabang dan satu cabang, penerimaan pembayaran, pemakaian suku cadang, stok, bukti pembayaran, rincian tagihan individual belum lunas, tagihan korporat belum lunas, bukti pelunasan korporat, rincian servis Owner, rincian Gudang, audit beberapa halaman, laporan kosong, serta riwayat dan bukti pembayaran dari viewport HP 390 px. PDF riwayat dan audit beberapa halaman diperiksa agar judul tabel berulang dan footer tetap tepat.

Lingkungan: PHP 8.4.24, MariaDB 10.4.32, PowerShell 7.4.19 dan Chromium 156 pada Linux terisolasi. Telemetri PowerShell dimatikan pada harness pengujian. Pengujian pemasangan ulang memakai instance database dengan port/direktori data terpisah; bukan dua laptop fisik. Skrip peluncur memakai sintaks Windows PowerShell 5.1. Eksekusi Windows/XAMPP pengguna tetap diverifikasi setelah pemasangan.

PDF dibuat memakai stylesheet cetak website dan mesin cetak Chromium, bukan tiruan yang digambar terpisah. Pengaturan header/footer bawaan browser dimatikan selama pemeriksaan PDF; footer dokumen berasal dari CSS website. Dukungan browser lain atau printer fisik dapat berbeda. Tidak ada koneksi ke localhost Windows pengguna pada pengujian pembuat.

Batas laporan 500 baris, format data/hasil query lama dan pagination audit per halaman aktif tetap dipertahankan. Validasi ini tidak mengklaim seluruh kemungkinan data/perangkat/input telah diuji. Backup asli tidak dimasukkan ke ZIP patch; paket siap pasang hasil builder dapat memuat data asli dan dikelola terpisah.
