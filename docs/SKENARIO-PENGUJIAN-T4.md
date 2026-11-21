# Lima skenario minimum dan hasil CRUD

Hasil aktual berasal dari eksekusi21 November 2026 pada salinan regresi, source CETAK_R05. Nama kasus dapat dicari pada [442 hasil uji](HASIL-UJI-T4-20261121.json). Status PASS di bawah memiliki asersi HTTP/SQL; penolakan yang sesuai aturan merupakan keberhasilan pengujian.

| ID | Input/tindakan | Hasil yang diharapkan | Hasil aktual yang diasersi | Kesimpulan |
| --- | --- | --- | --- | --- |
| U01 | Admin membuat WO dengan pelanggan/mekanik valid tetapi nomor polisi kosong | WO tidak disimpan | Jumlah WO sebelum/sesudah tetap sama; kasus `WO tanpa motor ditolak` PASS | PASS |
| U02 | Kasir membayar biaya Rp25.000, diskon Rp500, lalu mengirim ulang form/token yang sama | Bayar Rp24.500 satu kali; kiriman ganda ditolak | Total/metode tepat, satu penerimaan; kiriman kedua HTTP 409 dan penerimaan tetap satu. Empat metode lulus | PASS |
| U03 | Admin meminta barang qty 9999 saat persediaan tidak cukup | Penolakan 409/422 tanpa mutasi stok | Status berada dalam 409/422 dan hash seluruh tabel stok tidak berubah | PASS |
| U04 | Mekanik membuka `panel/work_order.php` secara langsung | Akses pengelolaan admin ditolak | HTTP 403 pada `tech1 GET work_order.php`; screenshot403 tersedia | PASS |
| U05 | Auditor membuka riwayat 1–2 Januari 1900 | Hasil kosong yang jelas tanpa error PHP | HTTP 200 dan pesan `Tidak ada data sesuai filter`; screenshot tersedia | PASS |

Status 409/422 pada U03 ditulis sebagai rentang hasil yang diperiksa suite, karena log ringkas tidak merekam pilihan status tepat dari kedua kemungkinan itu. Jangan mengarang detail respons yang tidak dicatat.

## Ringkasan lima master

| Master | Tambah | Baca | Ubah | Hapus | Catatan |
| --- | --- | --- | --- | --- | --- |
| Pelanggan | PASS | PASS | PASS | PASS data tanpa relasi | Pendaftaran/identitas dan pemilik motor turut diuji |
| Motor | PASS | PASS | PASS | PASS data tanpa relasi | Duplikat rangka/mesin ditolak; motor dengan histori tetap dilindungi |
| Jasa servis | PASS | PASS/cari | PASS | PASS data bebas | Jasa terpakai tidak dihapus |
| Suku cadang | PASS | PASS/cari | PASS | PASS data bebas | Stok cabang/pemakaian dipisahkan dari master |
| Supplier | PASS | PASS/cari | PASS | PASS data bebas | Supplier pemasok barang dilindungi; hubungan barang diuji |

Transaksi CRUD di atas berasal dari suite. Screenshot daftar/form berasal dari salinan capture dengan fixture terpisah; tombol tambah/ubah/hapus sendiri tidak dianggap bukti operasi berhasil.

## Kriteria lulus

- **Build:**49 file PHP lolos lint,66 hash source sesuai manifest, koneksi dan objek yang diperlukan tersedia.
- **Eksekusi:**442 PASS/0 FAIL/0 BLOCKED, transaksi sah tersimpan, penolakan sesuai aturan tanpa mutasi yang diperiksa, keutuhan sumber lulus, full restore data/definisi identik.

## Kasus debugging yang ditemukan dan diperbaiki

Lima pembayaran contoh lama sudah LUNAS tetapi belum mempunyai penerimaan kas. Tabel penerimaan baru belum diisi untuk pembayaran lama, sehingga laporan berbasis penerimaan dapat melewatkan Rp1.155.000. Rekonsiliasi melalui runner tervalidasi menambah lima penerimaan dan lima audit rekonstruksi.

| Ukuran snapshot historis | Sebelum | Sesudah |
| --- | --- | --- |
| LUNAS individual tanpa penerimaan | 5 WO/Rp1.155.000 | 0 WO/Rp0 |
| Record penerimaan kas | 20 | 25 |
| Record log audit | 60 | 65 |

Waktu penerapan asli21 November 2026 pukul 14:56:48 WIB. Tanggal bayar asli dan kasir historis tidak diketahui; tanggal selesai WO adalah asumsi skenario. Penampil gambar 16 melabeli bukti historis dan sumber JSON asli. Angka di atas tidak dicampur dengan source 83 WO atau salinan capture 129 WO pada malam hari.

Tidak diwajibkan lima bug berbeda. Lima skenario minimum di atas adalah pengujian, sedangkan rekonsiliasi merupakan temuan/perbaikan nyata.
