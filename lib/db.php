<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db_schema_file_path(): string
{
    $candidates = [
        __DIR__ . '/../db/schema.sql',
        __DIR__ . '/../sql/schema.sql',
    ];

    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    throw new RuntimeException('schema.sql が見つかりません。');
}

function db_sql_split_statements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $inSingle = false;
    $inDouble = false;
    $inLineComment = false;
    $inBlockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($inLineComment) {
            if ($char === "\n") {
                $inLineComment = false;
                $buffer .= $char;
            }
            continue;
        }

        if ($inBlockComment) {
            if ($char === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }

        if (!$inSingle && !$inDouble) {
            if ($char === '-' && $next === '-') {
                $inLineComment = true;
                $i++;
                continue;
            }

            if ($char === '#') {
                $inLineComment = true;
                continue;
            }

            if ($char === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }
        }

        if ($char === "'" && !$inDouble) {
            $escaped = $i > 0 && $sql[$i - 1] === '\\';
            if (!$escaped) {
                $inSingle = !$inSingle;
            }
            $buffer .= $char;
            continue;
        }

        if ($char === '"' && !$inSingle) {
            $escaped = $i > 0 && $sql[$i - 1] === '\\';
            if (!$escaped) {
                $inDouble = !$inDouble;
            }
            $buffer .= $char;
            continue;
        }

        if ($char === ';' && !$inSingle && !$inDouble) {
            $stmt = trim($buffer);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

function db_seed_default_admin_user(PDO $pdo): void
{
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'admin_users'");
    $stmt->execute();
    if ($stmt->fetchColumn() === false) {
        return;
    }

    $exists = $pdo->prepare('SELECT id FROM admin_users WHERE username = :username LIMIT 1');
    $exists->execute([':username' => 'admin']);
    if ($exists->fetchColumn() !== false) {
        return;
    }

    $columnsStmt = $pdo->query('SHOW COLUMNS FROM admin_users');
    $columns = $columnsStmt ? $columnsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    if (!is_array($columns) || $columns === []) {
        return;
    }

    $values = [
        'username' => 'admin',
        'password_hash' => password_hash('password', PASSWORD_DEFAULT),
        'role' => 'admin',
        'is_active' => 1,
        'login_mode' => 'username',
        'display_name' => 'admin',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    $targetColumns = [];
    $params = [];
    foreach ($values as $name => $value) {
        if (in_array($name, $columns, true)) {
            $targetColumns[] = $name;
            $params[':' . $name] = $value;
        }
    }

    if (!in_array('username', $targetColumns, true) || !in_array('password_hash', $targetColumns, true)) {
        return;
    }

    $sql = sprintf(
        'INSERT INTO admin_users (%s) VALUES (%s)',
        implode(', ', $targetColumns),
        implode(', ', array_keys($params))
    );
    $insert = $pdo->prepare($sql);
    $insert->execute($params);
}

function db_ensure_initialized(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $checked = true;

    $runMigrations = static function () use ($pdo): void {
        $migrationFiles = glob(__DIR__ . '/../sql/migrations/*.sql');
        if (!is_array($migrationFiles)) {
            return;
        }

        sort($migrationFiles, SORT_STRING);
        foreach ($migrationFiles as $file) {
            $migrationSql = (string)file_get_contents($file);
            foreach (db_sql_split_statements($migrationSql) as $sql) {
                $pdo->exec($sql);
            }
        }
    };

    $stmt = $pdo->prepare("SHOW TABLES LIKE 'admin_users'");
    $stmt->execute();
    $hasAdminUsers = $stmt->fetchColumn() !== false;

    if (!$hasAdminUsers) {
        $schemaPath = db_schema_file_path();
        $schemaSql = (string)file_get_contents($schemaPath);
        foreach (db_sql_split_statements($schemaSql) as $sql) {
            $pdo->exec($sql);
        }
    }

    $runMigrations();

    db_seed_default_admin_user($pdo);
}

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
        db_ensure_initialized($pdo);
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
