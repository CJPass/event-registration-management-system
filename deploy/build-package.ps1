# deploy/build-package.ps1
# ------------------------------------------------------------------
# Copies ONLY the files the live site needs into deploy\htdocs\ and
# zips them as deploy\event-registration-htdocs.zip.
# Upload the zip with the InfinityFree File Manager and extract it in htdocs
# (or upload the contents of deploy\htdocs\ folder by folder).
#
# Left out on purpose: tests\, database\, README.md, test_connection.php,
# deploy\, and any config.production.php (create that one on the server).
#
# Run from PowerShell in the project folder:
#   powershell -ExecutionPolicy Bypass -File deploy\build-package.ps1
# ------------------------------------------------------------------
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$out  = Join-Path $PSScriptRoot 'htdocs'
$zip  = Join-Path $PSScriptRoot 'event-registration-htdocs.zip'

if (Test-Path $out) { Remove-Item $out -Recurse -Force }
if (Test-Path $zip) { Remove-Item $zip -Force }
New-Item -ItemType Directory -Path $out | Out-Null

$include = @('.htaccess', 'index.php', 'event.php', 'register.php', 'confirmation.php',
             'admin', 'assets', 'config', 'includes')
foreach ($item in $include) {
    Copy-Item (Join-Path $root $item) -Destination $out -Recurse
}

# Never ship real credentials from this PC
Remove-Item (Join-Path $out 'config\config.production.php') -ErrorAction SilentlyContinue

# tar.exe (built into Windows 10/11) writes zip paths with "/" so they extract
# correctly on the Linux server (Compress-Archive in PowerShell 5.1 does not).
Push-Location $out
try { tar.exe -a -c -f $zip * } finally { Pop-Location }   # "*" includes .htaccess here

Write-Host "Package folder: $out"
Write-Host "Zip file:       $zip"
