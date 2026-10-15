<#
.SYNOPSIS
    Measures and compares response times for the local and cloud deployments.

.DESCRIPTION
    Requests the same page from both environments a number of times and reports
    the minimum, mean, median, 95th percentile and maximum response time, plus
    the response size. Use the figures as the quantitative evidence for the
    performance section of the report, alongside the browser Network tab
    screenshots.

    The first cloud request after a period of inactivity includes the free-tier
    cold start, so that run is reported separately rather than being averaged
    away.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tools\measure_performance.ps1 `
        -LocalUrl "http://localhost/inventory/health.php" `
        -CloudUrl "https://your-app.onrender.com/health.php" `
        -Runs 15
#>
[CmdletBinding()]
param(
    [string]$LocalUrl = 'http://localhost/inventory/health.php',
    [string]$CloudUrl = '',
    [int]$Runs = 15,
    [string]$CsvPath = ''
)

$ErrorActionPreference = 'Stop'

function Measure-Endpoint {
    param([string]$Label, [string]$Url, [int]$Count)

    Write-Host ''
    Write-Host "$Label" -ForegroundColor Cyan
    Write-Host "  $Url"

    $samples = New-Object System.Collections.Generic.List[double]
    $bytes = 0
    $coldStart = $null
    $failures = 0

    for ($i = 1; $i -le $Count; $i++) {
        $watch = [System.Diagnostics.Stopwatch]::StartNew()
        try {
            $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 120
            $watch.Stop()
            $ms = [math]::Round($watch.Elapsed.TotalMilliseconds, 1)
            $bytes = $response.RawContentLength

            if ($i -eq 1) {
                $coldStart = $ms
                Write-Host ("  run {0,2}: {1,8} ms  (first request)" -f $i, $ms)
            } else {
                $samples.Add($ms)
                Write-Host ("  run {0,2}: {1,8} ms" -f $i, $ms)
            }
        } catch {
            $watch.Stop()
            $failures++
            Write-Host ("  run {0,2}: FAILED - {1}" -f $i, $_.Exception.Message) -ForegroundColor Red
        }

        Start-Sleep -Milliseconds 250
    }

    if ($samples.Count -eq 0) {
        Write-Host '  No successful measurements.' -ForegroundColor Red
        return $null
    }

    $sorted = $samples | Sort-Object
    $median = if ($sorted.Count % 2 -eq 1) {
        $sorted[[math]::Floor($sorted.Count / 2)]
    } else {
        ($sorted[$sorted.Count / 2 - 1] + $sorted[$sorted.Count / 2]) / 2
    }
    $p95Index = [math]::Min($sorted.Count - 1, [math]::Ceiling($sorted.Count * 0.95) - 1)

    [PSCustomObject]@{
        Environment   = $Label
        Url           = $Url
        Samples       = $sorted.Count
        FirstRequestMs = $coldStart
        MinMs         = [math]::Round(($sorted | Measure-Object -Minimum).Minimum, 1)
        MeanMs        = [math]::Round(($sorted | Measure-Object -Average).Average, 1)
        MedianMs      = [math]::Round($median, 1)
        P95Ms         = [math]::Round($sorted[$p95Index], 1)
        MaxMs         = [math]::Round(($sorted | Measure-Object -Maximum).Maximum, 1)
        ResponseBytes = $bytes
        Failures      = $failures
    }
}

Write-Host ''
Write-Host 'Response time measurement' -ForegroundColor Cyan
Write-Host ('=' * 48)
Write-Host "Runs per environment : $Runs  (the first is reported separately)"
Write-Host "Started              : $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
Write-Host "Client machine       : $env:COMPUTERNAME"

$results = @()

$local = Measure-Endpoint -Label 'Local (XAMPP)' -Url $LocalUrl -Count $Runs
if ($local) { $results += $local }

if ($CloudUrl -ne '') {
    $cloud = Measure-Endpoint -Label 'Cloud' -Url $CloudUrl -Count $Runs
    if ($cloud) { $results += $cloud }
} else {
    Write-Host ''
    Write-Host 'No -CloudUrl supplied, so only the local environment was measured.' -ForegroundColor Yellow
}

Write-Host ''
Write-Host 'Summary (milliseconds)' -ForegroundColor Cyan
Write-Host ('=' * 48)
$results | Format-Table Environment, Samples, FirstRequestMs, MinMs, MeanMs, MedianMs, P95Ms, MaxMs, ResponseBytes -AutoSize

if ($results.Count -eq 2) {
    $ratio = [math]::Round($results[1].MeanMs / $results[0].MeanMs, 1)
    Write-Host "Cloud mean response time is ${ratio}x the local mean."
    Write-Host 'The difference is network round-trip time: the local request never leaves the machine.'
}

if ($CsvPath -ne '') {
    $results | Export-Csv -Path $CsvPath -NoTypeInformation -Encoding utf8
    Write-Host ''
    Write-Host "Results written to $CsvPath"
}

Write-Host ''
