# Checklist Tugas 4

Acuan: instruksi Tugas 4 yang memuat aplikasi, perubahan requirement, debugging, backup/recovery, serta laporan PDF. Dicentang berdasarkan [arsip bukti 21 November 2026](BUKTI-T4-2026-11-21.md), bukan berdasarkan keberadaan tombol saja.

## Aplikasi dan bukti fitur

- [x] Terhubung ke database Kalawa Service lanjutan Tugas 3;66 source final sesuai manifest.
- [x] Login enam role dan pembatasan cabang/penugasan;22 login fixture berhasil.
- [x] CRUD pelanggan, termasuk pemeriksaan data berelasi.
- [x] CRUD motor, identitas unik dan kepemilikan/histori.
- [x] CRUD jasa servis.
- [x] CRUD suku cadang; master dibedakan dari stok cabang.
- [x] CRUD supplier dan hubungan barang pemasok.
- [x] Pembuatan WO baru serta penambahan jasa dan suku cadang.
- [x] Antre/MENUNGGU →PROSES →SELESAI, serta pembatalan sesuai aturan.
- [x] Status dibayar melalui pembayaran/invoice LUNAS, terpisah dari status pekerjaan.
- [x] Pencatatan pembayaran dan bukti cetak dengan rincian/diskon/metode.
- [x] Empat laporan JOIN: riwayat motor, pendapatan cabang/periode, pemakaian barang per WO, stok di bawah minimum.

## Perubahan requirement

- [x] Bukti perusahaan dengan beberapa motor, periode kontrak dan plafon kredit.
- [x] Invoice kolektif minimal dua WO beserta pelunasan invoice yang sama.
- [x] Minimal dua fitur diperbarui: WO korporat serta tagihan/pelunasan kolektif; profil/kontrak turut tersedia.
- [x] Empat script migrasi utama dan penjelasan dampak tabel/relasi/constraint/view/procedure/function/trigger/aplikasi tersedia.
- [x] Bahan pembandingan sebelum/sesudah tersedia melalui baseline, SQL dan bukti final; tidak merekayasa screenshot aplikasi lama.

## Debugging

- [x] Kriteria build dan eksekusi tercatat;49 lint PHP PASS dan66 hash source sesuai.
- [x] Minimal lima skenario diuji: motor kosong, pembayaran dobel, stok kurang, privilege, rentang laporan kosong.
- [x] Hasil aktual442 PASS/0 FAIL/0 BLOCKED disertai JSON/CSV/log.
- [x] Temuan nyata, penyebab dan perbaikan penerimaan lama terdokumentasi.
- [x] Gambar sebelum/sesudah dalam penampil bukti historis berlabel; JSON asli dan asumsi tanggal dipertahankan.

## Backup dan recovery

- [x] Full dump SQL dibuat setelah fixture lengkap dan transaksi invoice pada salinan.
- [x] File backup aktual tersedia privat, dengan lokasi relatif dan SHA-256 terverifikasi.
- [x] Kehilangan data disimulasikan pada tabel contoh/database nonproduksi.
- [x] Recovery satu tabel memulihkan 3 record dengan hash sama.
- [x] Full recovery terpisah memulihkan26 tabel/38 definisi objek dengan data dan definisi identik.
- [x] Operasi/perintah, gambar, hasil verifikasi dan pembersihan salinan tercatat.

## Bahan laporan dan pengumpulan

- [x] 18 kelompok gambar, tujuh PDF cetak, query dan hasil uji tersedia sebagai bahan laporan.
- [x] Source/link repository tersedia.
- [x] Script migrasi requirement tersedia.
- [x] File backup atau bukti lokasi/hash tersedia; data lengkap tetap privat.
- [ ] Gabungkan narasi dan gambar terpilih menjadi **laporan PDF final**, lalu periksa keterbacaan dan konsistensi angka.
- [ ] Serahkan laporan serta source/link, migrasi dan backup/bukti lokasi melalui kanal resmi; bukti pengumpulan belum tersedia pada arsip ini.

**Bukti teknis minimum sudah lengkap.** Dua item terakhir adalah pekerjaan dokumen/pengumpulan, bukan kegagalan fitur. Pemeriksaan manual tambahan pada Apache, printer dan perangkat asli dicatat di [checklist browser](CHECKLIST-BROWSER-DAN-CETAK.md) serta [checklist cetak](CHECKLIST-CETAK-R05.md); jangan mengubahnya menjadi PASS tanpa bukti.
