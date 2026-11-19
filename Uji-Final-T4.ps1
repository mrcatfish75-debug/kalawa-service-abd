[CmdletBinding()]
param([string]$Target='C:\xampp\htdocs\kalawa-service-f1-korporat-uji',[string]$Php='C:\xampp\php\php.exe',[string]$AdminUser='root',[string]$HasilRoot='',[switch]$DenganPassword,[switch]$Jalankan)
. (Join-Path $PSScriptRoot 'Common-T4.ps1')
$Target=(Resolve-Path -LiteralPath $Target).Path
$HasilRoot=Get-KfOutputRoot $HasilRoot $PSScriptRoot $Target
& (Join-Path $PSScriptRoot 'uji\Uji-Semua-Fitur.ps1') -Target $Target -Php $Php -AdminUser $AdminUser -HasilRoot $HasilRoot -DenganPassword:$DenganPassword -Jalankan:$Jalankan
