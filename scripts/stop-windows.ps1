$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$projectRoot = Split-Path -Parent $PSScriptRoot
$pidPath = Join-Path $projectRoot 'runtime\server.pid'

if (-not (Test-Path -LiteralPath $pidPath)) {
    Write-Host '[INFO] Server PMB tidak tercatat sedang berjalan.' -ForegroundColor Yellow
    exit 0
}

$serverPid = 0
if (-not [int]::TryParse((Get-Content -LiteralPath $pidPath -Raw).Trim(), [ref]$serverPid)) {
    Remove-Item -LiteralPath $pidPath -Force
    throw 'Berkas PID server tidak valid dan telah dibersihkan.'
}

$processInfo = Get-CimInstance Win32_Process -Filter "ProcessId = $serverPid" -ErrorAction SilentlyContinue
if ($null -eq $processInfo) {
    Remove-Item -LiteralPath $pidPath -Force
    Write-Host '[INFO] Server sudah berhenti; catatan lama telah dibersihkan.' -ForegroundColor Yellow
    exit 0
}

$routerPath = Join-Path $projectRoot 'public\index.php'
if ($processInfo.Name -notin @('php.exe', 'php') -or $processInfo.CommandLine -notlike "*$routerPath*") {
    throw "PID $serverPid bukan server milik aplikasi PMB. Proses tidak dihentikan."
}

Stop-Process -Id $serverPid -Force
Remove-Item -LiteralPath $pidPath -Force
Write-Host '[OK] Server aplikasi PMB telah dihentikan.' -ForegroundColor Green
