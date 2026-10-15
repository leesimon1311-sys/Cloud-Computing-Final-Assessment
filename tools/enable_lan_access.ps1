<#
.SYNOPSIS
    Allows other devices on your Wi-Fi to reach the local deployment.

.DESCRIPTION
    Apache already listens on every network interface, but Windows Firewall
    blocks inbound connections on port 80 by default, so a phone on the same
    network cannot load the site. This adds an inbound rule scoped to the
    Private profile only - your home or campus Wi-Fi - and never to Public
    networks.

    Task B of the assessment requires evidence that the application was reached
    from another device over the local network, so this step is needed for
    those marks.

    MUST BE RUN AS ADMINISTRATOR. Right-click this file and choose
    "Run with PowerShell" from an elevated prompt, or run:

        Start-Process powershell -Verb RunAs -ArgumentList `
            '-ExecutionPolicy Bypass -File "<full path to this file>"'

.PARAMETER Remove
    Deletes the rule again. Do this once you have finished the assessment.
#>
[CmdletBinding()]
param(
    [int]$Port = 80,
    [switch]$Remove
)

$ErrorActionPreference = 'Stop'
$ruleName = 'XAMPP Apache HTTP 80 (BIT3253 local test)'

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).
    IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Host ''
    Write-Host 'This script must be run as Administrator.' -ForegroundColor Red
    Write-Host ''
    Write-Host 'Press the Start button, type "PowerShell", right-click'
    Write-Host '"Windows PowerShell" and choose "Run as administrator".'
    Write-Host 'Then run:'
    Write-Host ''
    Write-Host "  powershell -ExecutionPolicy Bypass -File `"$PSCommandPath`"" -ForegroundColor Yellow
    Write-Host ''
    exit 1
}

if ($Remove) {
    $existing = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
    if ($existing) {
        Remove-NetFirewallRule -DisplayName $ruleName
        Write-Host "[ ok ] Removed the firewall rule." -ForegroundColor Green
    } else {
        Write-Host '[note] No such rule exists; nothing to remove.'
    }
    exit 0
}

$existing = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
if ($existing) {
    Write-Host '[skip] The rule already exists.'
} else {
    New-NetFirewallRule -DisplayName $ruleName `
        -Description 'Temporary rule for the BIT3253 Cloud Computing assessment. Remove when finished.' `
        -Direction Inbound -Protocol TCP -LocalPort $Port -Action Allow -Profile Private | Out-Null
    Write-Host "[ ok ] Inbound TCP $Port allowed on Private networks." -ForegroundColor Green
}

# --- Report the address to use on the other device --------------------------

$addresses = Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object { $_.IPAddress -ne '127.0.0.1' -and $_.IPAddress -notlike '169.254.*' }

Write-Host ''
Write-Host 'Browse to one of these from a phone or laptop on the SAME Wi-Fi:' -ForegroundColor Cyan
foreach ($a in $addresses) {
    $suffix = if ($Port -eq 80) { '' } else { ":$Port" }
    Write-Host ("  http://{0}{1}/inventory/     ({2})" -f $a.IPAddress, $suffix, $a.InterfaceAlias) -ForegroundColor Yellow
}

Write-Host ''
Write-Host 'Screenshot the phone showing the application with that URL visible'
Write-Host 'in its address bar - that is the evidence for Appendix B.'
Write-Host ''
Write-Host 'Check which profile your Wi-Fi uses (it must be Private):'
Get-NetConnectionProfile | Select-Object Name, InterfaceAlias, NetworkCategory | Format-Table -AutoSize
Write-Host 'If it says Public, change it in Settings > Network & Internet >'
Write-Host 'Wi-Fi > (your network) > set the network profile type to Private.'
Write-Host ''
Write-Host 'When the assessment is finished, remove the rule again:'
Write-Host "  powershell -ExecutionPolicy Bypass -File `"$PSCommandPath`" -Remove"
Write-Host ''
