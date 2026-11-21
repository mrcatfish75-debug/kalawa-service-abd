# Kelengkapan pengumpulan Tugas 4

Acuan di sini adalah instruksi yang diberikan: laporan PDF berisi gambar/deskripsi aplikasi, CRUD, query JOIN, perubahan requirement, debugging, backup dan recovery; disertai source/link, script migrasi, dan file backup atau bukti lokasi.

| Instruksi | Status dan lokasi |
| --- | --- |
| Source aplikasi atau link repository | [x] Repository publik ini;66 source pada [website](../website/) sesuai CETAK_R05 |
| Script migrasi perubahan requirement | [x] Empat script utama pada [sql](../sql/); [riwayat](RIWAYAT-MIGRASI.md) dan referensi rekonsiliasi data historis terpisah |
| File backup atau bukti lokasi | [x] SQL aktual dalam arsip privat; [lokasi/hash](BUKTI-BACKUP.md) dan [verifikasi baru](BUKTI-T4-20261121.json) |
| Gambar/data untuk laporan | [x] 18 kelompok gambar, tujuh PDF cetak, JSON/CSV/log; [peta bukti](BUKTI-T4-2026-11-21.md) |
| Laporan PDF final | [ ] Bahan sudah tersedia; gabungkan narasi, query, tabel uji dan gambar, lalu periksa PDF akhir |
| Pengumpulan melalui kanal resmi | [ ] Belum ada bukti pengumpulan pada arsip yang diperiksa |

Laporan harus memuat empat bagian berikut.

1. **Aplikasi:** lingkungan/koneksi ke database Tugas 3, login/role, lima CRUD, WO dan detail jasa/barang, pemetaan status teknis/finansial, pembayaran, empat laporan JOIN beserta query dan penjelasan hasil.
2. **Requirement korporat:** kebutuhan baru, perbandingan sebelum/sesudah, analisis tabel/relasi/constraint/view/stored procedure/function/trigger/aplikasi, migrasi, dan minimal dua fitur yang diperbarui. Gunakan kontrak/armada serta invoice dua WO.
3. **Debugging:** kriteria build/eksekusi, minimal lima skenario dengan input/harapan/hasil aktual/kesimpulan, temuan nyata, penyebab/perbaikan dan gambar sebelum/sesudah. Rekonsiliasi lama diberi label historis dan asumsi tanggal.
4. **Backup/recovery:** strategi full SQL, file/lokasi/hash, kehilangan data pada salinan, operasi/perintah restore dan pembandingan data yang pulih. Pisahkan simulasi satu tabel dari full restore 26 tabel.

Angka26 tabel/38 definisi objek total dan66 source mengacu final R05. Hasil442 PASS baru bertanggal21 November 2026 memakai salinan; jumlah data sumber, fixture screenshot dan debugging historis tidak dicampur.

Dokumentasi lama juga menyebut video, logbook dan tanggal pengumpulan tertentu. Hal-hal tersebut tidak tercantum dalam instruksi Tugas 4 yang menjadi acuan pembaruan ini, sehingga tidak ditetapkan sebagai kewajiban di checklist. Ikuti instruksi tambahan dosen bila ada dokumen terpisah. Identitas dan pembagian penulis diisi sendiri oleh kelompok.

Source, migrasi dan bukti backup tidak menggantikan laporan PDF final. Gunakan [checklist lengkap](CHECKLIST-T4.md) sebelum pengumpulan.
