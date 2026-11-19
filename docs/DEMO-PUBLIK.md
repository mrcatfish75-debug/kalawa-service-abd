# Demo publik R03

Tujuan: siapa pun dapat mengunduh repository Public dan mencoba aplikasi pada XAMPP lokal, dengan source aplikasi final yang sama dan data fiktif baru.

Schema memuat 26 tabel, 4 procedure, 1 function, 4 trigger dan 3 view: total 38 objek SQL. Definisi diambil dari schema final tanpa menyalin baris data backup. Counter AUTO_INCREMENT lama dilepas agar data baru dimulai bersih. Referensi pelanggan/motor pada procedure debugging tanggal disesuaikan ke identitas demo fiktif.

Data dibuat oleh seed_demo_r03.php: empat cabang, sebelas akun demo, pegawai/mekanik fiktif, lima pelanggan/perusahaan, dua belas motor dan tiga belas WO. Terdapat WO Menunggu/Proses/Selesai/Batal, jasa/barang, mekanik pembantu, stok rendah, pembayaran, garansi serta tagihan korporat satu/lintas cabang. Tanggal mengikuti database saat pemasangan.

Password akun demo adalah 123456781234. Hash dibuat baru saat instalasi. Password SQL service user dibuat acak dan disimpan di luar htdocs. Data/personalia, hash akun dan password koneksi dari backup kelompok tidak disalin ke demo.

Pasang-Demo.ps1 tanpa -Pasang memeriksa berkas dan kesiapan tujuan. Dengan -Pasang, installer membuat database baru, mengimpor definisi, membuat data fiktif, memeriksa view/akun, menerapkan izin aplikasi dan menyalin 66 source. Tujuan yang sudah berisi instalasi Kalawa ditolak.

Jalur demo tidak membutuhkan file backup privat. Bukti backup asli pada docs/BUKTI-BACKUP.md tetap mengacu arsip kelompok untuk pengumpulan tugas. Profil demo dan arsip kelompok berbagi source R05 serta identifier aplikasi, sehingga dipasang sebagai alternatif pada lingkungan kosong.

Lihat README untuk langkah lengkap dan tabel akun.
