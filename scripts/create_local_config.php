<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$path = $root . '/config.local.php';

if (is_file($path)) {
    fwrite(STDOUT, "config.local.php already exists. Skipped.\n");
    exit(0);
}

$host = getenv('DB_HOST') ?: '';
$port = (int)(getenv('DB_PORT') ?: 3306);
$dbname = getenv('DB_NAME') ?: '';
$user = getenv('DB_USER') ?: '';
$password = getenv('DB_PASSWORD') ?: '';
$dsn = getenv('DB_DSN') ?: ($host !== '' && $dbname !== '' ? sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $dbname) : '');

$local = [
    'db' => [
        'host' => $host,
        'port' => $port,
        'dbname' => $dbname,
        'name' => $dbname,
        'dsn' => $dsn,
        'user' => $user,
        'pass' => $password,
        'password' => $password,
        'charset' => 'utf8mb4',
    ],
];

$contents = "<?php\nreturn " . var_export($local, true) . ";\n";

if (file_put_contents($path, $contents . "\n") === false) {
    fwrite(STDERR, "Failed to create config.local.php.\n");
    exit(1);
}

fwrite(STDOUT, "Created config.local.php.\n");
