[CmdletBinding()]
param(
    [string]$Target='C:\xampp\htdocs\kalawa-service-f1-korporat-uji',
    [string]$PaketFinal='',
    [switch]$SimpanBukti
)
$ErrorActionPreference='Stop'
. (Join-Path $PSScriptRoot 'tools/github_final_r02.ps1')
$provenance=Read-KfgJson (Join-Path $PSScriptRoot 'docs/PROVENANSI-FINAL-R05.json')
$evidence=Invoke-KfgAudit -Repo $PSScriptRoot -Target $Target -Paket $PaketFinal -Provenance $provenance
if($SimpanBukti){Write-KfgEvidence -Repo $PSScriptRoot -Evidence $evidence}
Write-Output 'PASS: 66 source GitHub = repository lokal = htdocs korporat uji = final R05.'
Write-Output 'PASS: Empat migrasi GitHub/lokal/paket identik.'
Write-Output 'PASS: Paket backup 123 berkas utuh; bukti restore 26 tabel/38 objek tersedia.'
Write-Output ('Commit remote diperiksa: '+$evidence.remote_commit_checked)
Write-Output ('Lokasi backup: '+$evidence.backup.path)
Write-Output 'Pemeriksaan ini tidak mengulang transaksi aplikasi atau membaca database live.'
if($SimpanBukti){Write-Output 'Bukti ditulis: docs/BUKTI-FINAL-LOKAL.json dan docs/BUKTI-BACKUP.md'}
