<p align="center"><img src="website/logo/kalawa-service-logo.png" alt="Logo Kalawa Service" width="140"></p>

# Kalawa Service

Sistem operasional bengkel multi-cabang untuk proyek **Administrasi Basis Data — Tugas 4**, melanjutkan database dan aturan bisnis Tugas 1–3.

**Aplikasi final CETAK_R05 · 66 berkas source · Demo publik R03**

**Pemeriksaan bukti terbaru: 21 November 2026, 21:15–21:17 WIB.** Pengumpulan pada salinan Windows/XAMPP menghasilkan **442 PASS, 0 FAIL, 0 BLOCKED**, 22 login, 18 kelompok gambar (84 PNG beserta varian), tujuh PDF, full restore identik serta simulasi kehilangan/pemulihan. Source aplikasi tetap sama dengan CETAK_R05. Lihat [hasil pemeriksaan](docs/BUKTI-T4-2026-11-21.md), [checklist Tugas 4](docs/CHECKLIST-T4.md), dan [ringkasan JSON](docs/BUKTI-T4-20261121.json).

Bukti teknis untuk laporan telah tersedia. Penyusunan, pemeriksaan akhir dan pengumpulan **laporan PDF** tetap dicatat terpisah; paket bukti bukan laporan yang sudah dikumpulkan. Arsip screenshot/SQL lengkap disimpan privat.

Repository menyediakan source final yang sama dengan website kelompok serta schema dan data contoh fiktif untuk mencoba aplikasi pada laptop lain. Demo dapat dipasang dari Download ZIP atau clone, tanpa memperoleh backup privat kelompok.

## Mulai cepat — Windows dan XAMPP

### 1. Siapkan XAMPP

Baseline kelompok: **XAMPP 8.2.12, PHP 8.2.12, MariaDB 10.4.32**. Demo juga telah diuji pada PHP 8.4.27 dengan MariaDB 10.4.32. Unduh baseline melalui [arsip resmi XAMPP](https://sourceforge.net/projects/xampp/files/XAMPP%20Windows/8.2.12/) atau lihat [Apache Friends](https://www.apachefriends.org/download.html).

Aktifkan **Apache** dan **MySQL/MariaDB** melalui XAMPP Control Panel. Contoh lokasi adalah `C:\xampp`. PDO MySQL harus tersedia dan folder htdocs dapat ditulis.

### 2. Download atau clone

Pilihan Download ZIP tidak memerlukan Git:

1. Buka [repository](https://github.com/mrcatfish75-debug/kalawa-service-abd).
2. Pilih **Code → Download ZIP**.
3. Ekstrak ke D: sehingga tersedia `D:\kalawa-service-abd-main\Pasang-Demo.ps1`.

Alternatif melalui Git:

```powershell
Set-Location D:\
git clone https://github.com/mrcatfish75-debug/kalawa-service-abd.git
```

Jika memakai clone, foldernya `D:\kalawa-service-abd`. Jika laptop tidak mempunyai D:, gunakan folder pada drive yang tersedia dan sesuaikan path contoh.

### 3. Periksa paket dan tujuan

Untuk hasil Download ZIP:

```powershell
Set-Location D:\kalawa-service-abd-main
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Periksa-Repo.ps1
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Pasang-Demo.ps1
```

Gunakan folder `D:\kalawa-service-abd` jika memakai clone. Pemeriksaan harus PASS dan menampilkan **DEMO VALID**.

Installer hanya untuk tujuan baru/kosong. Jika Kalawa dengan identifier yang sama sudah ada di XAMPP, pemeriksaan berhenti dan mempertahankan instalasi tersebut.

### 4. Pasang

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Pasang-Demo.ps1 -Pasang
```

Jika akun admin MariaDB mempunyai password, tambahkan `-DenganPassword` pada pemeriksaan maupun pemasangan:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Pasang-Demo.ps1 -DenganPassword -Pasang
```

Password admin diminta secara tersembunyi. Installer membuat database, 26 tabel, 38 objek SQL total, data fiktif, akun aplikasi demo, akun koneksi berizin terbatas dengan password acak, konfigurasi di luar htdocs dan 66 berkas aplikasi.

Tidak diperlukan Composer, npm, impor SQL manual, ataupun eksekusi migrasi historis saat memasang demo.

### 5. Buka aplikasi dan login

URL:

`http://localhost/kalawa-service-f1-korporat-uji/login.php`

**Password semua akun demo: `123456781234`**

| Role | Username | Cakupan |
|---|---|---|
| Owner | `owner_demo` | Seluruh cabang |
| Admin Cabang | `admin_demo` | CBG001 |
| Kasir | `kasir_demo` | CBG001 |
| Mekanik utama | `mekanik_demo` | Pekerjaan yang ditugaskan di CBG001 |
| Petugas Gudang | `gudang_demo` | CBG001 |
| Auditor | `auditor_demo` | Laporan/audit seluruh cabang |

Akun tambahan: `pembantu_demo` untuk mekanik pembantu, serta `admin2_demo`, `kasir2_demo`, `mekanik2_demo` dan `gudang2_demo` untuk CBG002. Passwordnya sama. Gunakan menu Keluar saat berpindah akun. Semua identitas tersebut merupakan akun baru untuk demo lokal.

### 6. Coba alur fitur

| Akun | Contoh yang bisa dicoba |
|---|---|
| Owner | Lima master CRUD, kontrak/plafon, tagihan lintas cabang, laporan dan audit |
| Admin Cabang | Pendaftaran pelanggan/motor, WO, jasa/barang dan mekanik pembantu |
| Mekanik | Pilih WO Menunggu, jalankan Proses lalu Selesai sesuai aturan |
| Kasir | Lihat WO selesai belum lunas; untuk bukti pilih filter Sudah lunas → Lihat bukti |
| Gudang | Cari barang, catat barang masuk/koreksi dan lihat stok di bawah minimum |
| Auditor | Telusuri empat laporan JOIN dan jejak transaksi lintas cabang |

Data awal memuat 4 cabang, 5 pelanggan/perusahaan, 12 motor, 13 WO dengan beberapa status, pembayaran, stok, kontrak serta tagihan korporat. Tanggal demo mengikuti tanggal database saat pemasangan.

### 7. Uji otomatis

Dari folder hasil ekstraksi/clone:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Uji-Final-T4.ps1
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Uji-Final-T4.ps1 -Jalankan -HasilRoot 'D:\KALAWA_HASIL_UJI'
```

Tambahkan `-DenganPassword` bila admin MariaDB berpassword. Suite membuat salinan terisolasi. Hasil lengkap yang telah diperiksa pada demo: **442 PASS, 0 FAIL, 0 BLOCKED**. Berkas hasil disimpan di luar repository.

## Lokasi XAMPP atau port berbeda

Gunakan parameter yang sama saat memeriksa dan memasang. Contoh:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Pasang-Demo.ps1 -Target 'D:\xampp\htdocs\kalawa-service-f1-korporat-uji' -Php 'D:\xampp\php\php.exe' -Port 3307
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Pasang-Demo.ps1 -Target 'D:\xampp\htdocs\kalawa-service-f1-korporat-uji' -Php 'D:\xampp\php\php.exe' -Port 3307 -Pasang
```

Untuk uji otomatis, sesuaikan `-Target` dan `-Php`. Port uji dibaca dari konfigurasi yang dibuat installer. Jika Apache memakai 8080, URL menjadi `http://localhost:8080/kalawa-service-f1-korporat-uji/login.php`.

Nama folder website, database dan user koneksi dipertahankan agar sesuai pengaman R05. Nama folder repository boleh berbeda.

| Komponen bawaan | Lokasi |
|---|---|
| Website | `C:\xampp\htdocs\kalawa-service-f1-korporat-uji` |
| Konfigurasi privat | `C:\xampp\kalawa-korporat-uji-config.php` |
| Database | `kalawa_service_f1_g1_clone_20261110_174035_33f8f960` |

## Fitur dan hak akses

Login enam role; CRUD pelanggan, motor, jasa servis, suku cadang dan supplier; WO dan rinciannya; penugasan mekanik utama/pembantu; persediaan; pembayaran; kontrak/plafon dan tagihan korporat; empat laporan JOIN; audit serta cetak dokumen.

Hak role/cabang/penugasan dan status diperiksa di backend. Mekanik pembantu membaca rincian; pengubahan status dilakukan mekanik utama yang ditugaskan. Gudang tidak mengelola pembayaran. Status pekerjaan **SELESAI** dan status finansial **LUNAS** dicatat terpisah. Lihat [matriks role](uji/CAKUPAN-DAN-ROLE.md).

## Berkas Tugas 4

| Instruksi | Lokasi |
|---|---|
| Source atau link repository | Repository ini dan [website](website/) |
| Migrasi perubahan requirement | Empat skrip pada [sql](sql/) dan [riwayat migrasi](docs/RIWAYAT-MIGRASI.md) |
| Backup atau bukti lokasi | [Bukti backup kelompok](docs/BUKTI-BACKUP.md) dan [hasil pemeriksaan lokal](docs/BUKTI-FINAL-LOKAL.json) |

Schema demo dan seed fiktif disediakan pada `demo/schema.sql` serta `tools/seed_demo_r03.php`. Data publik dibuat baru. Backup final kelompok tetap disimpan privat pada lokasi yang tercatat dalam bukti; file tersebut dapat diserahkan melalui kanal pengumpulan resmi.

Empat migrasi adalah riwayat perubahan dari baseline Tugas 1–3; schema demo sudah mencakup hasilnya. Jangan mengulang migrasi pada database final/demo yang sudah terpasang.

Laporan PDF harus berisi gambar dan deskripsi aplikasi, CRUD, query JOIN, perubahan requirement, debugging, backup serta recovery. [Pemetaan pengumpulan dan status](docs/PENGUMPULAN-T4.md). Persyaratan tambahan hanya berlaku bila diberikan dalam instruksi dosen yang terpisah.

## Memakai data arsip kelompok

Anggota/penguji yang memerlukan data snapshot kelompok dapat memperoleh paket `KALAWA_T4_SIAP_PASANG` secara privat dan menggunakan `Pasang-Dari-Clone.ps1 -PaketFinal 'D:\KALAWA_T4_SIAP_PASANG'`, lalu mengulang dengan `-Pasang`. Ini alternatif pemasangan pada tujuan kosong; satu instalasi tidak ditimpa oleh profil lain. Password akun snapshot dibagikan privat.

Pemilik tetap dapat menjalankan `Periksa-Final-Lokal.ps1` untuk membuktikan source GitHub sama dengan htdocs dan arsip kelompok. Pemeriksa itu memakai paket privat asli; installer demo publik memakai schema dan seed baru.

## Struktur

| Lokasi | Isi |
|---|---|
| `website/` | 66 berkas final R05 |
| `demo/` | Schema publik dan manifest keutuhan demo |
| `tools/` | Pendukung pengujian, installer dan generator data demo |
| `sql/` | Empat migrasi historis |
| `uji/` | Suite regresi serta fixture salinan |
| `docs/` | Instalasi, pengujian, bukti backup dan pengumpulan |

## Kendala umum

| Gejala | Pemeriksaan |
|---|---|
| Skrip tidak ditemukan | Pilih folder yang memuat README dan Pasang-Demo.ps1; periksa folder ganda setelah ekstraksi |
| PHP tidak ditemukan | Sesuaikan `-Php` dengan lokasi XAMPP |
| Access denied | Periksa akun admin MariaDB; gunakan `-DenganPassword` jika perlu |
| Database/site/config sudah ada | Installer mempertahankan instalasi yang ada; gunakan tujuan/laptop baru yang sesuai |
| could not find driver | Periksa ekstensi PDO MySQL pada PHP XAMPP |
| Halaman 404 | Periksa Apache, port HTTP dan folder situs |
| Source/demo berubah | Peroleh snapshot yang sesuai dan periksa perubahan sebelum memperbarui manifest |
| Uji belum seluruhnya PASS | Baca FAIL/BLOCKED pada ringkasan |

GitHub menyimpan source; aplikasi PHP/MariaDB dijalankan lokal di XAMPP/server yang sesuai. GitHub Pages tidak menjalankan PHP.

[Panduan demo](docs/DEMO-PUBLIK.md) · [Instalasi](docs/INSTALASI-LOKAL.md) · [Pengujian](docs/PENGUJIAN.md) · [Validasi demo](docs/VALIDASI-DEMO-R03.md)
