# Checklist cetak R05

Status berdasarkan [arsip 21 November 2026](BUKTI-T4-2026-11-21.md). Tujuh PDF dibuka, teks/judulnya diperiksa dan halamannya dirender untuk inspeksi visual. Ketujuh sampel adalah dokumen **satu halaman**.

## Dokumen aktual yang sudah diperiksa

- [x] Empat laporan JOIN memiliki judul khusus, periode/konteks, cabang/alamat, logo dan waktu cetak.
- [x] Bukti pembayaran LUNAS memiliki WO, jasa/barang, subtotal Rp120.000, diskon Rp5.000, QRIS, total Rp115.000 dan referensi penerimaan.
- [x] Invoice20 sebelum/sesudah memakai judul berbeda: Tagihan Servis Korporat dan Bukti Pelunasan Korporat; dua WO dan total Rp150.000 tetap sama.
- [x] Tabel pada tujuh sampel terbaca dan tidak terpotong; navigasi/form transaksi tidak ikut PDF.
- [x] Footer menyebut jenis dokumen dan halaman1 dari1; tidak ada header/footer browser tambahan.
- [x] Laporan stok membedakan waktu posisi data dari waktu cetak dan memuat stok yang **lebih kecil** dari minimum.

## Pemeriksaan otomatis HTML/source yang turut PASS

- [x] Rincian Gudang tidak memuat harga; rincian servis/audit memiliki konteks cetak dan penolakan akses tetap berlaku.
- [x] Kasus data dari halaman layar kedua tetap tersedia untuk cetak seluruh baris yang dimuat.
- [x] Menyiapkan dokumen cetak tidak memutasi tabel; metadata filter di-escape.

Bagian otomatis di atas tidak dinyatakan sebagai PDF tambahan yang sudah dirender. Bukti ada pada442 kasus JSON.

## Tambahan manual yang belum ada buktinya

- [ ] Render dataset yang menghasilkan PDF beberapa halaman untuk memeriksa pengulangan judul kolom, nomor halaman dan pemotongan baris lintas halaman.
- [ ] Cetak fisik pada printer; periksa dialog cetak dan penggunaan website setelah dialog ditutup.
- [ ] Cetak rincian servis/audit sebagai PDF tambahan bila dibutuhkan untuk pembahasan di luar tujuh contoh minimum.

Untuk cetak manual gunakan A4, orientasi sesuai template, skala Default/100%, dan matikan header/footer bawaan browser. Jika mencapai batas 500 baris, persempit filter; jangan menyatakan seluruh database telah tercetak.
