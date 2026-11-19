[CmdletBinding()]
param()
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$repoRoot = $PSScriptRoot
if ([string]::IsNullOrWhiteSpace($repoRoot)) {
    $repoRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
}
if ([string]::IsNullOrWhiteSpace($repoRoot)) { throw 'Jalankan berkas ini dengan powershell.exe -File.' }
$manifest = Get-Content -LiteralPath (Join-Path $repoRoot 'manifest-final.json') -Raw | ConvertFrom-Json
$entries = @($manifest.files)
if ($manifest.version -ne 'CETAK_R05' -or $entries.Count -ne 66) { throw 'Manifest bukan CETAK_R05 dengan 66 berkas.' }
$webRoot = Join-Path $repoRoot 'website'
$expected = @()
foreach ($entry in $entries) {
    $relative = [string]$entry.path
    if ($relative -match '(^|/)\.\.(/|$)' -or $relative -match '^[\\/]') { throw 'Path manifest tidak sah.' }
    $expected += $relative
    $path = Join-Path $webRoot $relative
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { throw "Source hilang: $relative" }
    if ((Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash -ine [string]$entry.after) {
        throw "Source berbeda dari R05: $relative. Periksa perubahan, jangan mengganti hash hanya agar lolos."
    }
}
$actual = @(Get-ChildItem -LiteralPath $webRoot -Recurse -File -Force)
if ($actual.Count -ne 66) { throw 'Jumlah source berbeda atau ada berkas tambahan di website/.' }
foreach ($file in $actual) {
    $relative = $file.FullName.Substring($webRoot.Length + 1).Replace('\','/')
    if ($expected -cnotcontains $relative) { throw "Berkas di luar manifest: $relative" }
}
$migrations = @('001_korporat.sql','002_penerimaan_kas.sql','003_garansi_lunas.sql','004_audit_terpadu.sql')
foreach ($name in $migrations) {
    if (-not (Test-Path -LiteralPath (Join-Path (Join-Path $repoRoot 'sql') $name) -PathType Leaf)) {
        throw "Migrasi hilang: $name"
    }
}
$allowedSql = @('sql/001_korporat.sql','sql/002_penerimaan_kas.sql','sql/003_garansi_lunas.sql','sql/004_audit_terpadu.sql',
    'uji/sql/Garansi-Pembayaran-R03.sql','uji/sql/Izin-Supplier-Tambahan.sql','uji/tools/izin-template.sql', 'demo/schema.sql')
$all = @(Get-ChildItem -LiteralPath $repoRoot -Recurse -File -Force | Where-Object { $_.FullName -notmatch '[\\/]\.git[\\/]' })
foreach ($file in $all) {
    $relative = $file.FullName.Substring($repoRoot.Length + 1).Replace('\','/')
    if ($relative -match '(^|/)(hasil|bukti|database|backup|backups|sessions|logs)/' -or
        $file.Name -match '^(\.env($|\.)|kalawa-korporat-uji-config\.php$|kalawa-final-t4-state\.json$)' -or
        $file.Extension -match '^\.(zip|7z|rar|bak|backup|log|pdf|mp4|mov|pem|key|p12|pfx|db|sqlite)$') {
        throw "Berkas privat/hasil di folder repo: $relative. Simpan di luar repository."
    }
    if ($file.Extension -ieq '.sql' -and $allowedSql -cnotcontains $relative) {
        throw "SQL di luar migrasi/template yang ditinjau: $relative"
    }
}
Write-Output 'PASS: 66 berkas aplikasi identik dengan manifest CETAK_R05.'
Write-Output 'PASS: 4 migrasi utama tersedia; SQL tambahan hanya template uji yang ditinjau.'
Write-Output 'PASS: Tidak ditemukan nama/path berkas privat yang dilarang.'
Write-Output 'Pemeriksaan ini tidak menjalankan transaksi aplikasi dan tidak mendeteksi semua kemungkinan rahasia baru.'

$demoManifestPath=Join-Path $repoRoot 'demo/manifest-demo.json'
if(-not(Test-Path -LiteralPath $demoManifestPath -PathType Leaf)){throw 'Manifest demo publik tidak ditemukan.'}
if(Test-Path -LiteralPath $demoManifestPath -PathType Leaf){
    $demo=Get-Content -LiteralPath $demoManifestPath -Raw -Encoding UTF8 | ConvertFrom-Json
    if($demo.format -ne 'KALAWA_DEMO_PUBLIK_R03' -or $demo.application_version -ne 'CETAK_R05'){
        throw 'Manifest demo publik tidak sesuai.'
    }
    foreach($entry in $demo.files.PSObject.Properties){
        $relative=[string]$entry.Name
        if($relative -match '(^|/)\.\.(/|$)|^[\\/]|^[A-Za-z]:|\\'){throw 'Path manifest demo tidak sah.'}
        $file=Join-Path $repoRoot $relative
        if(-not(Test-Path -LiteralPath $file -PathType Leaf)){throw ('Berkas demo hilang: '+$relative)}
        if((Get-FileHash -LiteralPath $file -Algorithm SHA256).Hash -ine [string]$entry.Value){
            throw ('Berkas demo berubah: '+$relative)
        }
    }
    Write-Output 'PASS: Schema dan alat instalasi demo publik sesuai manifest.'
}
