$ErrorActionPreference = 'Stop'
$storeRoot = $PSScriptRoot
$storePhp = Join-Path $storeRoot '..\.tools\php\php.exe'
if (-not (Test-Path -LiteralPath $storePhp)) { $storePhp = (Get-Command php -ErrorAction Stop).Source }
$storePhp = (Resolve-Path -LiteralPath $storePhp).Path
$storeWorkerFile = Join-Path $storeRoot 'storage\logs\scheduler.pid'
$storeStateFile = Join-Path $storeRoot 'storage\logs\scheduler-worker.json'
$storeRunning = $null
# Start time prevents a reused PID from being mistaken for this worker.
# Get-Process works for our own child without requiring WMI/CIM permission.
if (Test-Path -LiteralPath $storeStateFile) {
    $storeState = $null
    try { $storeState = Get-Content -LiteralPath $storeStateFile -Raw | ConvertFrom-Json } catch { }
    if ($storeState -and $storeState.root -eq $storeRoot) {
        $storeRunning = Get-Process -Id ([int]$storeState.processId) -ErrorAction SilentlyContinue
        if ($storeRunning -and $storeRunning.Path -eq $storePhp -and $storeRunning.StartTime.ToUniversalTime().Ticks -eq [long]$storeState.startTicks) {
            Write-Host 'Tác vụ nền SOLE đang hoạt động.'
            return
        }
    }
} elseif (Test-Path -LiteralPath $storeWorkerFile) {
    # Keep existing workers created by the previous version of this script.
    $storeLegacyId = 0
    $storeLegacyText = Get-Content -LiteralPath $storeWorkerFile -Raw
    if ([int]::TryParse($storeLegacyText.Trim(), [ref]$storeLegacyId)) {
        $storeRunning = Get-Process -Id $storeLegacyId -ErrorAction SilentlyContinue
    }
    if ($storeRunning -and $storeRunning.Path -eq $storePhp -and [math]::Abs(($storeRunning.StartTime.ToUniversalTime() - (Get-Item -LiteralPath $storeWorkerFile).LastWriteTimeUtc).TotalSeconds) -le 5) {
        @{ processId = $storeRunning.Id; startTicks = $storeRunning.StartTime.ToUniversalTime().Ticks; root = $storeRoot } | ConvertTo-Json | Set-Content -LiteralPath $storeStateFile
        Write-Host 'Tác vụ nền SOLE đang hoạt động.'
        return
    }
}
$storeWorker = Start-Process -FilePath $storePhp -ArgumentList @(('"' + (Join-Path $storeRoot 'artisan') + '"'), 'schedule:work') -WorkingDirectory $storeRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $storeRoot 'storage\logs\scheduler.out.log') -RedirectStandardError (Join-Path $storeRoot 'storage\logs\scheduler.error.log')
Set-Content -LiteralPath $storeWorkerFile -Value $storeWorker.Id
@{ processId = $storeWorker.Id; startTicks = $storeWorker.StartTime.ToUniversalTime().Ticks; root = $storeRoot } | ConvertTo-Json | Set-Content -LiteralPath $storeStateFile
Write-Host 'Đã bật tác vụ hết hạn QR và email (mỗi phút).'
