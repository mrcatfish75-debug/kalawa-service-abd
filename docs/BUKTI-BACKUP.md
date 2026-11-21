# Bukti lokasi backup dan kesamaan source final

## Arsip R05 yang diperiksa 19 November 2026

| Pemeriksaan | Hasil |
|---|---|
| Waktu pemeriksaan WIB | 2026-11-19T14:14:42+07:00 |
| Source GitHub, repository lokal dan htdocs | 66 berkas CETAK_R05 identik |
| Folder website | C:\xampp\htdocs\kalawa-service-f1-korporat-uji |
| Commit remote yang diperiksa | dd5819c756172b0903c18dad882e61517e5f282a |
| Tree source website | 5c357ae7ead27e3d5d28231135d8559696c56756 |
| Migrasi GitHub | Empat berkas SQL identik |
| Folder paket backup privat | D:\KALAWA_CETAK_R05\hasil\paket_20261117_195957_189\KALAWA_T4_SIAP_PASANG |
| Dump portabel | D:\KALAWA_CETAK_R05\hasil\paket_20261117_195957_189\KALAWA_T4_SIAP_PASANG\database\kalawa-final.sql |
| SHA-256 dump portabel | 5f79a38084f520515a698c81719b2bdcdd053bde7c882eaebe51c98573210e1a |
| Dump asli | D:\KALAWA_CETAK_R05\hasil\paket_20261117_195957_189\KALAWA_T4_SIAP_PASANG\database\backup-asli.sql |
| SHA-256 dump asli | 00e7ba0e347dbab0c7e8015be74d1a5550d288eb653ff532d9218a927007040d |
| Keutuhan paket | 123 berkas manifest cocok |
| Isi backup | 26 tabel dasar; 38 objek SQL total |
| Bukti full export/import | PASS; baris/hash identik; 2026-11-17T20:00:01+07:00 |
| Bukti uji pada snapshot | 442 PASS; 0 FAIL; 0 BLOCKED |

Berkas SQL disimpan privat di lokasi di atas. Penilai dapat meminta salinan paket kepada kelompok atau menerima berkas melalui kanal pengumpulan resmi. Metadata ini bukan link unduhan database.

Pemeriksaan ini membaca source Git yang di-fetch dari origin/main dan berkas lokal. Pemeriksaan tidak mengakses data database live, tidak mengulang 442 transaksi, serta tidak menjalankan ulang restore. Timestamp restore di atas adalah waktu pengujian arsip, bukan waktu pemeriksaan filesystem ini.

Commit dokumentasi sesudah pemeriksaan dapat berbeda; tree source website tetap sama jika hanya dokumentasi/alat persiapan yang berubah. Rincian dapat dibaca pada BUKTI-FINAL-LOKAL.json.

## Backup dan recovery baru — 21 November 2026

Pemilik menyerahkan `PAKET_BUKTI_T4.zip` hasil pengumpulan pukul 21:15–21:17 WIB. File SQL di dalamnya benar-benar tersedia dan hash-nya diperiksa; ini berbeda dari dump 17 November di atas. Lokasi relatif berikut berada di dalam folder privat `PAKET_BUKTI_T4`.

| Berkas/hasil | Bukti |
| --- | --- |
| Backup sumber sebelum fixture capture | `05-backup-recovery/database/backup-sumber-aktif.sql`; 64 akun, 83 WO, 33 penerimaan |
| Backup setelah data uji lengkap dan invoice dilunasi pada salinan | `05-backup-recovery/database/backup-bukti-lengkap.sql`; 86 akun, 129 WO, 42 penerimaan |
| Backup tabel contoh kehilangan data | `05-backup-recovery/database/backup-simulasi-satu-tabel.sql` |
| Full restore | Data26 tabel dan 38 definisi objek total identik; waktu21:17:18 WIB |
| Simulasi nonproduksi terpisah | 3 record → 2 record →tabel dihapus →restore 3 record; hash awal/pulih sama |
| Hash aktual SQL/arsip | [BUKTI-T4-20261121.json](BUKTI-T4-20261121.json), bagian `backups` dan `archive` |

Folder run lokal berada di bawah `D:\KALAWA_BUKTI_LENGKAP_R02\hasil`; path run terakhir dibaca dari `hasil\HASIL-TERAKHIR.txt`. Nama lengkap folder dengan milidetik tidak disimpulkan dari waktu JSON. Penilai dapat memperoleh ZIP/SQL melalui kanal privat. **Jangan push arsip hasil, SQL dump, konfigurasi atau cookie sesi ke repository publik.**

Strategi yang dibuktikan adalah **full dump SQL konsisten per eksekusi alat** dan recovery ke database baru. Tidak ada klaim incremental/differential, backup terjadwal atau point-in-time recovery. User/role global MariaDB berada di luar dump schema aplikasi; installer demo menyiapkan akun koneksinya sendiri. Source aktif tidak menjadi sasaran simulasi kehilangan atau restore.

