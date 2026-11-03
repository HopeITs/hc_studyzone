# Attach a COPY of a legacy DBRestaurantProV5 mdf/ldf pair to SQL Server Express.
# The originals are never touched: files are copied to C:\LegacyData\<Name>\ first.
#
# Usage (regular PowerShell, no admin needed once SQL Server runs and your
# Windows user is sysadmin on the instance):
#   .\scripts\attach-legacy-db.ps1 -Mdf "D:\backup\Center1.mdf" -Name LegacyCenter1
#   .\scripts\attach-legacy-db.ps1 -Mdf "D:\backup\Center1.mdf" -Ldf "D:\backup\Center1_log.ldf" -Name LegacyCenter2
#
# Then: php artisan finance:legacy-schema LegacyCenter1
param(
    [Parameter(Mandatory = $true)][string]$Mdf,
    [string]$Ldf,
    [Parameter(Mandatory = $true)][string]$Name,
    [string]$Instance = 'localhost\SQLEXPRESS'
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $Mdf)) { throw "ملف mdf غير موجود: $Mdf" }
if ($Ldf -and -not (Test-Path $Ldf)) { throw "ملف ldf غير موجود: $Ldf" }
if ($Name -notmatch '^[A-Za-z0-9_]+$') { throw 'اسم قاعدة البيانات: أحرف لاتينية وأرقام و _ فقط.' }

# 1. Work on copies, never the originals.
$workDir = "C:\LegacyData\$Name"
New-Item -ItemType Directory -Force -Path $workDir | Out-Null
$mdfCopy = Join-Path $workDir ([IO.Path]::GetFileName($Mdf))
Copy-Item $Mdf $mdfCopy -Force
Write-Host "نسخ mdf إلى: $mdfCopy"

$ldfCopy = $null
if ($Ldf) {
    $ldfCopy = Join-Path $workDir ([IO.Path]::GetFileName($Ldf))
    Copy-Item $Ldf $ldfCopy -Force
    Write-Host "نسخ ldf إلى: $ldfCopy"
}

# SQL Server's service account must be able to read the copies.
icacls $workDir /grant 'NT Service\MSSQL$SQLEXPRESS:(OI)(CI)F' /T | Out-Null
icacls $workDir /grant 'SYSTEM:(OI)(CI)F' /T | Out-Null

# 2. Attach (read-only source for the import). Without an ldf SQL Server
#    rebuilds the log (ATTACH_REBUILD_LOG).
$files = "(FILENAME = N'$mdfCopy')"
if ($ldfCopy) { $files += ", (FILENAME = N'$ldfCopy')" }
$attach = if ($ldfCopy) { 'FOR ATTACH' } else { 'FOR ATTACH_REBUILD_LOG' }

$sql = @"
IF DB_ID(N'$Name') IS NOT NULL
BEGIN
    PRINT N'قاعدة البيانات $Name ملحقة أصلاً — لا شيء لفعله.';
END
ELSE
BEGIN
    CREATE DATABASE [$Name] ON $files $attach;
    ALTER DATABASE [$Name] SET READ_ONLY;
    PRINT N'تم الإلحاق للقراءة فقط: $Name';
END
"@

sqlcmd -S $Instance -E -b -Q $sql
if ($LASTEXITCODE -ne 0) { throw 'فشل الإلحاق — راجع رسالة sqlcmd أعلاه.' }

Write-Host "`nالخطوة التالية: php artisan finance:legacy-schema $Name" -ForegroundColor Green
