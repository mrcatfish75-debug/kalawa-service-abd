<p align="center"><img src="website/logo/kalawa-service-logo.png" alt="Logo Kalawa Service" width="140"></p>

# Kalawa Service

Sistem operasional bengkel multi-cabang untuk proyek **Administrasi Basis Data (ABD) — Tugas 4**, yang melanjutkan database, aturan bisnis, dan administrasi pada Tugas 1–3.

**Versi aplikasi: CETAK_R05 — 66 berkas source.** Persiapan GitHub R02 menambahkan dokumentasi, pemeriksaan kesamaan source dan peluncur instalasi. Source website, migrasi SQL, serta alur transaksi R05 dipertahankan.

## Berkas pengumpulan Tugas 4

| Instruksi | Berkas atau bukti |
|---|---|
| Source aplikasi atau link repository | [Repository Kalawa Service](https://github.com/mrcatfish75-debug/kalawa-service-abd) dan folder [website](website/) |
| Script migrasi perubahan requirement | Empat skrip pada [sql](sql/), dijelaskan pada [riwayat migrasi](docs/RIWAYAT-MIGRASI.md) |
| File backup atau bukti lokasi backup | [Bukti lokasi backup dan SHA-256](docs/BUKTI-BACKUP.md), dilengkapi [hasil pemeriksaan lokal](docs/BUKTI-FINAL-LOKAL.json) |

Bukti lokasi dibuat setelah source Git yang di-fetch dari GitHub cocok dengan source repository dan folder htdocs, serta paket backup lolos pemeriksaan hash. SQL backup disimpan privat oleh kelompok dan dapat diserahkan melalui kanal pengumpulan resmi.

Laporan PDF, video 10–15 menit, logbook, nama/NIM anggota dan pengumpulan oleh perwakilan tetap dilengkapi sesuai instruksi dosen. Lihat [pemetaan pengumpulan](docs/PENGUMPULAN-T4.md).

## Fitur dan pembagian role

| Role | Tugas utama |
|---|---|
| Owner | Pengelolaan seluruh cabang, lima master CRUD, WO, stok, pembayaran, kontrak/tagihan korporat, laporan dan audit |
| Admin Cabang | Pendaftaran pelanggan/motor, WO dan penugasan mekanik di cabangnya, status pekerjaan, laporan cabang |
| Kasir | Pembayaran WO selesai, rincian jasa/barang, bukti pembayaran dan tagihan korporat sesuai batas cabang |
| Mekanik | Pekerjaan yang ditugaskan; mekanik utama menjalankan Menunggu → Proses → Selesai; pembantu membaca rincian |
| Petugas Gudang | Persediaan dan mutasi stok cabang, katalog, laporan pemakaian barang dan stok minimum |
| Auditor | Membaca laporan lintas cabang, rincian servis dan jejak audit |

Fitur mencakup CRUD pelanggan, motor, jasa servis, suku cadang dan supplier; WO beserta jasa/barang; pembayaran; pelanggan korporat; kontrak/plafon kredit; tagihan kolektif bulanan; serta empat laporan JOIN. Pembatasan role, cabang, penugasan dan status diperiksa di backend. Rincian hak akses ada di [cakupan enam role](uji/CAKUPAN-DAN-ROLE.md).

Status finansial **LUNAS** berbeda dari status pekerjaan **SELESAI**. Kasir menerima pelunasan setelah pekerjaan memenuhi aturan. Cetak mencakup laporan, rincian servis, bukti pembayaran dan dokumen korporat sesuai izin.

## Teknologi dan struktur

PHP/PDO MySQL, MariaDB, HTML/CSS dan JavaScript. Tidak ada tahap npm atau Composer pada versi ini.

| Lokasi | Kegunaan |
|---|---|
| `website/` | 66 berkas aplikasi, logo, CSS dan JavaScript |
| `sql/` | Empat migrasi historis |
| `uji/` | Suite pengujian, fixture salinan dan pembersihan salinan uji |
| `tools/` | Fungsi pendukung pengujian dan verifikasi GitHub |
| `docs/` | Instalasi, requirement, migrasi, pengujian, bukti backup dan panduan pengumpulan |
| `Pasang-Dari-Clone.ps1` | Memeriksa clone + paket privat lalu memanggil installer final yang sudah ada |
| `Periksa-Final-Lokal.ps1` | Mencocokkan GitHub, repository, htdocs dan arsip backup |
| `Periksa-Repo.ps1` | Memeriksa source dan berkas repository sebelum commit |
| `konfigurasi-contoh.php` | Contoh konfigurasi tanpa password koneksi aktual |

## Instalasi pada laptop Windows lain dari clone

### 1. Siapkan lingkungan

Gunakan lingkungan yang sama dengan laptop kelompok untuk reproduksi tugas:

- Windows dan Windows PowerShell 5.1.
- Git for Windows, dengan Git Credential Manager.
- **XAMPP Windows 8.2.12, PHP 8.2.12 dan MariaDB 10.4.32** sebagai baseline kelompok.
- PHP CLI dan ekstensi PDO MySQL tersedia.

Unduh Git dari [situs Git](https://git-scm.com/install/windows). Arsip XAMPP baseline tersedia pada [proyek resmi XAMPP di SourceForge](https://sourceforge.net/projects/xampp/files/XAMPP%20Windows/8.2.12/). [Halaman Apache Friends](https://www.apachefriends.org/download.html) menyediakan versi lain; hasil pengujian baseline tidak otomatis berlaku untuk versi MariaDB yang berbeda.

Contoh lokasi instalasi adalah `C:\xampp`. Aktifkan **Apache** dan **MySQL/MariaDB** melalui XAMPP Control Panel. Dari PowerShell:

```powershell
git --version
& 'C:\xampp\php\php.exe' -v
& 'C:\xampp\mysql\bin\mysql.exe' --version
& 'C:\xampp\php\php.exe' -m | Select-String -Pattern 'PDO|pdo_mysql'
```

Pemasangan ditujukan untuk XAMPP baru/tujuan kosong. Installer menolak jika folder website, konfigurasi, database final atau akun koneksi yang sama sudah ada.

### 2. Dapatkan akses repository dan clone

Jika repository Private, pemilik mengundang akun GitHub anggota/penguji melalui Settings → Collaborators. Terima undangan menggunakan akunmu.

Jalankan:

```powershell
Set-Location D:\
git clone https://github.com/mrcatfish75-debug/kalawa-service-abd.git
Set-Location D:\kalawa-service-abd
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Periksa-Repo.ps1
```

Jika Git meminta login, gunakan akun GitHub yang diberi akses. Autentikasi browser/Git Credential Manager terpisah dari akun login Kalawa. Hasil pemeriksaan repository harus PASS.

### 3. Dapatkan paket database final secara privat

Clone berisi source dan migrasi. Untuk memperoleh struktur database, objek SQL, data dan akun aplikasi yang sama dengan demonstrasi kelompok, mintalah paket lengkap **KALAWA_T4_SIAP_PASANG** kepada pemilik proyek. Lokasi arsip dan hash dapat dicocokkan dengan [bukti backup](docs/BUKTI-BACKUP.md).

Ekstrak di luar repository dan htdocs, misalnya:

`D:\KALAWA_T4_SIAP_PASANG`

Folder tersebut harus berisi:

- `MANIFEST-PAKET.json`.
- `database\kalawa-final.sql`, `database\backup-asli.sql` dan `database\fingerprint.json`.
- `website`, `tools`, `uji` dan `bukti`.
- `Pasang-Baru-T4.ps1`.

Periksa lokasi ekstraksi:

```powershell
Test-Path 'D:\KALAWA_T4_SIAP_PASANG\MANIFEST-PAKET.json'
Test-Path 'D:\KALAWA_T4_SIAP_PASANG\database\kalawa-final.sql'
Test-Path 'D:\KALAWA_T4_SIAP_PASANG\Pasang-Baru-T4.ps1'
```

Ketiganya harus True. Jika ada folder ganda setelah ekstraksi, gunakan path folder terdalam yang benar pada parameter `-PaketFinal`.

Paket yang dipakai adalah snapshot R05 yang dirujuk oleh provenance. Peluncur memeriksa 123 berkas paket, 66 source, migrasi, bukti 442 pengujian dan bukti restore. Empat migrasi historis saja tidak dapat membangun database kosong.

### 4. Jalankan pemeriksaan instalasi

Dari folder clone:

```powershell
Set-Location D:\kalawa-service-abd
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Pasang-Dari-Clone.ps1 -PaketFinal 'D:\KALAWA_T4_SIAP_PASANG'
```

Ini memeriksa kecocokan clone/backup dan kesiapan tujuan. Jika admin MariaDB memakai password, tambahkan `-DenganPassword`. Password diminta secara tersembunyi; jangan menuliskannya pada argumen perintah.

### 5. Pasang database dan aplikasi

Setelah pemeriksaan berhasil:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Pasang-Dari-Clone.ps1 -PaketFinal 'D:\KALAWA_T4_SIAP_PASANG' -Pasang
```

Untuk admin MariaDB berpassword:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Pasang-Dari-Clone.ps1 -PaketFinal 'D:\KALAWA_T4_SIAP_PASANG' -DenganPassword -Pasang
```

Peluncur menggunakan installer final yang sudah ada di paket privat. Installer membuat database, mengimpor dump portabel, memeriksa fingerprint data/objek, membuat akun koneksi berizin terbatas, menulis konfigurasi di luar htdocs dengan password koneksi acak, dan menyalin source ke website. Pemeriksaan sesudah pemasangan memastikan 66 berkas terpasang sama dengan clone.

Tujuan bawaan:

| Komponen | Lokasi/nilai |
|---|---|
| Website | `C:\xampp\htdocs\kalawa-service-f1-korporat-uji` |
| Konfigurasi privat | `C:\xampp\kalawa-korporat-uji-config.php` |
| Database | `kalawa_service_f1_g1_clone_20261110_174035_33f8f960` |
| SQL service user | `ks_korp_uji_33f8f960@127.0.0.1` |
| Port MariaDB | 3306 |

Nama repository dan nama folder website memang berbeda. Identifier database, user koneksi serta path sesi masih terikat pada pengaman R05, sehingga dipertahankan.

Jika XAMPP berada di D: dan MariaDB memakai port 3307, gunakan parameter yang sama pada pemeriksaan dan pemasangan:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Pasang-Dari-Clone.ps1 -PaketFinal 'D:\KALAWA_T4_SIAP_PASANG' -Target 'D:\xampp\htdocs\kalawa-service-f1-korporat-uji' -Php 'D:\xampp\php\php.exe' -Port 3307
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Pasang-Dari-Clone.ps1 -PaketFinal 'D:\KALAWA_T4_SIAP_PASANG' -Target 'D:\xampp\htdocs\kalawa-service-f1-korporat-uji' -Php 'D:\xampp\php\php.exe' -Port 3307 -Pasang
```

### 6. Buka aplikasi dan login

Buka:

`http://localhost/kalawa-service-f1-korporat-uji/login.php`

Jika Apache memakai port lain, tambahkan port Apache pada URL, misalnya `http://localhost:8080/kalawa-service-f1-korporat-uji/login.php`.

Minta username/password aplikasi kepada pemilik proyek melalui saluran privat. Installer mempertahankan akun aplikasi dari backup. Password aplikasi, password admin MariaDB dan password SQL service user merupakan tiga kredensial berbeda. Tidak ada reset password akun aplikasi dalam persiapan GitHub.

### 7. Uji hasil pemasangan

Jalankan dari folder clone:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Uji-Final-T4.ps1
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Uji-Final-T4.ps1 -Jalankan -HasilRoot 'D:\KALAWA_HASIL_UJI'
```

Tambahkan `-DenganPassword` jika admin MariaDB berpassword. Jika XAMPP berada di D:, tambahkan `-Target 'D:\xampp\htdocs\kalawa-service-f1-korporat-uji' -Php 'D:\xampp\php\php.exe'`. Port pengujian dibaca dari konfigurasi situs yang ditulis installer.

Suite membuat salinan terisolasi. Hasil yang diharapkan pada baseline lengkap adalah **442 PASS, 0 FAIL, 0 BLOCKED**. Baca `ringkasan.md` pada folder hasil, lalu periksa login tiap role dan cetak melalui [checklist browser](docs/CHECKLIST-BROWSER-DAN-CETAK.md).

Folder hasil dapat memuat backup dan kredensial salinan uji; simpan di luar repository. Lihat [pengujian](docs/PENGUJIAN.md).

## Pemeriksaan final di laptop pemilik proyek

Untuk membuktikan source GitHub sama dengan website htdocs dan paket backup:

```powershell
Set-Location D:\KALAWA_GITHUB_R01\repository
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Periksa-Final-Lokal.ps1 -PaketFinal 'D:\KALAWA_CETAK_R05\hasil\paket_20261117_195957_189\KALAWA_T4_SIAP_PASANG' -SimpanBukti
```

Alat melakukan fetch origin/main, membandingkan blob Git dengan source htdocs, memeriksa empat migrasi dan keutuhan paket backup, lalu menulis `docs/BUKTI-FINAL-LOKAL.json` serta `docs/BUKTI-BACKUP.md`. Isi bukti hanya metadata/path/hash dan hasil verifikasi, tanpa baris data pelanggan atau password.

Pemeriksaan ini tidak menjalankan ulang transaksi, mengakses database live, atau menyatakan snapshot backup sama dengan data live hari ini. Source aktif tambahan di luar manifest dihentikan untuk ditinjau; alat tidak menghapusnya.

Setelah bukti/dokumentasi di-commit dan di-push, ulangi pemeriksaan **tanpa** `-SimpanBukti` agar hasilnya tidak mengubah working tree. Commit dokumentasi dapat berubah sementara tree source website tetap sama.

## Migrasi perubahan requirement

| Skrip | Isi |
|---|---|
| [001_korporat.sql](sql/001_korporat.sql) | Profil pelanggan korporat, kontrak, relasi WO, tagihan bulanan dan rincian kolektif |
| [002_penerimaan_kas.sql](sql/002_penerimaan_kas.sql) | Penerimaan pembayaran individual/korporat dan pengisian baseline |
| [003_garansi_lunas.sql](sql/003_garansi_lunas.sql) | Penyesuaian fungsi garansi agar selesai dan lunas menjadi prasyarat |
| [004_audit_terpadu.sql](sql/004_audit_terpadu.sql) | View audit transaksi terpadu |

Migrasi merupakan riwayat perubahan Tugas 1–3 menuju Tugas 4. Dump final sudah memuat perubahan tersebut; pemasangan dari dump tidak memerlukan pengulangan migrasi. Tabel/constraint/view/procedure/function/trigger terdokumentasi melalui source SQL dan arsip final. Dump database tidak mengekspor seluruh user/role global server; installer membuat akun koneksi aplikasi yang diperlukan.

## Backup dan recovery

Strategi arsip final adalah **full logical SQL dump** setelah data uji tersedia. Ada dump portabel untuk pemasangan ulang dan dump asli untuk arsip. Backup juga memuat fingerprint data dan objek, serta bukti pemulihan.

Snapshot paket dibuat pada **17 November 2026, 20:00:02 WIB**. Bukti full export/import ke database baru menunjukkan PASS dengan **26 tabel dasar dan 38 objek SQL total**, baris/hash identik. Suite juga mencakup simulasi kehilangan dan pemulihan pada salinan non-produksi.

Hasil arsip tidak dipresentasikan sebagai pengujian baru. Lokasi yang benar-benar ditemukan pada laptop pemilik, SHA-256 kedua dump dan metadata restore dicatat pada [bukti backup](docs/BUKTI-BACKUP.md).

Jika data operasional berubah setelah snapshot, pemilik dapat menjalankan pengujian dan builder paket final kembali dari perangkat finalisasi yang sesuai. Paket baru perlu ditinjau dan provenance diperbarui berdasarkan hasilnya; jangan sekadar mengganti hash untuk melewati pemeriksaan.

## Kendala umum

| Gejala | Pemeriksaan |
|---|---|
| Repository not found | Periksa URL, akun GitHub dan penerimaan undangan repo Private |
| Skrip tidak ditemukan | Pastikan PowerShell berada di root clone, yang memuat README dan skrip terkait |
| Paket/manifest tidak ditemukan | `-PaketFinal` harus menunjuk folder lengkap yang berisi MANIFEST-PAKET.json |
| Source atau manifest paket berbeda | Gunakan snapshot final yang sesuai; tinjau perubahan sebelum memperbarui provenance |
| Database/site/config sudah ada | Installer hanya untuk tujuan kosong; gunakan lingkungan yang sesuai tanpa menghapus instalasi aktif |
| Access denied saat instalasi | Periksa akun admin MariaDB dan gunakan `-DenganPassword` bila perlu |
| could not find driver | Periksa PHP CLI yang dipakai dan ekstensi PDO MySQL |
| Halaman localhost 404 | Periksa Apache, port HTTP dan nama folder website |
| Uji belum seluruhnya PASS | Baca FAIL/BLOCKED di ringkasan; jumlah PASS saja belum cukup |

GitHub menyediakan source dan kolaborasi; PHP/MariaDB tetap dijalankan pada XAMPP/server yang sesuai. [GitHub Pages tidak menjalankan PHP](https://docs.github.com/en/pages/getting-started-with-github-pages/creating-a-github-pages-site).

## Dokumen pendukung

- [Matriks kesesuaian Tugas 1–4](docs/MATRIKS-TUGAS.md).
- [Cakupan enam role](uji/CAKUPAN-DAN-ROLE.md).
- [Instalasi dan parameter](docs/INSTALASI-LOKAL.md).
- [Pengujian dan batas bukti](docs/PENGUJIAN.md).
- [Pemeriksaan serta push dokumentasi](docs/PUSH-GITHUB.md).
- [Pemetaan berkas pengumpulan](docs/PENGUMPULAN-T4.md).
- [Provenance source dan backup R05](docs/PROVENANSI-FINAL-R05.json).
