$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
$storePhp = Join-Path $PSScriptRoot '..\.tools\php\php.exe'
if (-not (Test-Path -LiteralPath $storePhp)) {
    $storePhp = (Get-Command php -ErrorAction Stop).Source
}
if (-not (Test-Path -LiteralPath '.env')) {
    Copy-Item -LiteralPath '.env.example' -Destination '.env'
    & $storePhp artisan key:generate
    if ($LASTEXITCODE -ne 0) { throw 'Không thể tạo khóa ứng dụng.' }
}
if (-not (Test-Path -LiteralPath 'database\database.sqlite')) {
    New-Item -ItemType File -Path 'database\database.sqlite' | Out-Null
}
$localStoreEnv = Get-Content -LiteralPath '.env' -Raw
if ($localStoreEnv -match '(?m)^DB_CONNECTION=mysql\s*$' -and $localStoreEnv -match '(?m)^DB_PORT=3309\s*$') {
    & (Join-Path $PSScriptRoot 'start-mysql.ps1')
    Start-Sleep -Seconds 2
}
& $storePhp artisan config:clear
& $storePhp artisan migrate --seed --force
if ($LASTEXITCODE -ne 0) { throw 'Không thể cập nhật cơ sở dữ liệu. Kiểm tra cấu hình DB trong .env.' }
Write-Host 'SOLE: http://127.0.0.1:8000 | Quản trị: http://127.0.0.1:8000/admin'
& (Join-Path $PSScriptRoot 'start-worker.ps1')
& $storePhp artisan serve --host=127.0.0.1 --port=8000
