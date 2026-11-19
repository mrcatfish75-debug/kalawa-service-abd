# Pemeriksaan cetak setelah pemasangan

- Tekan Ctrl+F5 setelah patch. Pilih cabang/periode, lalu tampilkan laporan sebelum mencetak.
- Periksa empat laporan utama. Judul PDF harus sesuai jenis, dengan periode/cabang yang benar dan alamat mengikuti database.
- Pada stok, bedakan posisi data saat laporan dibuka dengan waktu cetak. Stok yang tepat sama dengan minimum tidak termasuk laporan di bawah minimum.
- Cetak bukti pembayaran LUNAS. Nomor WO, rincian jasa/barang, subtotal, diskon, metode, jumlah dibayar dan referensi penerimaan harus sama dengan layar.
- Cetak tagihan korporat belum lunas dan yang sudah lunas. Judul harus membedakan tagihan dengan bukti pelunasan; nilai per WO dan total tetap sama dengan layar.
- Cetak rincian servis dengan Owner/Auditor dan Gudang. Gudang tetap tidak memperoleh kolom keuangan yang sebelumnya tidak diizinkan.
- Cetak audit dengan pencarian WO dan halaman lanjutan. Tiap tabel harus menjelaskan halaman aktif dan jumlah hasilnya.
- Buka halaman layar kedua laporan riwayat, lalu cetak. Seluruh baris yang dimuat harus tetap tersedia pada PDF.
- Periksa PDF beberapa halaman: judul kolom tabel berulang, footer menyebutkan jenis laporan, halaman bernomor, dan tidak ada baris/kolom terpotong.
- Matikan Header dan footer bawaan Chrome agar URL localhost dan tanggal/judul browser tambahan tidak ikut masuk. Gunakan A4, arah sesuai template, skala Default/100%.
- Jika hasil mencapai batas 500 baris, persempit periode/filter. Jangan menganggap laporan yang dibatasi sudah memuat seluruh riwayat di database.
- Pastikan menu, navigasi HP, form dan tombol transaksi tetap seperti sebelumnya setelah keluar dari dialog cetak.
