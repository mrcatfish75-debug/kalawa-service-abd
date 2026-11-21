# Catatan perubahan requirement untuk penulisan laporan

Ini panduan membaca source/migrasi final R05, bukan bukti eksekusi migrasi ulang. Kondisi sebelum harus dirujuk ke desain Tugas 3; screenshot versi lama tidak dibuat ulang.

| Aspek | Baseline individu | Kebutuhan/implementasi final |
| --- | --- | --- |
| Pelanggan | Identitas pelanggan dan kepemilikan motor | Profil perusahaan sebagai perluasan pelanggan; satu perusahaan tetap dapat memiliki banyak motor melalui relasi kepemilikan |
| Kontrak | Transaksi servis per WO | Periode kontrak dan plafon kredit melalui `kontrak_korporat`; WO korporat ditautkan ke kontrak/pelanggan |
| Penagihan | Pembayaran individual per WO | Header tagihan bulanan dan detail beberapa WO; nilai final dibekukan dalam detail invoice |
| Pelunasan | Satu WO dibayar individual | Invoice kolektif dilunasi sekali; sumber penerimaan harus dibedakan agar tidak dihitung ganda |
| Laporan/audit | Riwayat dan pembayaran individual | Query/laporan mencakup penerimaan individual serta korporat; view terpadu menampilkan status efektif keduanya |
| Garansi | Status terkait penyelesaian servis | Fungsi garansi membaca pembayaran efektif individual atau invoice, serta status selesai WO |

## Dampak database dan aplikasi

- **Tabel:** migrasi001 menambah `pelanggan_korporat`, `kontrak_korporat`, `work_order_korporat`, `tagihan_korporat`, `detail_tagihan_korporat`. Migrasi002 menambah `penerimaan_kas`.
- **Relasi:** profil perusahaan → pelanggan; kontrak → profil; WO korporat → WO dan kontrak pelanggan yang sama; detail tagihan → WO korporat dan header pelanggan yang sama. Kepemilikan motor yang sudah ada dipakai kembali.
- **Constraint:** foreign key komposit mencegah pencampuran pelanggan, unique WO mencegah satu WO masuk dua tagihan, unique perusahaan/periode mencegah tagihan bulan ganda, CHECK membatasi periode/plafon/nilai serta konsistensi status pelunasan. Penerimaan memiliki satu target WO atau invoice dan unique masing-masing referensi.
- **View:** migrasi004 menambah `vw_ringkasan_transaksi_terpadu`. View baseline tetap tersedia; penambahan view terpadu tidak berarti semua view lama telah ditulis ulang.
- **Function:** migrasi003 memperbarui `fn_status_garansi_wo` agar status garansi mengikuti status pekerjaan dan pembayaran efektif, termasuk invoice korporat.
- **Stored procedure:** empat migrasi ini tidak membuat procedure korporat baru. Jelaskan dampak dan kecukupan procedure baseline dari definisi aktual dalam dump; proses penerbitan/pelunasan invoice dijalankan layanan PHP dalam transaksi. Jangan mengklaim seluruh operasi aplikasi memakai stored procedure baru.
- **Trigger:** trigger validasi tanggal WO, pemakaian stok dan audit perubahan baseline tetap relevan. Proses korporat harus tetap menjaga jalur pemakaian barang dan jejak perubahan; definisi aktif tercakup dump serta verifikasi full restore. Empat migrasi ini tidak menambah trigger korporat baru.
- **Aplikasi:** fitur profil/kontrak korporat, pembuatan WO dengan kontrak dan plafon, pratinjau/penerbitan invoice bulanan serta pelunasan kolektif, laporan penerimaan dan view audit terpadu. Minimal dua fitur yang dapat dibahas: WO korporat dan tagihan/pelunasan kolektif; lengkapi dengan kontrak/armada sebagai konteks.

## Cara menulis bagian sebelum dan sesudah

Gunakan tabel perubahan kebutuhan di atas, potongan SQL001/002, penjelasan fungsi003/view004, serta gambar kontrak/armada dan invoice dua WO. Untuk status final, gunakan query/JSON yang baru dihasilkan paket. Untuk P0 penerimaan historis, gunakan JSON asli dan migrasi data 005 di folder privat `04-debugging/historis`; labeli terpisah dari migrasi struktur dan snapshot pengujian baru.

Tambahkan ke laporan link source <https://github.com/mrcatfish75-debug/kalawa-service-abd>, nama empat script migrasi, dan lokasi/hash backup hasil run. Ringkasan hasil dan status dokumentasi diperbarui di repository; SQL/JSON mentah tetap privat.

Objek aktif yang terverifikasi:26 tabel, 4 procedure (`sp_bukti_trigger_tanggal`, `sp_daftar_wo_cabang`, `sp_rincian_tagihan_wo`, `sp_riwayat_servis_motor`), 1 function, 4 trigger dan 3 view. Empat procedure baseline tetap ada; tidak diklaim sebagai procedure korporat baru. Lihat [riwayat migrasi](RIWAYAT-MIGRASI.md) dan [bukti baru](BUKTI-T4-2026-11-21.md).
