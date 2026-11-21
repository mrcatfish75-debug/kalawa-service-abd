# Checklist browser dan bukti aplikasi

Pembaruan berdasarkan [bukti 21 November 2026](BUKTI-T4-2026-11-21.md). PASS di bawah menyatakan pemeriksaan pada salinan dengan server PHP CLI; tidak diubah menjadi klaim eksekusi Apache aktif.

| Pemeriksaan | Status | Bukti |
| --- | --- | --- |
| Login enam role, menu/cakupan/penugasan | [x] PASS pada salinan | 22 login HTTP; tujuh sesi screenshot; kasus RBAC |
| Lima CRUD pada data contoh | [x] PASS HTTP/SQL + tampilan | Kasus CRUD dan gambar 02–06 |
| Pendaftaran, WO, jasa dan barang | [x] PASS HTTP/SQL + tampilan | Gambar 07 dan variannya |
| Mekanik utama mengubah status; pembantu membaca | [x] PASS HTTP/SQL + tampilan penugasan | Kasus penugasan/status; varian role |
| Kasir, pembayaran dan bukti cetak | [x] PASS transaksi + PDF | Gambar 08 dan PDF bukti |
| Gudang, mutasi dan minimum stok | [x] PASS HTTP/SQL + tampilan | Kasus stok; role gudang; laporan 12 |
| Korporat, armada, kontrak, plafon, invoice dan pelunasan | [x] PASS | Gambar 13–14; invoice 20 berisi dua WO |
| Auditor dan pemisahan individual/korporat | [x] PASS HTTP/SQL + laporan | Kasus audit; laporan 09–12 |
| Empat laporan dan filter | [x] PASS data + render PDF contoh | Empat PDF; angka sesuai snapshot sebelum pelunasan invoice |
| Tampilan laptop dan viewport 390 px | [x] Tersedia | Screenshot desktop dan tambahan ponsel; bukan perangkat asli |

## Pemeriksaan tambahan yang belum dibuktikan arsip ini

- [ ] Ulangi seluruh alur pada Apache aktif jika ingin menyatakan verifikasi Apache manual lengkap.
- [ ] Keyboard Tab/fokus, interaksi menu mobile dan validasi secara manual pada perangkat asli.
- [ ] Pemasangan baru pada laptop/XAMPP teman yang terpisah. Demo publik sudah punya uji terisolasi; itu bukan bukti laptop kedua.

Item tambahan ini tidak dipakai untuk membatalkan hasil442 kasus atau menyatakan requirement perangkat yang tidak disebut dalam Tugas 4. Untuk laporan, tulis lingkungan dan cakupan yang benar. Gunakan bulan layanan yang telah berakhir untuk invoice; jangan mengubah tanggal server/transaksi kerja demi membuat bukti.
