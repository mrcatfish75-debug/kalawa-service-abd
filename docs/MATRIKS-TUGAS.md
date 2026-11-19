# Matriks kesesuaian

| Acuan | Implementasi/bukti |
|---|---|
| T1: bengkel multi-cabang | Schema cabang/pegawai/pelanggan/motor/WO/stok/supplier dipertahankan |
| T2: aturan bisnis | Pemilik aktif/histori, mekanik utama/pembantu, jasa/barang, empat metode bayar, garansi selesai dan lunas |
| T3: database fisik | PK/FK/CHECK, view baseline, procedure/function/trigger ikut dump; role/user SQL global memerlukan arsip administrasi terpisah |
| T4: role | Enam role aplikasi dan pembatasan server-side |
| T4: CRUD | Pelanggan, motor, jasa, suku cadang, supplier |
| T4: transaksi | WO, rincian jasa/barang, urutan status, pembayaran dan penerimaan |
| T4: laporan JOIN | Riwayat motor, pendapatan cabang/tanggal, pemakaian barang, stok minimum |
| T4: korporat | Kontrak/plafon/WO dan tagihan kolektif: lebih dari dua fitur berubah |
| T4: debugging | Lint PHP, pemeriksaan hash, pengujian HTTP/SQL, kasus negatif, hasil JSON/CSV |
| T4: backup/recovery | Dump final, impor ke DB baru, verifikasi data/objek; simulasi kehilangan dan restore |
| Pengumpulan | Source, migrasi, backup, hasil uji; laporan/video/logbook/identitas dilengkapi kelompok |

Dibayar adalah status finansial LUNAS, terpisah dari progres SELESAI. Garansi memerlukan keduanya.

Build lulus bila PHP lolos lint, 66 hash source sesuai, konfigurasi/objek/izin tersedia. Eksekusi lulus bila semua skenario PASS, keutuhan sumber lulus, restore SQL identik, dan checklist browser/cetak selesai diperiksa. Jumlah PASS tidak membuktikan kelengkapan video/logbook.

Form pencatatan garansi dan peminjaman mekanik antarcabang adalah pengembangan skenario lanjutan, bukan minimum eksplisit T4; paket finalisasi ini tidak menambah kedua alur itu.
