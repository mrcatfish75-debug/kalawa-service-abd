# Riwayat perubahan requirement

| Urutan | Skrip | Perubahan |
|---|---|---|
| 001 | `001_korporat.sql` | Profil korporat, kontrak, hubungan WO, tagihan bulanan dan detail tagihan |
| 002 | `002_penerimaan_kas.sql` | Membuat tabel penerimaan kas beserta constraint; tidak melakukan backfill seluruh pembayaran lama |
| 003 | `003_garansi_lunas.sql` | Penyesuaian fungsi garansi: pekerjaan selesai dan pembayaran lunas |
| 004 | `004_audit_terpadu.sql` | View audit transaksi terpadu |

Skrip di `sql/` identik dengan paket final. Semuanya sudah diterapkan pada database final 26 tabel. Ini migrasi historis, bukan installer database kosong dan tidak semuanya idempoten. Jangan menjalankan ulang pada database final.

Skrip menyebut identifier database yang dikunci aplikasi R05. Pembelajaran migrasi dari baseline memerlukan salinan terisolasi, baseline yang sesuai serta runner yang benar.

Skrip tambahan di `uji/sql/` dan template izin di `uji/tools/` adalah pendukung pengujian. Keberadaannya tidak berarti perlu dijalankan pada database sumber setiap kali uji.

## Rekonsiliasi data historis, 21 November 2026

Setelah struktur final dipakai, ditemukan lima pembayaran contoh individual berstatus LUNAS tanpa penerimaan kas, total Rp1.155.000. Rekonsiliasi R01 telah menambah lima penerimaan dan lima audit `REKONSTRUKSI`; hasil pemeriksaan sisa menjadi nol. Ini migrasi **data** pada snapshot contoh, terpisah dari empat migrasi utama di atas.

[Salinan SQL005 untuk pembahasan laporan](contoh/005_rekonsiliasi_penerimaan_contoh.sql.txt) dipertahankan sebagai referensi kejadian, bukan installer atau migrasi otomatis. Eksekusi aslinya menggunakan runner privat yang memeriksa rencana lima WO, operator, snapshot, transaksi dan hasil uji. Jangan mengeksekusi file referensi itu langsung pada database final/demo.

Tanggal pembayaran asli dan identitas kasir historis tidak diketahui. Tanggal selesai WO dipakai sebagai **asumsi skenario data contoh**, dan operator rekonstruksi tidak dinyatakan sebagai kasir asli. Bukti JSON asli tetap di arsip privat; [ringkasan publik](BUKTI-T4-2026-11-21.md) membedakan waktu historis dari pengujian baru.
