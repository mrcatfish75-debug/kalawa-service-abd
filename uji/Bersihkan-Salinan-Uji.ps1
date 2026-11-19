[CmdletBinding()]
param(
 [Parameter(Mandatory=$true)][string]$Sesi,
 [string]$Php='C:\xampp\php\php.exe',
 [string]$AdminUser='root',
 [switch]$DenganPassword,
 [switch]$Hapus
)
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
$Sesi=(Resolve-Path -LiteralPath $Sesi).Path
$Php=(Resolve-Path -LiteralPath $Php).Path
$plain='';$ptr=[IntPtr]::Zero;$secret=$null
try{
 if($DenganPassword){$secret=Read-Host 'Password admin MariaDB' -AsSecureString;$ptr=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret);$plain=[Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)}
 $mode=if($Hapus){'hapus'}else{'periksa'}
 $payload=@{session=$Sesi;admin_user=$AdminUser;admin_password=$plain;mode=$mode}
 $json=ConvertTo-Json -InputObject $payload -Compress
 $encoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($json))
 $encoded | & $Php (Join-Path $PSScriptRoot 'tools\bersihkan.php')
 if($LASTEXITCODE -ne 0){throw 'Pembersihan salinan gagal; sumber tidak boleh dihapus.'}
}finally{
 if($ptr -ne [IntPtr]::Zero){[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)}
 $plain=$null;$secret=$null;$payload=$null;$json=$null;$encoded=$null
}
