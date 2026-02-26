<?php

declare(strict_types=1);

function db_options(): array
{
    return [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
}

function db_server_pdo(): PDO
{
    if (isset($GLOBALS['__db_server_pdo']) && $GLOBALS['__db_server_pdo'] instanceof PDO) {
        return $GLOBALS['__db_server_pdo'];
    }

    $cfg = app_config()['db'];
    $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $cfg['host'], (int)$cfg['port'], $cfg['charset']);
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], db_options());
    $GLOBALS['__db_server_pdo'] = $pdo;

    return $pdo;
}

function db_pdo(): PDO
{
    if (isset($GLOBALS['__db_pdo']) && $GLOBALS['__db_pdo'] instanceof PDO) {
        return $GLOBALS['__db_pdo'];
    }

    $cfg = app_config()['db'];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], (int)$cfg['port'], $cfg['dbname'], $cfg['charset']);
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], db_options());
    $GLOBALS['__db_pdo'] = $pdo;

    return $pdo;
}

function db_reset_connections(): void
{
    unset($GLOBALS['__db_server_pdo'], $GLOBALS['__db_pdo']);
}

function db(): PDO
{
    return db_pdo();
}

function db_can_connect(): bool
{
    try {
        db();
        return true;
    } catch (PDOException) {
        return false;
    }
}

function db_table_exists(string $table): bool
{
    try {
        $cfg = app_config()['db'];
        $sql = 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :schema AND table_name = :table LIMIT 1';
        $stmt = db()->prepare($sql);
        $stmt->execute([
            'schema' => (string)$cfg['dbname'],
            'table' => $table,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}
