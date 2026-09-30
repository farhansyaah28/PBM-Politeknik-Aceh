<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Support/Env.php';

use App\Support\Env;

$rootDir = dirname(__DIR__);
$envPath = $rootDir . '/.env';
$checkOnly = in_array('--check', $argv, true);
$serverDatadirOnly = in_array('--server-datadir', $argv, true);
$configPath = file_exists($envPath) ? $envPath : $rootDir . '/.env.example';

if (!file_exists($configPath)) {
    fwrite(STDERR, "[GAGAL] .env.example tidak ditemukan.\n");
    exit(1);
}

$env = Env::load($configPath);
$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = $env['DB_PORT'] ?? '3306';
$dbName = $env['DB_DATABASE'] ?? 'pmb_politeknik_aceh';
$username = $env['DB_USERNAME'] ?? 'root';
$password = $env['DB_PASSWORD'] ?? '';
$databaseOverride = getenv('PMB_SETUP_DATABASE');
if (is_string($databaseOverride) && $databaseOverride !== '') {
    $dbName = $databaseOverride;
}

if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
    fwrite(STDERR, "[GAGAL] DB_DATABASE hanya boleh berisi huruf, angka, dan garis bawah.\n");
    exit(1);
}

function normalizedMigrationSql(string $sql): string
{
    $sql = preg_replace('/^\s*CREATE\s+DATABASE\b.*?;\s*/im', '', $sql) ?? $sql;
    return preg_replace('/^\s*USE\s+`?[A-Za-z0-9_]+`?\s*;\s*/im', '', $sql) ?? $sql;
}

function ensureLocalReviewAccounts(PDO $pdo, array $env): void
{
    if (($env['APP_ENV'] ?? 'local') !== 'local') {
        return;
    }

    $accounts = [
        [
            'id' => '00000000-0000-4000-8000-000000000301',
            'username' => 'admin.uji',
            'email' => 'admin.uji@example.test',
            'password_hash' => '$2y$10$h7T.M/5l3I3/nXlCxxQe6uQwDnodOVG0y/esqD6.hV4kxusFESJuS',
            'role' => 'ADMIN',
        ],
        [
            'id' => '00000000-0000-4000-8000-000000000302',
            'username' => 'panitia.uji',
            'email' => 'panitia.uji@example.test',
            'password_hash' => '$2y$10$zmOhwwRcQN8n/kRWN2q8Ye1GAfWofUnjRWOrUf1sDJbqrYO0.Nkim',
            'role' => 'COMMITTEE',
        ],
    ];

    $exists = $pdo->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
    $create = $pdo->prepare(
        'INSERT INTO users (id, username, email, password_hash, role, status, created_at, updated_at) '
        . 'VALUES (:id, :username, :email, :password_hash, :role, "ACTIVE", UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );

    foreach ($accounts as $account) {
        $exists->execute(['email' => $account['email']]);
        if ($exists->fetchColumn()) {
            continue;
        }
        $create->execute($account);
        echo "[OK] Akun review {$account['role']} dibuat: {$account['email']}\n";
    }
}

try {
    if (!$serverDatadirOnly) echo "Menghubungkan ke MySQL {$host}:{$port}...\n";
    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]);

    if ($serverDatadirOnly) {
        echo (string) $pdo->query('SELECT @@datadir')->fetchColumn();
        exit(0);
    }

    if ($checkOnly) {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?');
        $statement->execute([$dbName]);
        echo ((int) $statement->fetchColumn() === 1)
            ? "[OK] Database {$dbName} tersedia.\n"
            : "[INFO] Database {$dbName} belum dibuat.\n";
        exit(0);
    }

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbName}`");
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (' .
        'migration VARCHAR(255) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $migrationCount = (int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
    if ($migrationCount === 0) {
        $existingAppTables = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables " .
            "WHERE table_schema = DATABASE() AND table_name IN ('users','participants','exam_sessions')"
        )->fetchColumn();
        if ($existingAppTables > 0) {
            throw new RuntimeException(
                'Database lama terdeteksi tanpa riwayat migrasi. Gunakan database kosong atau ubah DB_DATABASE di .env agar data lama tidak tertimpa.'
            );
        }
    }

    $files = glob($rootDir . '/database/migrations/*.sql') ?: [];
    sort($files, SORT_NATURAL);
    if ($files === []) {
        throw new RuntimeException('Tidak ada file migrasi pada database/migrations.');
    }

    $isApplied = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration = ?');
    $markApplied = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
    $applied = 0;
    $skipped = 0;

    foreach ($files as $file) {
        $filename = basename($file);
        $isApplied->execute([$filename]);
        if ($isApplied->fetchColumn()) {
            echo "[LEWATI] {$filename}\n";
            $skipped++;
            continue;
        }

        $sql = file_get_contents($file);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException("File migrasi kosong atau tidak dapat dibaca: {$filename}");
        }

        echo "[PROSES] {$filename}... ";
        $pdo->exec(normalizedMigrationSql($sql));
        $markApplied->execute([$filename]);
        echo "OK\n";
        $applied++;
    }

    ensureLocalReviewAccounts($pdo, $env);

    echo "\n[OK] Database siap: {$applied} migrasi diterapkan, {$skipped} sudah tersedia.\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, "\n[GAGAL] {$error->getMessage()}\n");
    exit(1);
}
