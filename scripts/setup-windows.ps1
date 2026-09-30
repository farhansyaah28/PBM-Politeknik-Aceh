[CmdletBinding()]
param(
    [switch]$CheckOnly,
    [switch]$AutoInstall,
    [switch]$NoBrowser,
    [switch]$NoStartServer
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

trap {
    Write-Host "`n[GAGAL] $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

$projectRoot = Split-Path -Parent $PSScriptRoot
$envExamplePath = Join-Path $projectRoot '.env.example'
$envPath = Join-Path $projectRoot '.env'
$setupPhpPath = Join-Path $PSScriptRoot 'setup.php'
$runtimePath = Join-Path $projectRoot 'runtime'
$serverPidPath = Join-Path $runtimePath 'server.pid'
$requiredPhpExtensions = @('pdo_mysql', 'openssl', 'fileinfo', 'zip', 'xmlreader')

function Write-Step([string]$Message) {
    Write-Host "`n==> $Message" -ForegroundColor Cyan
}

function Find-Executable([string]$Name, [string[]]$Candidates) {
    foreach ($candidate in $Candidates) {
        if (Test-Path -LiteralPath $candidate -ErrorAction SilentlyContinue) {
            return $candidate
        }
    }

    $command = Get-Command $Name -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($null -ne $command -and (Test-Path -LiteralPath $command.Source -ErrorAction SilentlyContinue)) {
        return $command.Source
    }

    return $null
}

function Find-Winget {
    $command = Get-Command winget.exe -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($null -ne $command -and (Test-Path -LiteralPath $command.Source -ErrorAction SilentlyContinue)) {
        return $command.Source
    }

    $candidate = Join-Path $env:LOCALAPPDATA 'Microsoft\WindowsApps\winget.exe'
    if (Test-Path -LiteralPath $candidate -ErrorAction SilentlyContinue) {
        return $candidate
    }

    return $null
}

function Test-TcpPort([string]$HostName, [int]$Port, [int]$TimeoutMs = 800) {
    $client = [System.Net.Sockets.TcpClient]::new()
    try {
        $task = $client.ConnectAsync($HostName, $Port)
        return $task.Wait($TimeoutMs) -and $client.Connected
    } catch {
        return $false
    } finally {
        $client.Dispose()
    }
}

function Read-EnvValue([string]$Path, [string]$Key, [string]$Default) {
    if (-not (Test-Path -LiteralPath $Path)) { return $Default }
    $line = Get-Content -LiteralPath $Path | Where-Object { $_ -match ('^' + [regex]::Escape($Key) + '=') } | Select-Object -Last 1
    if ($null -eq $line) { return $Default }
    return ($line -split '=', 2)[1].Trim()
}

function Ensure-LocalConfiguration {
    if (-not (Test-Path -LiteralPath $envPath)) {
        Copy-Item -LiteralPath $envExamplePath -Destination $envPath
        Write-Host '[OK] .env dibuat dari .env.example.' -ForegroundColor Green
    } else {
        Write-Host '[OK] .env sudah tersedia; pengaturan yang ada dipertahankan.' -ForegroundColor Green
    }

    $content = Get-Content -LiteralPath $envPath -Raw
    if ($content -match 'TOKEN_ENCRYPTION_KEY=replace-with-a-long-random-secret-before-production') {
        $bytes = [byte[]]::new(32)
        $random = [System.Security.Cryptography.RandomNumberGenerator]::Create()
        try {
            $random.GetBytes($bytes)
        } finally {
            $random.Dispose()
        }
        $secret = -join ($bytes | ForEach-Object { $_.ToString('x2') })
        $content = $content.Replace('TOKEN_ENCRYPTION_KEY=replace-with-a-long-random-secret-before-production', "TOKEN_ENCRYPTION_KEY=$secret")
        [System.IO.File]::WriteAllText($envPath, $content, [System.Text.UTF8Encoding]::new($false))
        Write-Host '[OK] Kunci enkripsi lokal dibuat otomatis dan tidak ditampilkan.' -ForegroundColor Green
    }

    New-Item -ItemType Directory -Force -Path (Join-Path $projectRoot 'storage\private\proctoring') | Out-Null
}

function Ensure-BundledRuntimeConfiguration([string]$PhpPath, [string]$MysqlPath) {
    $bundledPhp = Join-Path $runtimePath 'xampp\php\php.exe'
    $bundledMysql = Join-Path $runtimePath 'xampp\mysql\bin\mysql.exe'
    $xamppPath = Join-Path $runtimePath 'xampp'

    if ([System.IO.Path]::GetFullPath($PhpPath).Equals([System.IO.Path]::GetFullPath($bundledPhp), [System.StringComparison]::OrdinalIgnoreCase)) {
        $phpIniPath = Join-Path $xamppPath 'php\php.ini'
        if (-not (Test-Path -LiteralPath $phpIniPath)) { throw "php.ini runtime aplikasi tidak ditemukan: $phpIniPath" }
        $phpIni = Get-Content -LiteralPath $phpIniPath -Raw
        $phpPaths = [ordered]@{
            '(?m)^include_path\s*=.*$' = 'include_path = "' + (Join-Path $xamppPath 'php\PEAR') + '"'
            '(?m)^extension_dir\s*=.*$' = 'extension_dir = "ext"'
            '(?m)^upload_tmp_dir\s*=.*$' = 'upload_tmp_dir = "' + (Join-Path $xamppPath 'tmp') + '"'
            '(?m)^error_log\s*=.*$' = 'error_log="' + (Join-Path $xamppPath 'php\logs\php_error_log') + '"'
            '(?m)^browscap\s*=.*$' = 'browscap = "' + (Join-Path $xamppPath 'php\extras\browscap.ini') + '"'
            '(?m)^session\.save_path\s*=.*$' = 'session.save_path = "' + (Join-Path $xamppPath 'tmp') + '"'
            '(?m)^curl\.cainfo\s*=.*$' = 'curl.cainfo = "' + (Join-Path $xamppPath 'apache\bin\curl-ca-bundle.crt') + '"'
            '(?m)^openssl\.cafile\s*=.*$' = 'openssl.cafile = "' + (Join-Path $xamppPath 'apache\bin\curl-ca-bundle.crt') + '"'
        }
        foreach ($entry in $phpPaths.GetEnumerator()) {
            if (-not [regex]::IsMatch($phpIni, $entry.Key)) { throw "Konfigurasi PHP tidak ditemukan untuk pola: $($entry.Key)" }
            $phpIni = [regex]::Replace($phpIni, $entry.Key, [string] $entry.Value)
        }
        New-Item -ItemType Directory -Force -Path (Join-Path $xamppPath 'php\logs'), (Join-Path $xamppPath 'tmp') | Out-Null
        [System.IO.File]::WriteAllText($phpIniPath, $phpIni, [System.Text.UTF8Encoding]::new($false))
    }

    if ([System.IO.Path]::GetFullPath($MysqlPath).Equals([System.IO.Path]::GetFullPath($bundledMysql), [System.StringComparison]::OrdinalIgnoreCase)) {
        $mysqlIniPath = Join-Path $xamppPath 'mysql\bin\my.ini'
        if (-not (Test-Path -LiteralPath $mysqlIniPath)) { throw "my.ini runtime aplikasi tidak ditemukan: $mysqlIniPath" }
        $mysqlIni = Get-Content -LiteralPath $mysqlIniPath -Raw
        $mysqlRoot = ((Join-Path $xamppPath 'mysql') -replace '\\', '/')
        $xamppRoot = ($xamppPath -replace '\\', '/')
        $mysqlPaths = [ordered]@{
            '(?m)^socket\s*=.*$' = 'socket = "' + $mysqlRoot + '/mysql.sock"'
            '(?m)^basedir\s*=.*$' = 'basedir = "' + $mysqlRoot + '"'
            '(?m)^tmpdir\s*=.*$' = 'tmpdir = "' + $xamppRoot + '/tmp"'
            '(?m)^datadir\s*=.*$' = 'datadir = "' + $mysqlRoot + '/data"'
            '(?m)^plugin_dir\s*=.*$' = 'plugin_dir = "' + $mysqlRoot + '/lib/plugin/"'
            '(?m)^innodb_data_home_dir\s*=.*$' = 'innodb_data_home_dir = "' + $mysqlRoot + '/data"'
            '(?m)^innodb_log_group_home_dir\s*=.*$' = 'innodb_log_group_home_dir = "' + $mysqlRoot + '/data"'
        }
        foreach ($entry in $mysqlPaths.GetEnumerator()) {
            if (-not [regex]::IsMatch($mysqlIni, $entry.Key)) { throw "Konfigurasi MySQL tidak ditemukan untuk pola: $($entry.Key)" }
            $mysqlIni = [regex]::Replace($mysqlIni, $entry.Key, [string] $entry.Value)
        }
        [System.IO.File]::WriteAllText($mysqlIniPath, $mysqlIni, [System.Text.UTF8Encoding]::new($false))
    }
}

function Assert-BundledMysqlDatadir([string]$PhpPath, [string]$MysqlPath) {
    $bundledMysql = Join-Path $runtimePath 'xampp\mysql\bin\mysql.exe'
    if (-not [System.IO.Path]::GetFullPath($MysqlPath).Equals([System.IO.Path]::GetFullPath($bundledMysql), [System.StringComparison]::OrdinalIgnoreCase)) {
        return
    }

    $actualOutput = @(& $PhpPath $setupPhpPath --server-datadir 2>&1)
    if ($LASTEXITCODE -ne 0) { throw "Datadir MySQL aktif tidak dapat diverifikasi: $($actualOutput -join ' ')" }
    $actual = [System.IO.Path]::GetFullPath(($actualOutput -join '').Trim()).TrimEnd('\', '/')
    $expected = [System.IO.Path]::GetFullPath((Join-Path $runtimePath 'xampp\mysql\data')).TrimEnd('\', '/')
    if (-not $actual.Equals($expected, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Port database digunakan MySQL dengan datadir lain: $actual. Hentikan instance tersebut agar runtime aplikasi dapat memakai $expected."
    }
    Write-Host '[OK] Datadir MySQL terisolasi di runtime aplikasi.' -ForegroundColor Green
}

function Test-PhpExtension([string]$PhpPath, [string]$ExtensionName) {
    & $PhpPath -d display_errors=0 -d error_reporting=0 -r "exit(extension_loaded('$ExtensionName') ? 0 : 1);" 2>&1 | Out-Null
    return $LASTEXITCODE -eq 0
}

function Get-MissingPhpExtensions([string]$PhpPath) {
    return @($requiredPhpExtensions | Where-Object { -not (Test-PhpExtension $PhpPath $_) })
}

function Resolve-PhpIniPath([string]$PhpPath) {
    $output = @(& $PhpPath -d display_errors=0 -r "echo php_ini_loaded_file() ?: '';" 2>&1)
    $loadedIni = [string]($output | ForEach-Object { ([string] $_).Trim() } | Where-Object { $_ -ne '' } | Select-Object -Last 1)
    if (-not [string]::IsNullOrWhiteSpace($loadedIni) -and (Test-Path -LiteralPath $loadedIni)) {
        return $loadedIni
    }

    $phpDir = Split-Path -Parent $PhpPath
    foreach ($template in @('php.ini-production', 'php.ini-development')) {
        $templatePath = Join-Path $phpDir $template
        if (Test-Path -LiteralPath $templatePath) {
            $createdIni = Join-Path $phpDir 'php.ini'
            Copy-Item -LiteralPath $templatePath -Destination $createdIni
            Write-Host "[OK] php.ini dibuat dari $template." -ForegroundColor Green
            return $createdIni
        }
    }

    return $null
}

function Add-IniLine([string]$IniPath, [string]$Line) {
    $ini = Get-Content -LiteralPath $IniPath -Raw
    $ini = $ini.TrimEnd() + "`r`n" + $Line + "`r`n"
    [System.IO.File]::WriteAllText($IniPath, $ini, [System.Text.UTF8Encoding]::new($false))
}

function Enable-PhpExtensionDir([string]$PhpPath, [string]$IniPath) {
    $ini = Get-Content -LiteralPath $IniPath -Raw
    if ([regex]::IsMatch($ini, '(?m)^\s*extension_dir\s*=.*$')) { return }

    $extensionDir = Join-Path (Split-Path -Parent $PhpPath) 'ext'
    if (-not (Test-Path -LiteralPath $extensionDir)) { return }

    Add-IniLine $IniPath ('extension_dir = "' + $extensionDir + '"')
    Write-Host "[OK] extension_dir diarahkan ke $extensionDir." -ForegroundColor Green
}

function Enable-PhpExtension([string]$IniPath, [string]$ExtensionName) {
    $ini = Get-Content -LiteralPath $IniPath -Raw
    $escapedName = [regex]::Escape($ExtensionName)
    if ([regex]::IsMatch($ini, "(?m)^\s*extension\s*=\s*(php_)?$escapedName(\.dll)?\s*$")) {
        # Baris sudah aktif tetapi ekstensi tidak termuat; DLL kemungkinan tidak tersedia.
        return
    }

    $commentedPattern = [regex]::new("(?m)^\s*;\s*extension\s*=\s*(php_)?$escapedName(\.dll)?\s*$")
    if ($commentedPattern.IsMatch($ini)) {
        $ini = $commentedPattern.Replace($ini, "extension=$ExtensionName", 1)
        [System.IO.File]::WriteAllText($IniPath, $ini, [System.Text.UTF8Encoding]::new($false))
    } else {
        Add-IniLine $IniPath "extension=$ExtensionName"
    }
    Write-Host "[OK] extension=$ExtensionName diaktifkan." -ForegroundColor Green
}

function Ensure-RequiredPhpExtensions([string]$PhpPath) {
    $missing = @(Get-MissingPhpExtensions $PhpPath)
    if ($missing.Count -eq 0) { return }

    Write-Step ('Mengaktifkan ekstensi PHP yang belum aktif: ' + ($missing -join ', '))
    $iniPath = Resolve-PhpIniPath $PhpPath
    if ($null -eq $iniPath) {
        throw ('php.ini aktif tidak ditemukan sehingga ekstensi ' + ($missing -join ', ') + ' tidak dapat diaktifkan otomatis. Pasang XAMPP 8.2 standar di C:\xampp, lalu jalankan setup.bat kembali.')
    }

    $backupPath = "$iniPath.pmb-backup"
    Write-Host "[INFO] Berkas yang disesuaikan: $iniPath (cadangan: $backupPath)." -ForegroundColor Yellow
    try {
        if (-not (Test-Path -LiteralPath $backupPath)) {
            Copy-Item -LiteralPath $iniPath -Destination $backupPath
        }
        Enable-PhpExtensionDir $PhpPath $iniPath
        foreach ($extension in $missing) {
            Enable-PhpExtension $iniPath $extension
        }
    } catch [System.UnauthorizedAccessException] {
        throw ("Tidak memiliki izin menulis $iniPath. Klik kanan setup.bat lalu pilih 'Run as administrator', atau aktifkan ekstensi " + ($missing -join ', ') + ' secara manual di berkas tersebut.')
    } catch {
        throw ("Gagal memperbarui ${iniPath}: $($_.Exception.Message). Aktifkan ekstensi " + ($missing -join ', ') + ' secara manual di berkas tersebut, lalu jalankan setup.bat kembali.')
    }

    $stillMissing = @(Get-MissingPhpExtensions $PhpPath)
    if ($stillMissing.Count -gt 0) {
        throw ('Ekstensi ' + ($stillMissing -join ', ') + " tetap tidak aktif setelah php.ini diperbarui. Berkas DLL-nya kemungkinan tidak tersedia pada instalasi PHP ini ($PhpPath). Pasang XAMPP 8.2 standar di C:\xampp, lalu jalankan setup.bat kembali.")
    }
    Write-Host '[OK] Ekstensi PHP wajib berhasil diaktifkan otomatis.' -ForegroundColor Green
}

Write-Step 'Memeriksa berkas aplikasi'
$requiredFiles = @($envExamplePath, $setupPhpPath, (Join-Path $projectRoot 'public\index.php'))
foreach ($requiredFile in $requiredFiles) {
    if (-not (Test-Path -LiteralPath $requiredFile)) {
        throw "Berkas wajib tidak ditemukan: $requiredFile"
    }
}
Write-Host '[OK] Struktur aplikasi lengkap.' -ForegroundColor Green

$xamppRootCandidates = @(
    (Join-Path $runtimePath 'xampp'),
    'C:\xampp',
    'D:\xampp',
    'E:\xampp'
)
foreach ($programFilesRoot in @($env:ProgramFiles, ${env:ProgramFiles(x86)})) {
    if (-not [string]::IsNullOrWhiteSpace($programFilesRoot)) {
        $xamppRootCandidates += (Join-Path $programFilesRoot 'XAMPP')
    }
}
$phpCandidates = @($xamppRootCandidates | ForEach-Object { [System.IO.Path]::Combine($_, 'php\php.exe') })
$mysqlCandidates = @($xamppRootCandidates | ForEach-Object { [System.IO.Path]::Combine($_, 'mysql\bin\mysql.exe') })

Write-Step 'Memeriksa PHP dan MySQL'
$phpExe = Find-Executable 'php.exe' $phpCandidates
$mysqlExe = Find-Executable 'mysql.exe' $mysqlCandidates

if ($null -eq $phpExe -or $null -eq $mysqlExe) {
    if ($CheckOnly) {
        throw 'PHP/MySQL belum ditemukan. Jalankan setup.bat untuk memasang XAMPP secara otomatis.'
    }

    $wingetExe = Find-Winget
    if ($null -eq $wingetExe) {
        throw 'XAMPP belum tersedia dan Windows Package Manager (winget) tidak ditemukan. Pasang App Installer dari Microsoft Store, lalu jalankan setup.bat kembali.'
    }

    $installApproved = $AutoInstall
    if (-not $installApproved) {
        $answer = Read-Host 'XAMPP 8.2 belum lengkap. Unduh dan pasang otomatis sekarang? [Y/n]'
        $installApproved = [string]::IsNullOrWhiteSpace($answer) -or $answer -match '^[Yy]'
    }
    if (-not $installApproved) {
        throw 'Instalasi dibatalkan. Aplikasi memerlukan PHP 8.2 dan MySQL/MariaDB.'
    }

    Write-Step 'Mengunduh dan memasang XAMPP 8.2 dari katalog winget'
    & $wingetExe install --id ApacheFriends.Xampp.8.2 --exact --source winget --accept-package-agreements --accept-source-agreements --silent
    if ($LASTEXITCODE -ne 0) {
        throw "Instalasi XAMPP gagal dengan kode $LASTEXITCODE. Periksa koneksi internet atau jalankan setup.bat sebagai Administrator."
    }

    $phpExe = Find-Executable 'php.exe' $phpCandidates
    $mysqlExe = Find-Executable 'mysql.exe' $mysqlCandidates
    if ($null -eq $phpExe -or $null -eq $mysqlExe) {
        throw 'XAMPP selesai dipasang, tetapi PHP/MySQL belum ditemukan. Tutup jendela ini dan jalankan setup.bat kembali.'
    }
}

if (-not $CheckOnly) {
    Ensure-BundledRuntimeConfiguration $phpExe $mysqlExe
}

$phpVersionOutput = @(& $phpExe -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>&1)
$phpVersionText = [string] ($phpVersionOutput | Where-Object { ([string] $_).Trim() -match '^\d+\.\d+$' } | Select-Object -Last 1)
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($phpVersionText)) {
    $phpDiagnostic = ($phpVersionOutput | ForEach-Object { ([string] $_).Trim() } | Where-Object { $_ -ne '' } | Select-Object -First 3) -join ' '
    throw "PHP ditemukan tetapi tidak dapat dijalankan dengan benar. Periksa instalasi XAMPP. Detail: $phpDiagnostic"
}
if ([version]$phpVersionText -lt [version]'8.2') {
    throw "PHP $phpVersionText terdeteksi. Aplikasi memerlukan PHP 8.2 atau lebih baru."
}
if ($CheckOnly) {
    $missingExtensions = @(Get-MissingPhpExtensions $phpExe)
    if ($missingExtensions.Count -gt 0) {
        throw ('Ekstensi PHP ' + ($missingExtensions -join ', ') + ' belum aktif. Jalankan setup.bat agar ekstensi tersebut diaktifkan otomatis.')
    }
} else {
    Ensure-RequiredPhpExtensions $phpExe
}
Write-Host "[OK] PHP $phpVersionText dan MySQL client ditemukan." -ForegroundColor Green

if ($CheckOnly) {
    $configPath = if (Test-Path -LiteralPath $envPath) { $envPath } else { $envExamplePath }
    $dbHost = Read-EnvValue $configPath 'DB_HOST' '127.0.0.1'
    $dbPort = [int](Read-EnvValue $configPath 'DB_PORT' '3306')
    $dbReady = Test-TcpPort $dbHost $dbPort
    Write-Host ("[INFO] MySQL {0}:{1}: {2}" -f $dbHost, $dbPort, $(if ($dbReady) { 'aktif' } else { 'belum aktif' }))
    if ($dbReady) {
        & $phpExe $setupPhpPath --check
        if ($LASTEXITCODE -ne 0) {
            throw 'MySQL aktif, tetapi kredensial database tidak dapat digunakan. Periksa .env.'
        }
        Assert-BundledMysqlDatadir $phpExe $mysqlExe
    }
    Write-Host '[OK] Pemeriksaan prasyarat selesai; tidak ada perubahan yang dibuat.' -ForegroundColor Green
    exit 0
}

Write-Step 'Menyiapkan konfigurasi lokal'
Ensure-LocalConfiguration

$dbHost = Read-EnvValue $envPath 'DB_HOST' '127.0.0.1'
$dbPort = [int](Read-EnvValue $envPath 'DB_PORT' '3306')
if (-not (Test-TcpPort $dbHost $dbPort)) {
    $xamppRoot = Split-Path -Parent (Split-Path -Parent (Split-Path -Parent $mysqlExe))
    $mysqlStart = Join-Path $xamppRoot 'mysql_start.bat'
    if (-not (Test-Path -LiteralPath $mysqlStart)) {
        throw "MySQL belum aktif. Jalankan MySQL pada XAMPP Control Panel, lalu ulangi setup.bat."
    }

    Write-Step 'Menyalakan MySQL XAMPP'
    Start-Process -FilePath 'cmd.exe' -ArgumentList @('/c', ('"{0}"' -f $mysqlStart)) -WindowStyle Hidden | Out-Null
    $ready = $false
    for ($attempt = 1; $attempt -le 30; $attempt++) {
        if (Test-TcpPort $dbHost $dbPort) { $ready = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) {
        throw 'MySQL tidak aktif setelah 30 detik. Buka XAMPP Control Panel, periksa konflik port 3306, lalu ulangi setup.'
    }
}
Write-Host '[OK] MySQL aktif.' -ForegroundColor Green
Assert-BundledMysqlDatadir $phpExe $mysqlExe

Write-Step 'Membuat database dan menjalankan migrasi'
& $phpExe $setupPhpPath
if ($LASTEXITCODE -ne 0) {
    throw 'Pembuatan database gagal. Ikuti pesan [GAGAL] di atas (umumnya DB_DATABASE, DB_USERNAME, atau DB_PASSWORD pada .env), lalu jalankan setup.bat kembali.'
}

if ($NoStartServer) {
    Write-Host '[OK] Database siap. Server tidak dijalankan karena opsi -NoStartServer digunakan.' -ForegroundColor Green
    exit 0
}

Write-Step 'Menjalankan aplikasi'
New-Item -ItemType Directory -Force -Path $runtimePath | Out-Null
$appHost = '127.0.0.1'
$appPort = 8080
$documentRoot = Join-Path $projectRoot 'public'
$routerPath = Join-Path $projectRoot 'public\index.php'
if (-not (Test-TcpPort $appHost $appPort)) {
    $stdoutLog = Join-Path $runtimePath 'server.log'
    $stderrLog = Join-Path $runtimePath 'server-error.log'
    $serverArguments = "-S $appHost`:$appPort -t `"$documentRoot`" `"$routerPath`""
    $serverProcess = Start-Process -FilePath $phpExe -ArgumentList $serverArguments -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput $stdoutLog -RedirectStandardError $stderrLog -PassThru
    [System.IO.File]::WriteAllText($serverPidPath, [string]$serverProcess.Id, [System.Text.Encoding]::ASCII)
    $serverReady = $false
    for ($attempt = 1; $attempt -le 15; $attempt++) {
        if (Test-TcpPort $appHost $appPort) { $serverReady = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $serverReady) {
        throw "Server aplikasi gagal berjalan. Periksa $stderrLog"
    }
} else {
    $knownServer = $false
    if (Test-Path -LiteralPath $serverPidPath) {
        $knownPid = 0
        if ([int]::TryParse((Get-Content -LiteralPath $serverPidPath -Raw).Trim(), [ref]$knownPid)) {
            $processInfo = Get-CimInstance Win32_Process -Filter "ProcessId=$knownPid" -ErrorAction SilentlyContinue
            if ($null -ne $processInfo -and -not [string]::IsNullOrWhiteSpace($processInfo.ExecutablePath)) {
                $expectedPhpPath = [System.IO.Path]::GetFullPath($phpExe)
                $actualPhpPath = [System.IO.Path]::GetFullPath($processInfo.ExecutablePath)
                $commandLine = [string] $processInfo.CommandLine
                $knownServer = $actualPhpPath.Equals($expectedPhpPath, [System.StringComparison]::OrdinalIgnoreCase) `
                    -and $commandLine.IndexOf($documentRoot, [System.StringComparison]::OrdinalIgnoreCase) -ge 0 `
                    -and $commandLine.IndexOf($routerPath, [System.StringComparison]::OrdinalIgnoreCase) -ge 0
            }
        }
    }
    if (-not $knownServer) {
        if (Test-Path -LiteralPath $serverPidPath) {
            Remove-Item -LiteralPath $serverPidPath -Force
            Write-Host '[INFO] PID server lama tidak menunjuk instance aplikasi ini; penanda lama dihapus.' -ForegroundColor Yellow
        }
        throw 'Port 8080 sedang digunakan aplikasi lain. Hentikan aplikasi tersebut, lalu jalankan setup.bat kembali.'
    }
    Write-Host '[INFO] Server PMB sudah aktif dan dipertahankan.' -ForegroundColor Yellow
}

$appUrl = "http://$appHost`:$appPort"
Write-Host "`n[SELESAI] Aplikasi siap di $appUrl" -ForegroundColor Green
Write-Host 'Untuk menghentikan server aplikasi, klik dua kali stop-app.bat.'
Write-Host 'Untuk penggunaan berikutnya, jalankan setup.bat kembali; migrasi yang sudah selesai akan dilewati.'
if (-not $NoBrowser) {
    Start-Process $appUrl | Out-Null
}
