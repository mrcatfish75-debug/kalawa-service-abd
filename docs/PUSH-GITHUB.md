# Push Kalawa Service ke GitHub

Akun terkonfirmasi: **mrcatfish75-debug** (Darryl). Paket ini belum membuat repository atau melakukan push. Jalankan langkah berikut di Windows PowerShell biasa.

## 1. Git dan ekstraksi

Jalankan `git --version`. Jika tidak dikenali, pasang Git for Windows dari https://git-scm.com/install/windows, pertahankan Git Credential Manager, lalu tutup dan buka kembali PowerShell.

Simpan ZIP ini di D: dan ekstrak sehingga tersedia `D:\KALAWA_GITHUB_R01\repository\README.md`. Untuk folder hasil yang belum ada:

```powershell
Expand-Archive -LiteralPath D:\KALAWA_GITHUB_R01.zip -DestinationPath D:\
Set-Location D:\KALAWA_GITHUB_R01\repository
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Periksa-Repo.ps1
```

Tiga pemeriksaan harus PASS. Jika folder ekstraksi sudah ada, gunakan tujuan baru agar pekerjaan sebelumnya tidak tertimpa. Website di htdocs tidak disentuh. Source 66 berkas R05, empat migrasi dan alat uji sudah tersedia.

## 2. Buat repository kosong

Buka https://github.com/new, login sebagai **mrcatfish75-debug**, lalu isi:

| Isian | Nilai |
|---|---|
| Owner | mrcatfish75-debug |
| Repository name | kalawa-service-abd |
| Description | Sistem operasional bengkel multi-cabang — ABD Tugas 4 |
| Visibility | Private |
| README, .gitignore, License | Jangan diinisialisasi dari GitHub |

Klik Create repository. Jika nama sudah dipakai, pilih nama baru dan sesuaikan URL perintah di bawah; jangan menimpa repo lama.

## 3. Git lokal dan identitas

```powershell
git init
git branch -M main
git config core.autocrlf false
git config user.name "Darryl"
```

Buka https://github.com/settings/emails dan salin email commit/noreply yang ditampilkan GitHub. Untuk menjaga email pribadi gunakan noreply pada pengaturan privasi email, lalu:

```powershell
$EmailGit = Read-Host 'Tempel email commit/noreply dari GitHub Settings > Emails'
if ($EmailGit -notmatch '^[^\s@]+@[^\s@]+\.[^\s@]+$') { throw 'Alamat email belum valid.' }
git config user.email $EmailGit
```

Konfigurasi hanya berlaku untuk repo ini. .gitattributes mempertahankan byte berkas agar hash R05 tidak berubah karena konversi akhir baris Windows/Linux.

## 4. Pilih berkas dan periksa

```powershell
git add -- .gitattributes .gitignore README.md Periksa-Repo.ps1 Common-T4.ps1 Uji-Final-T4.ps1 manifest-final.json konfigurasi-contoh.php website sql tools uji docs
git status --short
git diff --cached --stat
git ls-files
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Periksa-Repo.ps1
```

Jangan tambahkan konfigurasi aktual, password/token, .env, folder hasil/bukti/database, backup SQL, ZIP paket lengkap atau PDF pelanggan. Membaca perubahan tetap diperlukan; pemeriksaan nama/path bukan pemindai rahasia sempurna.

Jika berkas privat telanjur di-stage, lepaskan dengan `git rm --cached -- NAMA_BERKAS` atau `git rm -r --cached -- NAMA_FOLDER`. Ganti penanda dengan path yang tepat. Ini tidak menghapus salinan lokal. Simpan berkas privat di luar repo. .gitignore tidak melepaskan berkas yang sudah dilacak.

## 5. Commit dan push

```powershell
git commit -m "Finalisasi Kalawa Service ABD Tugas 4 - CETAK_R05"
git remote add origin https://github.com/mrcatfish75-debug/kalawa-service-abd.git
git remote -v
git push -u origin main
```

Saat login browser/Git Credential Manager muncul, pilih mrcatfish75-debug dan selesaikan 2FA jika diminta. Sesi Git Windows berbeda dari koneksi GitHub di ChatGPT. Password Kalawa bukan password GitHub. Jangan memasukkan token/password ke URL remote atau chat.

Jika origin sudah ada, periksa `git remote -v`. Jika URL salah, gunakan `git remote set-url origin https://github.com/mrcatfish75-debug/kalawa-service-abd.git`.

Jika Repository not found, periksa repo sudah dibuat, URL dan akun Git Windows. Jika non-fast-forward, jangan force push; periksa isi/riwayat repo dahulu atau gunakan repo baru yang benar-benar kosong.

## 6. Verifikasi

```powershell
git status
git log -1 --oneline
git rev-parse HEAD
git ls-remote origin refs/heads/main
```

Hash HEAD harus sama dengan hash remote main; working tree bersih. Refresh https://github.com/mrcatfish75-debug/kalawa-service-abd dan periksa README, website, sql, uji serta docs. Simpan URL, hash commit dan screenshot repo untuk laporan.

## 7. Teman, dosen dan pengumpulan

Untuk repo Private, buka Settings > Collaborators > Add people, undang username pihak yang berwenang dan pastikan undangan diterima. Penilai memerlukan akses untuk membuka URL.

Teman dapat memilih Code > Download ZIP atau clone HTTPS setelah login. Repo ini menyediakan source/migrasi tanpa database baseline, seed atau akun login. Untuk menjalankan aplikasi, bagikan paket **KALAWA_T4_SIAP_PASANG** secara privat dan ikuti docs/INSTALASI-LOKAL.md. Jangan mengunggah backup asli ke repo.

GitHub Pages tidak menjalankan PHP/MariaDB; aplikasi tetap dijalankan melalui XAMPP/server yang sesuai. Nama repository boleh kalawa-service-abd; nama folder website/database/akun koneksi R05 tetap mengikuti pengaman aplikasi.

Push yang berhasil dan bisa diakses dosen memenuhi komponen source/link repository. Backup atau bukti lokasi backup, PDF laporan, migrasi, video, logbook dan identitas tetap mengikuti tugas. Empat migrasi tersedia di sql/.

## Perubahan berikutnya

```powershell
git status --short
git add -- README.md
git diff --cached
git commit -m "Perbarui dokumentasi Kalawa Service"
git push
```

Jika mengubah fitur/source, uji lalu perbarui snapshot/manifest secara terverifikasi. Jangan mengganti hash hanya supaya pemeriksaan lolos.

Pengujian aplikasi bila diperlukan, dengan hasil disimpan di luar repo:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Uji-Final-T4.ps1 -Jalankan -HasilRoot D:\KALAWA_HASIL_UJI_GITHUB
```

Tambahkan -DenganPassword untuk admin MariaDB berpassword. Source yang tidak berubah tidak memerlukan pengujian ulang transaksi hanya untuk push.

Referensi resmi:

- https://docs.github.com/en/migrations/importing-source-code/using-the-command-line-to-import-source-code/adding-locally-hosted-code-to-github?platform=windows
- https://docs.github.com/en/get-started/git-basics/caching-your-github-credentials-in-git
- https://docs.github.com/en/account-and-profile/how-tos/email-preferences/setting-your-commit-email-address
- https://docs.github.com/en/pages/getting-started-with-github-pages/creating-a-github-pages-site
