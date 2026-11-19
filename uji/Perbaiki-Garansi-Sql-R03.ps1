[CmdletBinding()]
param([string]$Mysql='C:\xampp\mysql\bin\mysql.exe',[int]$Port=3306,[string]$AdminUser='root',[switch]$DenganPassword,[string]$BackupRoot='D:\KALAWA_BACKUP_R03',[switch]$Terapkan)
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
if(-not(Test-Path -LiteralPath $Mysql)){throw 'mysql.exe tidak ditemukan.'}
if($Port -lt 1 -or $Port -gt 65535){throw 'Port tidak valid.'}
if(-not $Terapkan){Write-Output 'Belum mengubah database. Skrip ini memperbaiki fungsi status garansi; tambahkan -Terapkan setelah membaca panduan.';return}
$schema='kalawa_service_f1_g1_clone_20261110_174035_33f8f960'
$common=@('--protocol=TCP','--host=127.0.0.1',"--port=$Port","--user=$AdminUser",'--default-character-set=utf8mb4',"--database=$schema")
if($DenganPassword){$common+='--password'}
$xmlText=(& $Mysql @common '--xml' '--execute=SHOW CREATE FUNCTION fn_status_garansi_wo') -join [Environment]::NewLine
if($LASTEXITCODE -ne 0){throw 'Definisi fungsi lama tidak dapat dibaca; tidak ada migrasi dijalankan.'}
[xml]$xml=$xmlText
$node=$xml.SelectSingleNode("//field[@name='Create Function']")
if($null -eq $node -or $node.InnerText -notmatch 'fn_status_garansi_wo'){throw 'Definisi fungsi tidak sesuai.'}
$folder=Join-Path $BackupRoot ('garansi_'+(Get-Date -Format 'yyyyMMdd_HHmmss_fff'))
New-Item -ItemType Directory -Path $folder -Force | Out-Null
$old=$node.InnerText -replace '^CREATE\s','CREATE OR REPLACE '
$restore=Join-Path $folder 'Pulihkan-Fungsi-Garansi.sql'
$utf8=New-Object Text.UTF8Encoding($false)
[IO.File]::WriteAllText($restore,"USE ``$schema``;`nDELIMITER `$$`n$old`$$`nDELIMITER ;`n",$utf8)
if((Get-Item -LiteralPath $restore).Length -lt 100){throw 'Backup definisi fungsi belum lengkap.'}
$sql=(Join-Path $PSScriptRoot 'sql\Garansi-Pembayaran-R03.sql').Replace('\','/')
& $Mysql @common '--abort-source-on-error' "--execute=source $sql"
if($LASTEXITCODE -ne 0){throw "Migrasi fungsi gagal. Definisi lama ada di $restore"}
Write-Output "Fungsi garansi diperbarui. Backup definisi lama: $restore"
Write-Output 'Periksa kembali hasil garansi. Pemulihan source website tidak memulihkan fungsi SQL; gunakan file backup fungsi bila perlu.'
