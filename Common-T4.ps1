Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
function Invoke-KfPhp {
 param([string]$Php,[string]$Helper,[hashtable]$Payload,[switch]$DenganPassword)
 $ptr=[IntPtr]::Zero;$plain='';$secret=$null
 try{
  if($DenganPassword){$secret=Read-Host 'Password admin MariaDB (bukan login website)' -AsSecureString;$ptr=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret);$plain=[Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)}
  $Payload.admin_password=$plain
  $encoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes((ConvertTo-Json -InputObject $Payload -Compress -Depth 12)))
  $encoded | & $Php $Helper
  if($LASTEXITCODE -ne 0){throw 'Proses dihentikan. Baca pesan DIHENTIKAN di atas; jangan menganggap tindakan selesai.'}
 }finally{if($ptr -ne [IntPtr]::Zero){[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)};$plain=$null;$secret=$null;$encoded=$null;$Payload.admin_password=$null}
}
function Get-KfOutputRoot {
 param([string]$Root,[string]$ScriptDir,[string]$Target)
 if([string]::IsNullOrWhiteSpace($Root)){$Root=Join-Path $ScriptDir 'hasil'}
 $full=[IO.Path]::GetFullPath($Root).TrimEnd('\','/');$htdocs=(Split-Path $Target -Parent).TrimEnd('\','/')
 if($full -eq $htdocs -or $full.StartsWith($htdocs+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)){throw 'Hasil harus di luar htdocs.'}
 return $full
}
function Test-KfPhpBuild {
 param([string]$Php,[string]$Target,[string]$ScriptDir)
 $files=@(Get-ChildItem -LiteralPath (Join-Path $Target 'app') -Filter '*.php')+@(Get-ChildItem -LiteralPath (Join-Path $Target 'panel') -Filter '*.php')+@(Get-ChildItem -LiteralPath $Target -Filter '*.php')+@(Get-ChildItem -LiteralPath (Join-Path $ScriptDir 'tools') -Filter '*.php')
 foreach($file in $files){$out=& $Php '-l' $file.FullName 2>&1;if($LASTEXITCODE -ne 0){throw "Build PHP gagal: $($file.FullName) $($out -join ' ')"}}
 Write-Output ("BUILD PHP PASS: {0} file" -f $files.Count)
}
