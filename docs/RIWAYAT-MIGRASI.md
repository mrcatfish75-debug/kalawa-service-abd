# Riwayat perubahan requirement

| Urutan | Skrip | Perubahan |
|---|---|---|
| 001 | `001_korporat.sql` | Profil korporat, kontrak, hubungan WO, tagihan bulanan dan detail tagihan |
| 002 | `002_penerimaan_kas.sql` | Tabel penerimaan kas dan pengisian dari pembayaran baseline |
| 003 | `003_garansi_lunas.sql` | Penyesuaian fungsi garansi: pekerjaan selesai dan pembayaran lunas |
| 004 | `004_audit_terpadu.sql` | View audit transaksi terpadu |

Skrip di `sql/` identik dengan paket final. Semuanya sudah diterapkan pada database final 26 tabel. Ini migrasi historis, bukan installer database kosong dan tidak semuanya idempoten. Jangan menjalankan ulang pada database final.

Skrip menyebut identifier database yang dikunci aplikasi R05. Pembelajaran migrasi dari baseline memerlukan salinan terisolasi, baseline yang sesuai serta runner yang benar.

Skrip tambahan di `uji/sql/` dan template izin di `uji/tools/` adalah pendukung pengujian. Keberadaannya tidak berarti perlu dijalankan pada database sumber setiap kali uji.
