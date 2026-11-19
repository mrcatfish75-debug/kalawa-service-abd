# Cakupan tugas dan enam role

Matriks ini mengikuti pembagian akses aplikasi final T4 dan UI R04 dari source yang ditinjau dan kebutuhan kelompok. Tidak semua role harus memiliki semua menu. Pembatasan pembayaran, master, dan perubahan status merupakan bagian dari pembagian tugas.

| Role | Tugas pada aplikasi | Batas yang diuji |
|---|---|---|
| Owner | Ringkasan seluruh cabang; lima master CRUD; pendaftaran; WO, jasa, barang dan mekanik pembantu; pembayaran; korporat/kontrak/tagihan; stok; katalog; empat laporan; audit | Tindakan tetap mengikuti status WO, token transaksi, stok, kontrak dan plafon; bukan bebas menimpa transaksi |
| Admin Cabang | Ringkasan cabang; pendaftaran pelanggan/motor; WO cabang, jasa, barang, mekanik; status/cancel sesuai aturan; empat laporan cabang; katalog | Tidak mengubah master global, pembayaran, kontrak/tagihan atau stok manual; cabang lain ditolak |
| Kasir | WO individual siap dibayar, rincian jasa/barang/harga/qty/diskon/metode; pelunasan; bukti; tagihan korporat yang seluruh WO-nya dalam cabang kasir; empat laporan cabang; katalog | Bukan editor WO atau master; tidak membayar WO belum selesai; tagihan lintas cabang ditangani Owner |
| Mekanik | Pekerjaan yang ditugaskan; rincian jasa/barang dan pembantu; mekanik utama menjalankan Menunggu → Proses → Selesai | Pembantu membaca; tidak memulai/menyelesaikan WO; tanpa penugasan/cabang lain ditolak; tidak melihat harga atau membatalkan WO |
| Petugas Gudang | Stok awal/masuk/koreksi/minimum cabang, riwayat mutasi, katalog/supplier, laporan pemakaian dan stok minimum | Tidak mengubah WO/pembayaran/master; laporan riwayat pelanggan dan pendapatan ditolak; cabang lain ditolak |
| Auditor | Empat laporan seluruh cabang, rincian servis termasuk item/pembayaran/garansi, jejak perubahan dan penerimaan kas | Semua tampilan baca; transaksi dan perubahan master ditolak |

## Kebutuhan Tugas 1–3 yang diteruskan ke aplikasi Tugas 4

| Kebutuhan | Bukti otomatis dalam paket |
|---|---|
| Pelanggan, motor, riwayat kepemilikan | Tambah/ubah/hapus yang sah, pencarian, duplikasi identitas/mesin, satu pemilik aktif, perpindahan pemilik dan perlindungan histori |
| Lima master CRUD | Pelanggan, motor, jasa, suku cadang, supplier; hubungan supplier–barang serta perlindungan data terpakai |
| Pendaftaran | Pelanggan baru + motor + kepemilikan atomik; motor pelanggan terdaftar; motor duplikat tidak meninggalkan pelanggan baru |
| WO per cabang | Pembuatan, validasi, rincian, penolakan cabang lain melalui GET dan POST |
| Jasa dan suku cadang | Tambah/ubah/hapus jasa; pemakaian barang; stok tidak cukup; pengurangan stok tepat; replay tidak memotong stok ulang |
| Mekanik utama/pembantu | Penugasan/penghapusan pembantu, penolakan mekanik beda cabang, pembantu baca, utama ubah status, tanpa penugasan ditolak |
| Status WO | Urutan status, larangan lompatan, tanggal selesai, dua jejak transisi, pembatalan sah dan larangan pembatalan setelah pemakaian barang |
| Pembayaran individual | Tunai, transfer, QRIS, kartu debit; diskon; jumlah tepat; WO belum selesai ditolak; CSRF; biaya kedaluwarsa; pencatatan kas dan replay |
| Korporat | Data perusahaan, kontrak, overlap, plafon individual/kumulatif lintas cabang, WO terkait kontrak, tagihan periode tertutup, pelunasan cabang/campuran |
| Stok | Awal, masuk, koreksi, minimum, riwayat pencatat, angka negatif, snapshot kedaluwarsa, replay, cakupan cabang |
| Empat laporan | Riwayat motor, pendapatan cabang, pemakaian barang per WO, stok minimum; filter/rentang; hasil kosong; penolakan filter lintas cabang; kecocokan total pendapatan |
| Rincian dan garansi | Kode/nama item/harga, rincian baca, garansi belum lunas/aktif/kedaluwarsa/tanpa garansi; UI dan fungsi SQL diperiksa terpisah |
| Objek Tugas 3 | Tiga procedure benar-benar dipanggil; fungsi garansi dieksekusi; trigger tanggal/stok/status/pembayaran teruji melalui dampak transaksi dan audit |
| Keamanan aplikasi | Login/sesi, password salah, pembatasan percobaan, CSRF, logout, matriks GET/POST enam role, pembatasan cabang/penugasan |
| Backup/recovery | Backup SQL sumber, hash salinan awal, ekspor/hapus/restore SQL tabel simulasi, pemeriksaan keutuhan file/data sumber sesudah uji |

Ada lebih dari 360 pemeriksaan pada konfigurasi lengkap. Jumlah dapat lebih sedikit jika prasyarat gagal. Kesimpulan harus melihat FAIL/BLOCKED dan batas cakupan, bukan jumlah PASS saja.

Skenario bulan tertutup memerlukan data historis; fixture historis disisipkan langsung oleh alat hanya pada database salinan. Penerbitan tagihan dan pelunasan tetap dijalankan melalui HTTP aplikasi. Beberapa fixture garansi/tanggal/konflik juga dipasang melalui SQL salinan untuk memicu kondisi yang sulit dibuat lewat UI.

## Pemeriksaan browser yang tetap perlu dilakukan

Gunakan akun asli yang sudah Anda uji di situs XAMPP; jangan mengubah transaksi yang sudah ada hanya untuk tangkapan layar. Pilih data contoh yang memang boleh digunakan.

| Pemeriksaan | Langkah dan hasil yang diharapkan |
|---|---|
| Owner | Buka semua master, WO dan kontrak; label/form jelas; ringkasan seluruh cabang; navigasi tidak terpotong |
| Admin Cabang | Buka pendaftaran, WO dan rincian jasa/barang; cabang sesuai penempatan; panduan menjelaskan urutan pekerjaan |
| Kasir | Buka rincian WO dan bukti pembayaran; jasa dan barang dapat dibedakan; qty, harga, diskon, total dan metode terbaca |
| Mekanik | Buka pekerjaan utama/pembantu; rincian pekerjaan mudah dipahami; tombol status sesuai penugasan dan kondisi |
| Gudang | Cari barang; baca kondisi stok; buka form mutasi; validasi dan pesan hasil mudah dipahami |
| Auditor | Buka empat laporan dan jejak audit; telusuri rincian servis; filter dan isi laporan sesuai kebutuhan |
| Cetak | Buka pratinjau cetak laporan, rincian servis dan bukti; judul, periode, cabang, tabel dan total tidak terpotong; menu/tombol tidak tercetak |
| Layar kecil | Coba lebar sekitar 390 px dan laptop 1366 px; menu dapat diakses; tabel dapat digeser; tombol/form tidak bertumpuk |
| Keyboard | Navigasi Tab, indikator fokus, label input, validasi, dan tombol submit dapat dipakai tanpa mouse |
| JavaScript | Uji konfirmasi hapus dan pencegahan klik ganda di browser; cek Console jika ada error |
| Apache | Situs `http://localhost/kalawa-service-f1-korporat-uji/` tetap membuka login, aset, halaman dan cetak; PHP CLI sementara tidak menggantikan pemeriksaan ini |

Daftar ini memisahkan bukti otomatis dan bukti manual. Paket tidak mengklaim telah menguji setiap kemungkinan input, persaingan transaksi paralel yang sesungguhnya, semua kombinasi browser, pemulihan server produksi penuh, atau seluruh dokumen/presentasi/video tugas. Pembatasan SQL role/user MariaDB perlu bukti administrasi tersendiri jika diminta dosen; aplikasi memakai satu akun koneksi dengan RBAC backend.
