<#
.SYNOPSIS
    Replaces XAMPP's unsecured default database configuration.

.DESCRIPTION
    A fresh XAMPP installation ships with a database that anyone who can reach
    the machine can take over completely:

      * the MySQL "root" superuser has no password, on every host entry
      * the "pma" control account has no password
      * phpMyAdmin is set to auth_type = 'config', so it signs in as root
        automatically and never asks who you are
      * AllowNoPassword is true, which permits password-less sign in
      * blowfish_secret is the publicly known string "xampp"
      * leftover wildcard grants expose the "test" database to any account

    This script closes all of those. It sets a strong password on every root
    entry and on the control account, removes the test-database grants, and
    rewrites phpMyAdmin to use cookie authentication so it presents a login
    screen.

    The application itself is unaffected: it connects as the least-privilege
    "inventory_app" account, never as root.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tools\secure_mysql.ps1

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tools\secure_mysql.ps1 -RootPassword "MyChosenPassword"
#>
[CmdletBinding()]
param(
    [string]$XamppPath = 'C:\xampp',
    # Password MySQL's root account currently has ('' on a default install).
    [string]$CurrentRootPassword = '',
    # Leave blank to have a strong one generated for you.
    [string]$RootPassword = '',
    [switch]$KeepTestDatabase
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot

function New-StrongPassword {
    param([int]$Bytes = 12)
    $buffer = New-Object byte[] $Bytes
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($buffer)
    return ([System.BitConverter]::ToString($buffer) -replace '-', '').ToLower()
}

Write-Host ''
Write-Host 'Securing the local database configuration' -ForegroundColor Cyan
Write-Host ('=' * 52)

$mysql = Join-Path $XamppPath 'mysql\bin\mysql.exe'
if (-not (Test-Path $mysql)) { throw "mysql.exe was not found at $mysql" }

$connect = @('--user=root', '--host=127.0.0.1', '--protocol=TCP')
if ($CurrentRootPassword -ne '') { $connect += "--password=$CurrentRootPassword" }

& $mysql @connect --execute="SELECT 1;" | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw 'Could not connect as root. Start MySQL, and pass -CurrentRootPassword if root already has one.'
}
Write-Host '[ ok ] Connected as root'

if ($RootPassword -eq '') {
    $RootPassword = New-StrongPassword
    Write-Host '[ ok ] Generated a strong root password'
}
$pmaPassword = New-StrongPassword

# --- 1. Password every root entry ------------------------------------------

$rootHosts = (& $mysql @connect --skip-column-names --execute="SELECT host FROM mysql.user WHERE user='root';") -split "`r?`n" |
    Where-Object { $_.Trim() -ne '' }

$rootEsc = $RootPassword.Replace("'", "''")
foreach ($h in $rootHosts) {
    $h = $h.Trim()
    & $mysql @connect --execute="ALTER USER 'root'@'$h' IDENTIFIED BY '$rootEsc';" | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "Failed to set the password for root@$h" }
    Write-Host "[ ok ] Password set for root@$h"
}

# Every later statement must authenticate with the new password.
$connect = @('--user=root', '--host=127.0.0.1', '--protocol=TCP', "--password=$RootPassword")

# --- 2. Password the phpMyAdmin control account ----------------------------

$pmaEsc = $pmaPassword.Replace("'", "''")
$pmaSql = @"
CREATE USER IF NOT EXISTS 'pma'@'localhost' IDENTIFIED BY '$pmaEsc';
ALTER USER 'pma'@'localhost' IDENTIFIED BY '$pmaEsc';
GRANT SELECT, INSERT, UPDATE, DELETE ON phpmyadmin.* TO 'pma'@'localhost';
FLUSH PRIVILEGES;
"@
$pmaSql | & $mysql @connect
if ($LASTEXITCODE -ne 0) { throw 'Failed to configure the pma control account.' }
Write-Host '[ ok ] Control account "pma" given a password and scoped to the phpmyadmin database'

# --- 3. Remove the leftover test-database grants ----------------------------

if (-not $KeepTestDatabase) {
    $cleanup = @"
DELETE FROM mysql.db WHERE Db IN ('test', 'test\\_%');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.user WHERE User='';
FLUSH PRIVILEGES;
"@
    $cleanup | & $mysql @connect
    if ($LASTEXITCODE -ne 0) { throw 'Failed to remove the default test-database grants.' }
    Write-Host '[ ok ] Removed the anonymous test-database grants and the test database'
}

# --- 4. Rewrite phpMyAdmin to use cookie authentication ---------------------

$configPath = Join-Path $XamppPath 'phpMyAdmin\config.inc.php'
if (-not (Test-Path $configPath)) { throw "phpMyAdmin config not found at $configPath" }

$backupPath = "$configPath.backup-" + (Get-Date -Format 'yyyyMMdd-HHmmss')
Copy-Item $configPath $backupPath
Write-Host "[ ok ] Backed up the original config to $(Split-Path -Leaf $backupPath)"

# phpMyAdmin requires a blowfish secret of exactly 32 characters.
$blowfish = New-StrongPassword -Bytes 16

$newConfig = @"
<?php
/*
 * phpMyAdmin configuration - hardened for BIT3253 Cloud Computing.
 *
 * Changed from the XAMPP defaults:
 *   auth_type        'config' -> 'cookie'  (phpMyAdmin now asks who you are
 *                                           instead of signing in as root)
 *   AllowNoPassword  true     -> false     (password-less sign in refused)
 *   blowfish_secret  'xampp'  -> a random 32-character secret
 *   controlpass      ''       -> a generated password
 *
 * The original file was backed up alongside this one before it was replaced.
 */

/* Secret used to encrypt the cookie that stores your credentials. */
`$cfg['blowfish_secret'] = '$blowfish';

`$i = 0;
`$i++;

/* Ask for a username and password instead of signing in automatically. */
`$cfg['Servers'][`$i]['auth_type'] = 'cookie';
`$cfg['Servers'][`$i]['AllowNoPassword'] = false;

/* Refuse the root account a route in without a password. */
`$cfg['Servers'][`$i]['AllowRoot'] = true;

`$cfg['Servers'][`$i]['extension'] = 'mysqli';
`$cfg['Lang'] = '';

/* Bind to the localhost ipv4 address and tcp */
`$cfg['Servers'][`$i]['host'] = '127.0.0.1';
`$cfg['Servers'][`$i]['connect_type'] = 'tcp';

/* Sign out after 30 minutes of inactivity. */
`$cfg['LoginCookieValidity'] = 1800;

/* Control account used only for phpMyAdmin's own storage database. */
`$cfg['Servers'][`$i]['controluser'] = 'pma';
`$cfg['Servers'][`$i]['controlpass'] = '$pmaPassword';

/* Advanced phpMyAdmin features */
`$cfg['Servers'][`$i]['pmadb'] = 'phpmyadmin';
`$cfg['Servers'][`$i]['bookmarktable'] = 'pma__bookmark';
`$cfg['Servers'][`$i]['relation'] = 'pma__relation';
`$cfg['Servers'][`$i]['table_info'] = 'pma__table_info';
`$cfg['Servers'][`$i]['table_coords'] = 'pma__table_coords';
`$cfg['Servers'][`$i]['pdf_pages'] = 'pma__pdf_pages';
`$cfg['Servers'][`$i]['column_info'] = 'pma__column_info';
`$cfg['Servers'][`$i]['history'] = 'pma__history';
`$cfg['Servers'][`$i]['designer_coords'] = 'pma__designer_coords';
`$cfg['Servers'][`$i]['tracking'] = 'pma__tracking';
`$cfg['Servers'][`$i]['userconfig'] = 'pma__userconfig';
`$cfg['Servers'][`$i]['recent'] = 'pma__recent';
`$cfg['Servers'][`$i]['table_uiprefs'] = 'pma__table_uiprefs';
`$cfg['Servers'][`$i]['users'] = 'pma__users';
`$cfg['Servers'][`$i]['usergroups'] = 'pma__usergroups';
`$cfg['Servers'][`$i]['navigationhiding'] = 'pma__navigationhiding';
`$cfg['Servers'][`$i]['savedsearches'] = 'pma__savedsearches';
`$cfg['Servers'][`$i]['central_columns'] = 'pma__central_columns';
`$cfg['Servers'][`$i]['designer_settings'] = 'pma__designer_settings';
`$cfg['Servers'][`$i]['export_templates'] = 'pma__export_templates';
`$cfg['Servers'][`$i]['favorite'] = 'pma__favorite';
"@

# No byte order mark: a BOM in a PHP file sends output before the headers and
# breaks phpMyAdmin's redirect after sign in.
[System.IO.File]::WriteAllText($configPath, $newConfig, (New-Object System.Text.UTF8Encoding $false))
Write-Host '[ ok ] phpMyAdmin switched to cookie authentication'

# --- 5. Record the credentials locally --------------------------------------

$notePath = Join-Path $projectRoot 'CREDENTIALS-LOCAL.txt'
$note = @"
LOCAL DATABASE CREDENTIALS - KEEP PRIVATE
=========================================
Written $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') by tools\secure_mysql.ps1

This file is listed in .gitignore and is NOT part of the repository.
Do NOT include it, or any value from it, in your report or screenshots.

phpMyAdmin / MySQL superuser
    URL       http://localhost/phpmyadmin/
    Username  root
    Password  $RootPassword

phpMyAdmin control account (used internally by phpMyAdmin, not by you)
    Username  pma
    Password  $pmaPassword

The application does NOT use either account. It connects as 'inventory_app'
with the credentials in .env, which hold only SELECT, INSERT, UPDATE and
DELETE on inventory_db.

If you lose the root password, recover it by stopping MySQL and starting it
once with:  mysqld.exe --skip-grant-tables
then connect and run:  ALTER USER 'root'@'localhost' IDENTIFIED BY 'new';
"@
[System.IO.File]::WriteAllText($notePath, $note, (New-Object System.Text.UTF8Encoding $false))

Write-Host ''
Write-Host 'Database hardening complete.' -ForegroundColor Green
Write-Host ''
Write-Host '  phpMyAdmin now asks for a username and password at:'
Write-Host '    http://localhost/phpmyadmin/' -ForegroundColor Yellow
Write-Host ''
Write-Host "    Username : root"
Write-Host "    Password : $RootPassword" -ForegroundColor Yellow
Write-Host ''
Write-Host '  Saved to CREDENTIALS-LOCAL.txt (git-ignored). Keep it private and'
Write-Host '  keep it out of every screenshot.'
Write-Host ''
Write-Host '  Note: tools\setup_local.ps1 and tools\backup_local.ps1 now need'
Write-Host '  -RootPassword "<the password above>".'
Write-Host ''
