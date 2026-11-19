[CmdletBinding()]
param([string]$Mysql='C:\xampp\mysql\bin\mysql.exe',[int]$Port=3306,[string]$AdminUser='root',[switch]$DenganPassword,[switch]$Terapkan)
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
if(-not(Test-Path -LiteralPath $Mysql -PathType Leaf)){throw 'mysql.exe tidak ditemukan.'}
if($Port -lt 1 -or $Port -gt 65535){throw 'Port tidak valid.'}
if(-not $Terapkan){Write-Output 'Belum mengubah izin. -Terapkan hanya menambah INSERT dan DELETE pada supplier_suku_cadang untuk akun koneksi website uji.';return}
$schema='kalawa_service_f1_g1_clone_20261110_174035_33f8f960'
$common=@('--protocol=TCP','--host=127.0.0.1',"--port=$Port","--user=$AdminUser",'--default-character-set=utf8mb4',"--database=$schema")
if($DenganPassword){$common+='--password'}
$sql=(Join-Path $PSScriptRoot 'sql\Izin-Supplier-Tambahan.sql').Replace('\','/')
& $Mysql @common '--abort-source-on-error' "--execute=source $sql"
if($LASTEXITCODE -ne 0){throw 'Tambahan izin supplier gagal. Akun service harus sudah ada; cek MySQL/port/password admin.'}
Write-Output 'Izin pemetaan supplier ditambahkan. Ulangi Uji-Semua-Fitur.ps1 -Jalankan untuk memverifikasi.'
