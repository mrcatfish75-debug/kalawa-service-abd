Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Read-KfgJson {
    param([string]$Path)
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) { throw "Berkas tidak tersedia: $Path" }
    return ([IO.File]::ReadAllText($Path, [Text.Encoding]::UTF8) | ConvertFrom-Json)
}
function Get-KfgHash {
    param([string]$Path)
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) { throw "Berkas hilang: $Path" }
    $item = Get-Item -LiteralPath $Path -Force
    if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { throw "Link/junction berkas ditolak: $Path" }
    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
}
function Get-KfgChild {
    param([string]$Root,[string]$Relative)
    if ($Relative -match '(^|/)\.\.(/|$)|^[A-Za-z]:|^[\\/]|\\' -or [string]::IsNullOrWhiteSpace($Relative)) {
        throw "Path relatif tidak sah: $Relative"
    }
    $base = [IO.Path]::GetFullPath($Root).TrimEnd([IO.Path]::DirectorySeparatorChar,[IO.Path]::AltDirectorySeparatorChar)
    $path = [IO.Path]::GetFullPath((Join-Path $base $Relative))
    if (-not $path.StartsWith($base + [IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)) {
        throw "Path keluar dari folder: $Relative"
    }
    return $path
}
function ConvertTo-KfgArgument {
    param([AllowEmptyString()][string]$Value)
    if ($Value.Contains([string][char]0)) { throw 'Argumen mengandung NUL.' }
    $escaped = [regex]::Replace($Value,'(\\*)"','$1$1\"')
    $escaped = [regex]::Replace($escaped,'(\\+)$','$1$1')
    return '"' + $escaped + '"'
}
function Invoke-KfgGit {
    param([string]$Repo,[string[]]$GitArgs)
    $git = Get-Command git -CommandType Application -ErrorAction Stop | Select-Object -First 1
    $argsAll = @('-C',$Repo) + $GitArgs
    $info = New-Object Diagnostics.ProcessStartInfo
    $info.FileName = $git.Source
    $info.Arguments = (($argsAll | ForEach-Object { ConvertTo-KfgArgument $_ }) -join ' ')
    $info.UseShellExecute = $false
    $info.CreateNoWindow = $true
    $info.RedirectStandardOutput = $true
    $info.RedirectStandardError = $true
    $info.StandardOutputEncoding = [Text.Encoding]::UTF8
    $info.StandardErrorEncoding = [Text.Encoding]::UTF8
    $process = New-Object Diagnostics.Process
    $process.StartInfo = $info
    try {
        if (-not $process.Start()) { throw 'Git tidak dapat dimulai.' }
        $stdoutTask = $process.StandardOutput.ReadToEndAsync()
        $stderrTask = $process.StandardError.ReadToEndAsync()
        $process.WaitForExit()
        $stdout = $stdoutTask.Result
        $stderr = $stderrTask.Result
        if ($process.ExitCode -ne 0) { throw ("Git gagal: " + $stderr.Trim() + ' ' + $stdout.Trim()) }
        return $stdout.TrimEnd([char]13,[char]10)
    } finally { $process.Dispose() }
}
function Test-KfgSource {
    param([string]$Root,[object]$Expected,[switch]$CekTambahan)
    $rootPath = (Resolve-Path -LiteralPath $Root -ErrorAction Stop).Path.TrimEnd([IO.Path]::DirectorySeparatorChar,[IO.Path]::AltDirectorySeparatorChar)
    $entries = @($Expected.PSObject.Properties)
    if ($entries.Count -ne 66) { throw 'Manifest source harus memuat 66 berkas.' }
    foreach ($entry in $entries) {
        $file = Get-KfgChild $rootPath $entry.Name
        if ((Get-KfgHash $file) -cne [string]$entry.Value) { throw "Source berbeda dari final R05: $($entry.Name)" }
    }
    $extraRuntime = @()
    if ($CekTambahan) {
        foreach ($file in @(Get-ChildItem -LiteralPath $rootPath -Recurse -File -Force)) {
            $relative = $file.FullName.Substring($rootPath.Length + 1).Replace('\','/')
            if ($relative -match '(^|/)(\.git|\.agents|\.agent|\.vscode|\.idea)/') { continue }
            if ($null -eq $Expected.PSObject.Properties[$relative] -and
                ($file.Extension -match '^\.(php|phtml|phar|js|css)$' -or $file.Name -eq '.htaccess')) {
                $extraRuntime += $relative
            }
        }
    }
    if ($extraRuntime.Count -gt 0) {
        throw ('Ada source aktif di luar manifest; periksa dahulu, jangan hapus otomatis: ' + ($extraRuntime -join ', '))
    }
    return 66
}
function Resolve-KfgPackage {
    param([string]$Path)
    if (-not [string]::IsNullOrWhiteSpace($Path)) {
        return (Resolve-Path -LiteralPath $Path -ErrorAction Stop).Path
    }
    $candidates = @(
        'D:\KALAWA_CETAK_R05\hasil\paket_20261117_195957_189\KALAWA_T4_SIAP_PASANG',
        'D:\KALAWA_T4_SIAP_PASANG',
        'D:\KALAWA_T4_SIAP_PASANG\KALAWA_T4_SIAP_PASANG',
        'D:\KALAWA_T4_SIAP_PASANG(2)\KALAWA_T4_SIAP_PASANG'
    )
    foreach ($candidate in $candidates) {
        if (Test-Path -LiteralPath (Join-Path $candidate 'MANIFEST-PAKET.json') -PathType Leaf) {
            return (Resolve-Path -LiteralPath $candidate).Path
        }
    }
    throw 'Paket final privat belum ditemukan. Gunakan -PaketFinal dengan folder yang berisi MANIFEST-PAKET.json dan database.'
}
function Test-KfgPackage {
    param([string]$Paket,[object]$Provenance)
    $package = Resolve-KfgPackage $Paket
    $manifestPath = Join-Path $package 'MANIFEST-PAKET.json'
    if ((Get-KfgHash $manifestPath) -cne [string]$Provenance.backup.manifest_sha256) {
        throw 'Manifest paket berbeda dari arsip final yang ditinjau. Pilih paket R05 yang benar.'
    }
    $manifest = Read-KfgJson $manifestPath
    if ($manifest.format -ne 'KALAWA_FINAL_T4_R01' -or @($manifest.files.PSObject.Properties).Count -ne 123) {
        throw 'Format/jumlah berkas paket final tidak cocok.'
    }
    foreach ($entry in $manifest.files.PSObject.Properties) {
        if ((Get-KfgHash (Get-KfgChild $package $entry.Name)) -cne [string]$entry.Value) {
            throw "Berkas paket berubah: $($entry.Name)"
        }
    }
    $count = Test-KfgSource (Join-Path $package 'website') $Provenance.source -CekTambahan
    foreach ($entry in $Provenance.migrations.PSObject.Properties) {
        if ((Get-KfgHash (Get-KfgChild $package $entry.Name)) -cne [string]$entry.Value) {
            throw "Migrasi paket berbeda: $($entry.Name)"
        }
    }
    $restore = Read-KfgJson (Join-Path $package 'bukti/restore-paket.json')
    $fingerprint = Read-KfgJson (Join-Path $package 'database/fingerprint.json')
    $test = Read-KfgJson (Join-Path $package 'bukti/hasil-uji.json')
    if ($restore.status -ne 'PASS' -or [int]$restore.jumlah_tabel -ne 26 -or [int]$restore.jumlah_objek -ne 38 -or
        $restore.baris_dan_sha256 -ne 'identik' -or @($fingerprint.tables.PSObject.Properties).Count -ne 26 -or
        @($fingerprint.objects.PSObject.Properties).Count -ne 38) { throw 'Bukti restore paket tidak sesuai.' }
    if ($test.keterangan.versi_paket -ne 'CETAK_R05' -or [int]$test.ringkasan.PASS -ne 442 -or
        [int]$test.ringkasan.FAIL -ne 0 -or [int]$test.ringkasan.BLOCKED -ne 0) { throw 'Bukti 442 pengujian final tidak cocok.' }
    foreach ($entry in $Provenance.source.PSObject.Properties) {
        if ([string]$test.keterangan.source_hash.PSObject.Properties[$entry.Name].Value -cne [string]$entry.Value) {
            throw "Bukti pengujian menunjuk source lain: $($entry.Name)"
        }
    }
    return [pscustomobject]@{
        path = $package
        manifest_files_verified = 123
        portable_dump = (Join-Path $package 'database/kalawa-final.sql')
        portable_sha256 = (Get-KfgHash (Join-Path $package 'database/kalawa-final.sql'))
        original_dump = (Join-Path $package 'database/backup-asli.sql')
        original_sha256 = (Get-KfgHash (Join-Path $package 'database/backup-asli.sql'))
        restore_status = 'PASS'
        restore_verified_at = [string]$restore.waktu
        table_count = 26
        object_count = 38
        historical_tests = [pscustomobject]@{ PASS=442; FAIL=0; BLOCKED=0; rerun=$false }
    }
}
function Get-KfgRemoteTree {
    param([string]$Repo,[string]$Prefix)
    $lines = Invoke-KfgGit $Repo @('ls-tree','-r','origin/main','--',$Prefix)
    $map = @{}
    foreach ($line in @($lines -split '\r?\n')) {
        if ([string]::IsNullOrWhiteSpace($line)) { continue }
        if ($line -notmatch '^(100644|100755) blob ([a-f0-9]{40,64})\s+(.+)$') { throw "Entry Git tidak didukung: $line" }
        $map[$matches[3]] = $matches[2]
    }
    return $map
}
function Invoke-KfgAudit {
    param([string]$Repo,[string]$Target,[string]$Paket,[object]$Provenance)
    $repoPath = (Resolve-Path -LiteralPath $Repo).Path
    if ((Invoke-KfgGit $repoPath @('rev-parse','--show-toplevel')).Replace('\','/').TrimEnd('/') -cne
        $repoPath.Replace('\','/').TrimEnd('/')) { throw 'Pilih root repository, bukan subfolder.' }
    if ((Invoke-KfgGit $repoPath @('branch','--show-current')) -ne 'main') { throw 'Pemeriksaan ini memerlukan branch main.' }
    $origin = Invoke-KfgGit $repoPath @('remote','get-url','origin')
    if ($origin -cne 'https://github.com/mrcatfish75-debug/kalawa-service-abd.git') { throw 'Origin bukan repository Kalawa yang disepakati.' }
    $null = Invoke-KfgGit $repoPath @('fetch','--quiet','origin','main')
    $localCommit = Invoke-KfgGit $repoPath @('rev-parse','HEAD')
    $remoteCommit = Invoke-KfgGit $repoPath @('rev-parse','origin/main')
    if ($localCommit -cne $remoteCommit) { throw 'HEAD dan origin/main berbeda. Selesaikan perbedaan Git dahulu; jangan force push.' }
    $repoCount = Test-KfgSource (Join-Path $repoPath 'website') $Provenance.source -CekTambahan
    $targetPath = (Resolve-Path -LiteralPath $Target).Path
    if ((Split-Path -Leaf $targetPath) -ne 'kalawa-service-f1-korporat-uji') { throw 'Nama folder target htdocs tidak sesuai.' }
    $siteCount = Test-KfgSource $targetPath $Provenance.source -CekTambahan
    $remoteSource = Get-KfgRemoteTree $repoPath 'website'
    if ($remoteSource.Count -ne 66) { throw 'Source website pada origin/main bukan 66 berkas.' }
    foreach ($entry in $Provenance.source.PSObject.Properties) {
        $path = 'website/' + $entry.Name
        $oid = Invoke-KfgGit $repoPath @('hash-object','--no-filters','--',(Get-KfgChild $targetPath $entry.Name))
        if (-not $remoteSource.ContainsKey($path) -or $remoteSource[$path] -cne $oid) {
            throw "Source GitHub berbeda dari htdocs: $($entry.Name)"
        }
    }
    $remoteSql = Get-KfgRemoteTree $repoPath 'sql'
    if ($remoteSql.Count -ne 4) { throw 'Folder sql origin/main harus memuat empat migrasi yang ditinjau.' }
    foreach ($entry in $Provenance.migrations.PSObject.Properties) {
        $path = Get-KfgChild $repoPath $entry.Name
        if ((Get-KfgHash $path) -cne [string]$entry.Value) { throw "Migrasi lokal berubah: $($entry.Name)" }
        $oid = Invoke-KfgGit $repoPath @('hash-object','--no-filters','--',$path)
        if (-not $remoteSql.ContainsKey($entry.Name) -or $remoteSql[$entry.Name] -cne $oid) { throw "Migrasi GitHub berbeda: $($entry.Name)" }
    }
    $backup = Test-KfgPackage $Paket $Provenance
    return [pscustomobject][ordered]@{
        format = 'KALAWA_FINAL_GITHUB_R02'
        status = 'PASS'
        checked_at_wib = [DateTimeOffset]::UtcNow.ToOffset([TimeSpan]::FromHours(7)).ToString('yyyy-MM-ddTHH:mm:sszzz')
        repository = $origin
        remote_commit_checked = $remoteCommit
        source_tree = (Invoke-KfgGit $repoPath @('rev-parse','origin/main:website'))
        source_version = 'CETAK_R05'
        application_files = 66
        htdocs_path = $targetPath
        source_github_equals_htdocs = $true
        migrations_verified = 4
        backup = $backup
        live_database_queried = $false
        note = 'Verifikasi source/arsip; hasil 442 dan restore berasal dari snapshot paket. Tidak menjalankan ulang transaksi atau mengklaim backup sama dengan data live hari ini.'
    }
}
function Write-KfgEvidence {
    param([string]$Repo,[object]$Evidence)
    $folder = Join-Path $Repo 'docs'
    $jsonPath = Join-Path $folder 'BUKTI-FINAL-LOKAL.json'
    $mdPath = Join-Path $folder 'BUKTI-BACKUP.md'
    $enc = New-Object Text.UTF8Encoding($false)
    [IO.File]::WriteAllText($jsonPath,(ConvertTo-Json -InputObject $Evidence -Depth 12) + [Environment]::NewLine,$enc)
    $lines = @(
        '# Bukti lokasi backup dan kesamaan source final',
        '',
        '| Pemeriksaan | Hasil |','|---|---|',
        ('| Waktu pemeriksaan WIB | ' + $Evidence.checked_at_wib + ' |'),
        '| Source GitHub, repository lokal dan htdocs | 66 berkas CETAK_R05 identik |',
        ('| Folder website | ' + $Evidence.htdocs_path + ' |'),
        ('| Commit remote yang diperiksa | ' + $Evidence.remote_commit_checked + ' |'),
        ('| Tree source website | ' + $Evidence.source_tree + ' |'),
        '| Migrasi GitHub | Empat berkas SQL identik |',
        ('| Folder paket backup privat | ' + $Evidence.backup.path + ' |'),
        ('| Dump portabel | ' + $Evidence.backup.portable_dump + ' |'),
        ('| SHA-256 dump portabel | ' + $Evidence.backup.portable_sha256 + ' |'),
        ('| Dump asli | ' + $Evidence.backup.original_dump + ' |'),
        ('| SHA-256 dump asli | ' + $Evidence.backup.original_sha256 + ' |'),
        '| Keutuhan paket | 123 berkas manifest cocok |',
        '| Isi backup | 26 tabel dasar; 38 objek SQL total |',
        ('| Bukti full export/import | PASS; baris/hash identik; ' + $Evidence.backup.restore_verified_at + ' |'),
        '| Bukti uji pada snapshot | 442 PASS; 0 FAIL; 0 BLOCKED |',
        '',
        'Berkas SQL disimpan privat di lokasi di atas. Penilai dapat meminta salinan paket kepada kelompok atau menerima berkas melalui kanal pengumpulan resmi. Metadata ini bukan link unduhan database.',
        '',
        'Pemeriksaan ini membaca source Git yang di-fetch dari origin/main dan berkas lokal. Pemeriksaan tidak mengakses data database live, tidak mengulang 442 transaksi, serta tidak menjalankan ulang restore. Timestamp restore di atas adalah waktu pengujian arsip, bukan waktu pemeriksaan filesystem ini.',
        '',
        'Commit dokumentasi sesudah pemeriksaan dapat berbeda; tree source website tetap sama jika hanya dokumentasi/alat persiapan yang berubah. Rincian dapat dibaca pada BUKTI-FINAL-LOKAL.json.'
    )
    [IO.File]::WriteAllText($mdPath,($lines -join [Environment]::NewLine) + [Environment]::NewLine,$enc)
}
