[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$PaketFinal,
    [string]$Target='C:\xampp\htdocs\kalawa-service-f1-korporat-uji',
    [string]$Php='C:\xampp\php\php.exe',
    [ValidateRange(1,65535)][int]$Port=3306,
    [string]$AdminUser='root',
    [switch]$DenganPassword,
    [switch]$Pasang
)
$ErrorActionPreference='Stop'
. (Join-Path $PSScriptRoot 'tools/github_final_r02.ps1')
$provenance=Read-KfgJson (Join-Path $PSScriptRoot 'docs/PROVENANSI-FINAL-R05.json')
$count=Test-KfgSource -Root (Join-Path $PSScriptRoot 'website') -Expected $provenance.source -CekTambahan
$backup=Test-KfgPackage -Paket $PaketFinal -Provenance $provenance
foreach($entry in $provenance.migrations.PSObject.Properties){
    if((Get-KfgHash (Get-KfgChild $PSScriptRoot $entry.Name)) -cne [string]$entry.Value){throw ('Migrasi clone berubah: '+$entry.Name)}
}
if(-not(Test-Path -LiteralPath $Php -PathType Leaf)){throw 'PHP XAMPP tidak ditemukan; periksa -Php.'}
Write-Output 'SOURCE CLONE DAN PAKET BACKUP COCOK: 66 berkas R05; paket 123 berkas terverifikasi.'
$parameters=@{Target=$Target;Php=$Php;Port=$Port;AdminUser=$AdminUser;DenganPassword=$DenganPassword;Pasang=$Pasang}
& (Join-Path $backup.path 'Pasang-Baru-T4.ps1') @parameters
if($Pasang){
    $count=Test-KfgSource -Root $Target -Expected $provenance.source -CekTambahan
    Write-Output 'PASS: Source terpasang identik dengan 66 berkas pada clone R05.'
}else{
    Write-Output 'Pemeriksaan selesai. Untuk memasang pada XAMPP baru/tujuan kosong, ulangi dengan -Pasang.'
}
