<?php
declare(strict_types=1);

use App\Support\Env;

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) require $path;
});

$env = Env::load(dirname(__DIR__) . '/.env');
$privateStoragePath = $env['PRIVATE_STORAGE_PATH'] ?? 'storage/private';
$isWindowsAbsolute = strlen($privateStoragePath) >= 3
    && ctype_alpha($privateStoragePath[0])
    && $privateStoragePath[1] === ':'
    && ($privateStoragePath[2] === '/' || $privateStoragePath[2] === '\\');
if (!$isWindowsAbsolute && !str_starts_with($privateStoragePath, '/') && !str_starts_with($privateStoragePath, '\\')) {
    $privateStoragePath = dirname(__DIR__) . '/' . $privateStoragePath;
}
$config = [
    'timezone' => $env['APP_TIMEZONE'] ?? 'Asia/Jakarta',
    'participant_prefix' => $env['PARTICIPANT_PREFIX'] ?? 'PES',
    'token_encryption_key' => $env['TOKEN_ENCRYPTION_KEY'] ?? '',
    'private_storage_path' => $privateStoragePath,
    'db' => ['host' => $env['DB_HOST'] ?? '127.0.0.1', 'port' => $env['DB_PORT'] ?? '3306', 'database' => $env['DB_DATABASE'] ?? 'pmb_politeknik_aceh', 'username' => $env['DB_USERNAME'] ?? 'root', 'password' => $env['DB_PASSWORD'] ?? ''],
];
date_default_timezone_set($config['timezone']);
session_name($env['APP_SESSION_NAME'] ?? 'pmb_session');
$sessionSavePath = trim((string) ($env['APP_SESSION_SAVE_PATH'] ?? ''));
if ($sessionSavePath !== '') {
    if ($sessionSavePath === 'system-temp') {
        $sessionSavePath = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'pmb-politeknik-aceh-sessions';
    } else {
        $isWindowsAbsoluteSessionPath = strlen($sessionSavePath) >= 3
            && ctype_alpha($sessionSavePath[0])
            && $sessionSavePath[1] === ':'
            && ($sessionSavePath[2] === '/' || $sessionSavePath[2] === '\\');
        if (!$isWindowsAbsoluteSessionPath && !str_starts_with($sessionSavePath, '/') && !str_starts_with($sessionSavePath, '\\')) {
            $sessionSavePath = dirname(__DIR__) . '/' . $sessionSavePath;
        }
    }
    if (!is_dir($sessionSavePath) && !mkdir($sessionSavePath, 0775, true) && !is_dir($sessionSavePath)) {
        throw new RuntimeException('Folder penyimpanan sesi tidak dapat dibuat.');
    }
    if (!is_writable($sessionSavePath)) {
        throw new RuntimeException('Folder penyimpanan sesi tidak dapat ditulis.');
    }
    session_save_path($sessionSavePath);
}
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')]);
session_start();
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
