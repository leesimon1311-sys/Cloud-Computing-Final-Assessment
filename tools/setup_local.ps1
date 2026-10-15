<#
.SYNOPSIS
    Prepares the local XAMPP environment for the Inventory Management System.

.DESCRIPTION
    Creates the inventory database, creates a dedicated least-privilege MySQL
    account (so the application never runs as root), imports the schema and
    writes a .env file containing the generated credentials.

    Apache and MySQL must already be started from the XAMPP Control Panel.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tools\setup_local.ps1

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tools\setup_local.ps1 -RootPassword "secret"
#>
[CmdletBinding()]
param(
    [string]$XamppPath   = 'C:\xampp',
    [string]$RootUser    = 'root',
    [string]$RootPassword = '',
    [string]$Database    = 'inventory_db',
    [string]$AppUser     = 'inventory_app',
    [string]$AppPassword = ''
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot

Write-Host ''
Write-Host 'Inventory Management System - local setup' -ForegroundColor Cyan
Write-Host ('=' * 48)

# --- 1. Locate the MySQL client --------------------------------------------

$mysql = Join-Path $XamppPath 'mysql\bin\mysql.exe'
if (-not (Test-Path $mysql)) {
    throw "mysql.exe was not found at $mysql. Pass -XamppPath if XAMPP is installed elsewhere."
}
Write-Host "[ ok ] Found MySQL client at $mysql"

# --- 2. Build the argument list --------------------------------------------

$baseArgs = @("--user=$RootUser", '--host=127.0.0.1', '--protocol=TCP')
if ($RootPassword -ne '') { $baseArgs += "--password=$RootPassword" }

# Confirm the server is reachable before changing anything.
& $mysql @baseArgs --execute="SELECT VERSION();" | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw 'Could not connect to MySQL. Start MySQL from the XAMPP Control Panel, and pass -RootPassword if the root account has one.'
}
Write-Host '[ ok ] Connected to the MySQL server'

# --- 3. Generate an application password when one was not supplied ----------

if ($AppPassword -eq '') {
    $bytes = New-Object byte[] 12
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $AppPassword = ([System.BitConverter]::ToString($bytes) -replace '-', '').ToLower()
    Write-Host '[ ok ] Generated a random password for the application account'
}

# --- 4. Create the database and the least-privilege account -----------------

$escaped = $AppPassword.Replace("'", "''")
$setup = @"
CREATE DATABASE IF NOT EXISTS $Database CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER IF NOT EXISTS '$AppUser'@'localhost' IDENTIFIED BY '$escaped';
ALTER USER '$AppUser'@'localhost' IDENTIFIED BY '$escaped';
GRANT SELECT, INSERT, UPDATE, DELETE ON $Database.* TO '$AppUser'@'localhost';
FLUSH PRIVILEGES;
"@

$setup | & $mysql @baseArgs
if ($LASTEXITCODE -ne 0) { throw 'Failed to create the database or the application account.' }
Write-Host "[ ok ] Database '$Database' and account '$AppUser' are ready"

# --- 5. Import the schema (needs administrator rights) ----------------------

$schema = Join-Path $projectRoot 'sql\schema_mysql.sql'
if (-not (Test-Path $schema)) { throw "Schema file not found at $schema" }

Get-Content $schema -Raw | & $mysql @baseArgs $Database
if ($LASTEXITCODE -ne 0) { throw 'Failed to import sql\schema_mysql.sql.' }
Write-Host '[ ok ] Schema imported (users, categories, products, activity_log)'

# --- 6. Write the .env file -------------------------------------------------

$envPath = Join-Path $projectRoot '.env'

if (Test-Path $envPath) {
    $backup = "$envPath.backup-" + (Get-Date -Format 'yyyyMMdd-HHmmss')
    Copy-Item $envPath $backup
    Write-Host "[note] Existing .env backed up to $(Split-Path -Leaf $backup)"
}

$installToken = [System.Guid]::NewGuid().ToString('N') + [System.Guid]::NewGuid().ToString('N')

$envContent = @"
APP_ENV=local
APP_NAME="Inventory Management System"
APP_TZ=Asia/Kuala_Lumpur
APP_CURRENCY=RM

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=$Database
DB_USER=$AppUser
DB_PASS=$AppPassword

DATABASE_URL=

INSTALL_TOKEN=$installToken
"@

# Written without a byte order mark: a BOM would become part of the first key.
[System.IO.File]::WriteAllText($envPath, $envContent, (New-Object System.Text.UTF8Encoding $false))
Write-Host '[ ok ] Wrote .env (this file is git-ignored - never commit or screenshot it)'

# --- 7. Next steps ----------------------------------------------------------

Write-Host ''
Write-Host 'Local database setup is complete.' -ForegroundColor Green
Write-Host ''
Write-Host 'Next steps:'
Write-Host '  1. Make sure the project folder is reachable by Apache (see docs\DEPLOYMENT_GUIDE.md).'
Write-Host '  2. Load the sample data and create your administrator account by opening:'
Write-Host ''
Write-Host "     http://localhost/inventory/install.php?token=$installToken" -ForegroundColor Yellow
Write-Host ''
Write-Host '  3. Save the administrator password it shows you, then sign in at'
Write-Host '     http://localhost/inventory/'
Write-Host ''
Write-Host 'The installer token is also stored in your .env file.'
Write-Host ''
