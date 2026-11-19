[CmdletBinding()]
param(
 [string]$Target='C:\xampp\htdocs\kalawa-service-f1-korporat-uji',
 [string]$Php='C:\xampp\php\php.exe',
 [string]$AdminUser='root',
 [switch]$DenganPassword,
 [string]$HasilRoot='',
 [switch]$Jalankan
)
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
$scriptDir=$PSScriptRoot
if([string]::IsNullOrWhiteSpace($scriptDir)){$scriptDir=Split-Path -Parent $MyInvocation.MyCommand.Path}
if([string]::IsNullOrWhiteSpace($scriptDir)){throw 'Folder skrip tidak dapat ditentukan. Jalankan berkas Uji-Semua-Fitur.ps1 dengan -File.'}
if([string]::IsNullOrWhiteSpace($HasilRoot)){$HasilRoot=Join-Path $scriptDir 'hasil'}
$Target=(Resolve-Path -LiteralPath $Target).Path.TrimEnd('\','/')
$Php=(Resolve-Path -LiteralPath $Php).Path
if((Split-Path -Leaf $Target) -ne 'kalawa-service-f1-korporat-uji'){throw 'Target bukan folder website korporat uji.'}
$htdocs=Split-Path $Target -Parent
$HasilRoot=[IO.Path]::GetFullPath($HasilRoot).TrimEnd('\','/')
if($HasilRoot -eq $htdocs -or $HasilRoot.StartsWith($htdocs+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)){throw 'Simpan hasil di luar htdocs karena berisi backup data.'}
if($Jalankan -and -not(Test-Path -LiteralPath $HasilRoot)){New-Item -ItemType Directory -Path $HasilRoot -Force | Out-Null}
$helper=Join-Path $scriptDir 'tools\jalankan.php'
foreach($file in (Get-ChildItem -LiteralPath (Join-Path $scriptDir 'tools') -Filter '*.php')){
 $lint=& $Php '-l' $file.FullName 2>&1
 if($LASTEXITCODE -ne 0){throw "Lint gagal: $($lint -join ' ')"}
}
$runDir=Join-Path $HasilRoot ('uji_'+(Get-Date -Format 'yyyyMMdd_HHmmss_fff'))
$plain='';$ptr=[IntPtr]::Zero;$secret=$null
try{
 if($Jalankan -and $DenganPassword){
  $secret=Read-Host 'Password admin MariaDB (bukan password website)' -AsSecureString
  $ptr=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
  $plain=[Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)
 }
 $mode=if($Jalankan){'jalankan'}else{'periksa'}
 $payload=[ordered]@{target=$Target;php=$Php;mode=$mode;admin_user=$AdminUser;admin_password=$plain;run_dir=$runDir}
 $json=ConvertTo-Json -InputObject $payload -Compress
 $encoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($json))
 # UTF-8 lewat stdin; tidak memakai password dalam argumen proses atau berkas.
 $encoded | & $Php $helper
 $code=$LASTEXITCODE
 if($Jalankan -and (Test-Path -LiteralPath $runDir)){Set-Content -LiteralPath (Join-Path $HasilRoot 'HASIL-TERAKHIR.txt') -Value $runDir -Encoding UTF8;Write-Output "Buka hasil: $runDir\ringkasan.md"}
 if($code -ne 0){throw "Uji belum lulus seluruhnya (exit $code). Baca FAIL/BLOCKED dan petunjuk BACA-DULU.md; jangan menganggap seluruh fitur sudah PASS."}
 if(-not $Jalankan){Write-Output 'Untuk menjalankan transaksi pada salinan: ulangi dengan -Jalankan.'}
}finally{
 if($ptr -ne [IntPtr]::Zero){[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)}
 $plain=$null;$secret=$null;$payload=$null;$json=$null;$encoded=$null
}
