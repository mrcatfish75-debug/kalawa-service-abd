[CmdletBinding()]
param(
    [string]$Target='C:\xampp\htdocs\kalawa-service-f1-korporat-uji',
    [string]$Php='C:\xampp\php\php.exe',
    [ValidateRange(1,65535)][int]$Port=3306,
    [string]$AdminUser='root',
    [switch]$DenganPassword,
    [switch]$Pasang
)
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
if(-not(Test-Path -LiteralPath $Php -PathType Leaf)){throw 'PHP XAMPP tidak ditemukan. Periksa -Php.'}
. (Join-Path $PSScriptRoot 'Common-T4.ps1')
$mode=if($Pasang){'pasang'}else{'periksa'}
$payload=@{target=$Target;port=$Port;admin_user=$AdminUser;mode=$mode}
Invoke-KfPhp -Php $Php -Helper (Join-Path $PSScriptRoot 'tools/install_demo_r03.php') -Payload $payload -DenganPassword:$DenganPassword
