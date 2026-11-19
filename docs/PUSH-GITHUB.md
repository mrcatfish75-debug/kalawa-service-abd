# Memperbarui repository final

Gunakan repository yang sudah memiliki origin dan branch main. Persiapan R02 memeriksa source sebelum mengganti dokumentasi; htdocs dan database tidak diperbarui oleh paket dokumentasi.

Setelah paket R02 diterapkan dan bukti lokal berhasil dibuat:

```powershell
Set-Location D:\KALAWA_GITHUB_R01\repository
git status --short
git --no-pager diff --stat
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Periksa-Repo.ps1
git add -- README.md docs Periksa-Final-Lokal.ps1 Pasang-Dari-Clone.ps1 tools/github_final_r02.ps1
git --no-pager diff --cached --stat
git commit -m "Lengkapi README instalasi clone dan bukti final Tugas 4"
git push
```

Verifikasi sesudah push:

```powershell
git status
git rev-parse HEAD
git ls-remote origin refs/heads/main
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Periksa-Final-Lokal.ps1
```

Jika lokasi paket tidak ditemukan otomatis, tambahkan -PaketFinal dengan folder sebenarnya. Pemeriksa pascapush dijalankan tanpa -SimpanBukti sehingga tidak mengubah bukti dan working tree.

HEAD harus sama dengan origin/main. Halaman GitHub harus menampilkan README terbaru, dua skrip peluncur baru, empat migrasi dan bukti lokasi backup. Source website tetap 66 berkas identik.

Bukti mencatat commit/tree yang diperiksa sebelum commit dokumentasi; perubahan commit dokumentasi berikutnya tidak mengubah tree source. Undang penilai/anggota untuk repository Private melalui Settings → Collaborators.

Jika terjadi penolakan push karena remote telah berubah, periksa perbedaannya sebelum menyatukan perubahan. Simpan SQL backup, konfigurasi aktual, hasil uji dan password di luar repository.
