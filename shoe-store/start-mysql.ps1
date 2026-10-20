$ErrorActionPreference = 'Stop'
$workspace = Split-Path -Parent $PSScriptRoot
$mysqlRuntime = Join-Path $workspace '.tools/mysql-local'
$mysqlBinary = 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqld.exe'
if (!(Test-Path -LiteralPath $mysqlBinary) -or !(Test-Path -LiteralPath (Join-Path $mysqlRuntime 'data/auto.cnf'))) {
    throw 'Khong tim thay MySQL local da khoi tao. Xem README de dung MySQL cua ban.'
}
$connection = [System.Net.Sockets.TcpClient]::new()
try {
    $connection.Connect('127.0.0.1', 3309)
    Write-Output 'Cong 3309 dang hoat dong. Ung dung se kiem tra ket noi bang tai khoan trong .env.'
    exit 0
} catch {
    # Start only this project's isolated instance; never modify a system service.
} finally {
    $connection.Dispose()
}
$dataDirectory = (Join-Path $mysqlRuntime 'data').Replace('\','/')
$errorLog = (Join-Path $mysqlRuntime 'mysql.log').Replace('\','/')
Start-Process -FilePath $mysqlBinary -ArgumentList @('--no-defaults', ('--datadir="' + $dataDirectory + '"'), '--port=3309', '--bind-address=127.0.0.1', '--mysqlx=OFF', ('--log-error="' + $errorLog + '"')) -WindowStyle Hidden | Out-Null
Write-Output 'Da khoi dong MySQL rieng cho SOLE tai 127.0.0.1:3309.'
