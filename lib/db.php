<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = config_get('db', []);

    // DSN優先。無ければ host/name/charset から生成（MySQL想定）
    $dsn = (string)($db['dsn'] ?? '');
    if ($dsn === '') {
        $host = (string)($db['host'] ?? '127.0.0.1');
        $name = (string)($db['name'] ?? '');
        $charset = (string)($db['charset'] ?? 'utf8mb4');
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $name, $charset);
    }

    $user = (string)($db['user'] ?? '');
    $password = (string)($db['password'] ?? ($db['pass'] ?? ''));

    $options = $db['options'] ?? [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // MySQL向け：本物のプリペアを有効にしてインジェクション耐性を上げる
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $password, $options);
    } catch (PDOException $e) {
        throw new RuntimeException('Database connection failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
    }

    return $pdo;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function log_message(string $message): void
{
    static $inProgress = false;

    $line = sprintf("[%s] %s", date('Y-m-d H:i:s'), $message);
    $fallback = '[PinkClub-FANZA] ' . $line;

    if ($inProgress) {
        error_log($fallback);
        return;
    }

    $inProgress = true;

    try {
        $dir = __DIR__ . '/../logs';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            error_log($fallback . ' | log directory is not writable.');
            return;
        }

        $path = $dir . '/app.log';
        $result = @file_put_contents($path, $line . "\n", FILE_APPEND);
        if ($result === false) {
            error_log($fallback . ' | failed to write logs/app.log.');
            return;
        }
    } catch (Throwable $e) {
        error_log($fallback . ' | logging fallback: ' . $e->getMessage());
    } finally {
        $inProgress = false;
    }
}
