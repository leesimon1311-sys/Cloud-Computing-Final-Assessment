<#
.SYNOPSIS
    Creates a timestamped SQL dump of the local inventory database.

.DESCRIPTION
    Demonstrates the manual backup procedure that the local environment
    requires, in contrast to the automated daily backups the cloud provider
    performs on its managed database.

    Dumps are written to backups\ which is excluded from version control,
    because a dump contains every password hash in the users table.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tools\backup_local.ps1
#>
[CmdletBinding()]
param(
    [string]$XamppPath    = 'C:\xampp',
    [string]$RootUser     = 'root',
    [string]$RootPassword = '',
    [string]$Database     = 'inventory_db'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot

$dump = Join-Path $XamppPath 'mysql\bin\mysqldump.exe'
if (-not (Test-Path $dump)) {
    throw "mysqldump.exe was not found at $dump."
}

$backupDir = Join-Path $projectRoot 'backups'
if (-not (Test-Path $backupDir)) {
    New-Item -ItemType Directory -Path $backupDir | Out-Null
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$target = Join-Path $backupDir "$Database-$stamp.sql"

$dumpArgs = @("--user=$RootUser", '--host=127.0.0.1', '--protocol=TCP', '--single-transaction', '--routines', '--events')
if ($RootPassword -ne '') { $dumpArgs += "--password=$RootPassword" }
$dumpArgs += $Database

& $dump @dumpArgs | Out-File -FilePath $target -Encoding utf8
if ($LASTEXITCODE -ne 0) { throw 'mysqldump failed.' }

$size = [math]::Round((Get-Item $target).Length / 1KB, 1)

Write-Host ''
Write-Host 'Backup complete.' -ForegroundColor Green
Write-Host "  File : $target"
Write-Host "  Size : $size KB"
Write-Host "  Time : $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
Write-Host ''
Write-Host 'Restore with:'
Write-Host "  C:\xampp\mysql\bin\mysql.exe --user=root $Database < `"$target`""
Write-Host ''
Write-Host 'Note: this dump contains password hashes. Keep it private and do not' -ForegroundColor Yellow
Write-Host 'paste its contents into the report.' -ForegroundColor Yellow
Write-Host ''
