# Publikasi demo

Setelah pembaruan demo R03 diterapkan dan pemeriksa repository PASS:

```powershell
Set-Location D:\KALAWA_GITHUB_R01\repository
git add -- .gitignore Periksa-Repo.ps1 Pasang-Demo.ps1 README.md demo tools/install_demo_r03.php tools/seed_demo_r03.php docs
git --no-pager diff --cached --stat
git commit -m "Sediakan demo publik mandiri dengan source final R05"
git push
```

Source website tetap 66 berkas yang sama. Jangan menambahkan database/backup privat.

Untuk membuat Public, buka repository → Settings → General → Danger Zone → Change repository visibility → Change visibility → Public. Selesaikan konfirmasi yang ditampilkan GitHub.

Sesudah Public, periksa halaman menggunakan jendela incognito. Teman dapat memilih Code → Download ZIP dan mengikuti README, atau melakukan clone. Tidak diperlukan undangan collaborator untuk membaca source Public.

Pemeriksa htdocs pemilik tetap dapat dijalankan:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Periksa-Final-Lokal.ps1
git status
```

Alat tersebut memakai paket backup asli untuk bukti tugas, sedangkan installer demo memakai schema dan seed publik.
