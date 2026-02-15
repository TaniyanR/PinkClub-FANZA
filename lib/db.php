<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db_schema_file_path(): string
{
    $path = __DIR__ . '/../sql/schema.sql';
    if (is_file($path)) {
        return $path;
    }

    throw new RuntimeException('schema.sql が見つかりません。');
}

function db_stmt_fetch_one(PDOStatement $stmt): mixed
{
    try {
        return $stmt->fetchColumn();
    } finally {
        $stmt->closeCursor();
    }
}

function db_stmt_fetch_all(PDOStatement $stmt, int $mode = PDO::FETCH_ASSOC): array
{
    try {
        $rows = $stmt->fetchAll($mode);
        return is_array($rows) ? $rows : [];
    } finally {
        $stmt->closeCursor();
    }
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
    if (db_stmt_fetch_one($stmt) === false) {
        return;
    }

    $exists = $pdo->prepare('SELECT id FROM admin_users WHERE username = :username LIMIT 1');
    $exists->execute([':username' => 'admin']);
    if (db_stmt_fetch_one($exists) !== false) {
        return;
    }

    $columnsStmt = $pdo->query('SHOW COLUMNS FROM admin_users');
    $columns = $columnsStmt ? db_stmt_fetch_all($columnsStmt, PDO::FETCH_COLUMN) : [];
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

function db_extract_name_from_dsn(string $dsn): string
{
    if (preg_match('/(?:^|;)dbname=([^;]+)/i', $dsn, $matches) === 1 && $matches[1] !== '') {
        return trim($matches[1]);
    }

    return '';
}

function db_remove_dbname_from_dsn(string $dsn): string
{
    $base = preg_replace('/;?dbname=[^;]*/i', '', $dsn);
    if (!is_string($base) || $base === '') {
        return $dsn;
    }

    return $base;
}

function db_build_connection_info(array $db): array
{
    $charset = (string)($db['charset'] ?? 'utf8mb4');
    $host = (string)($db['host'] ?? 'localhost');
    $name = trim((string)($db['name'] ?? 'pinkclub_fanza'));
    if ($name === '') {
        $name = 'pinkclub_fanza';
    }
    $dsn = (string)($db['dsn'] ?? '');

    if ($dsn === '') {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $name, $charset);
    } else {
        if (stripos($dsn, 'mysql:') !== 0) {
            throw new RuntimeException('MySQL以外のDSNはサポートしていません。DSN: ' . $dsn);
        }

        $parsedName = trim(db_extract_name_from_dsn($dsn));
        if ($parsedName !== '' && !isset($db['name'])) {
            $name = $parsedName;
        }

        if (preg_match('/(?:^|;)host=([^;]+)/i', $dsn, $hostMatches) === 1 && $hostMatches[1] !== '') {
            $host = trim($hostMatches[1]);
        }

        $dsnWithoutDb = db_remove_dbname_from_dsn($dsn);
        $dsn = rtrim($dsnWithoutDb, ';') . ';dbname=' . $name;
        if (stripos($dsn, 'charset=') === false) {
            $dsn .= ';charset=' . $charset;
        }
    }

    $serverDsn = db_remove_dbname_from_dsn($dsn);
    if (stripos($serverDsn, 'charset=') === false) {
        $serverDsn .= ';charset=' . $charset;
    }

    return [
        'dsn' => $dsn,
        'server_dsn' => $serverDsn,
        'db_name' => $name,
        'host' => $host,
        'charset' => $charset,
    ];
}

function db_build_init_error_message(string $stage, array $info, Throwable $e): string
{
    $host = (string)($info['host'] ?? '(unknown)');
    $dbName = (string)($info['db_name'] ?? '(unknown)');
    $dsn = (string)($info['dsn'] ?? '(unknown)');

    return "DB初期化に失敗しました。\n"
        . '段階: ' . $stage . "\n"
        . '接続先(host): ' . $host . "\n"
        . 'DB名: ' . $dbName . "\n"
        . 'DSN: ' . $dsn . "\n"
        . 'エラー: ' . $e->getMessage();
}

function db_mysql_driver_error_code(Throwable $e): int
{
    if ($e instanceof PDOException && isset($e->errorInfo[1])) {
        return (int)$e->errorInfo[1];
    }

    return (int)$e->getCode();
}

function db_statement_starts_with(string $sql, array $keywords): bool
{
    $trimmed = ltrim($sql);
    if ($trimmed === '') {
        return false;
    }

    foreach ($keywords as $keyword) {
        if (preg_match('/^' . preg_quote($keyword, '/') . '\\b/i', $trimmed) === 1) {
            return true;
        }
    }

    return false;
}

function db_should_ignore_statement_error(PDOException $e, string $sql = ''): bool
{
    $ignoreCodes = [1050, 1060, 1061, 1062, 1091];
    $errno = db_mysql_driver_error_code($e);
    if (in_array($errno, $ignoreCodes, true)) {
        return true;
    }

    if ($errno === 1146 && db_statement_starts_with($sql, ['DROP', 'ALTER'])) {
        return true;
    }

    return false;
}

function db_exec_statement(PDO $pdo, string $sql): void
{
    $statement = trim($sql);
    if ($statement === '') {
        return;
    }

    try {
        $stmt = $pdo->prepare($statement);
        if (!$stmt instanceof PDOStatement) {
            return;
        }

        try {
            $stmt->execute();

            do {
                if ($stmt->columnCount() > 0) {
                    $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } while ($stmt->nextRowset());
        } finally {
            $stmt->closeCursor();
        }
    } catch (PDOException $e) {
        if (db_should_ignore_statement_error($e, $statement)) {
            return;
        }

        throw $e;
    }
}

function db_create_database(PDO $pdo, array $connectionInfo): void
{
    $dbName = str_replace('`', '``', (string)($connectionInfo['db_name'] ?? ''));
    $charset = (string)($connectionInfo['charset'] ?? 'utf8mb4');
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $charset)) {
        $charset = 'utf8mb4';
    }

    $sql = sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` DEFAULT CHARACTER SET %s COLLATE %s_unicode_ci',
        $dbName,
        $charset,
        $charset
    );

    $pdo->exec($sql);
}


function db_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1');
    $stmt->execute([':table' => $table]);
    return db_stmt_fetch_one($stmt) !== false;
}

function db_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column LIMIT 1');
    $stmt->execute([':table' => $table, ':column' => $column]);
    return db_stmt_fetch_one($stmt) !== false;
}

function db_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index LIMIT 1');
    $stmt->execute([':table' => $table, ':index' => $index]);
    return db_stmt_fetch_one($stmt) !== false;
}

function db_repair_schema(PDO $pdo): void
{
    if (db_table_exists($pdo, 'items') && !db_column_exists($pdo, 'items', 'view_count')) {
        db_exec_statement($pdo, 'ALTER TABLE items ADD COLUMN view_count INT DEFAULT 0');
    }
    if (db_table_exists($pdo, 'items') && !db_index_exists($pdo, 'items', 'idx_items_view_count')) {
        db_exec_statement($pdo, 'CREATE INDEX idx_items_view_count ON items(view_count)');
    }

    if (db_table_exists($pdo, 'mutual_links')) {
        if (!db_column_exists($pdo, 'mutual_links', 'is_enabled')) {
            db_exec_statement($pdo, 'ALTER TABLE mutual_links ADD COLUMN is_enabled TINYINT(1) NOT NULL DEFAULT 1');
        }
        if (!db_column_exists($pdo, 'mutual_links', 'display_order')) {
            db_exec_statement($pdo, 'ALTER TABLE mutual_links ADD COLUMN display_order INT NOT NULL DEFAULT 100');
        }
        if (!db_column_exists($pdo, 'mutual_links', 'approved_at')) {
            db_exec_statement($pdo, 'ALTER TABLE mutual_links ADD COLUMN approved_at DATETIME NULL');
        }
        if (!db_index_exists($pdo, 'mutual_links', 'idx_mutual_links_status_enabled_order')) {
            db_exec_statement($pdo, 'CREATE INDEX idx_mutual_links_status_enabled_order ON mutual_links(status, is_enabled, display_order, id)');
        }
    }
}

function db_ensure_schema_migrations_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations ('
        . 'id INT AUTO_INCREMENT PRIMARY KEY,'
        . 'migration_name VARCHAR(255) NOT NULL,'
        . 'applied_at DATETIME NOT NULL,'
        . 'UNIQUE KEY uq_schema_migrations_name (migration_name)'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function db_apply_sql_file(PDO $pdo, string $filePath): void
{
    $sql = (string)file_get_contents($filePath);
    foreach (db_sql_split_statements($sql) as $statement) {
        db_exec_statement($pdo, $statement);
    }
}

function db_apply_migrations(PDO $pdo): void
{
    db_ensure_schema_migrations_table($pdo);

    $migrationFiles = glob(__DIR__ . '/../sql/migrations/*.sql');
    if (!is_array($migrationFiles) || $migrationFiles === []) {
        return;
    }

    sort($migrationFiles, SORT_STRING);

    $appliedRows = $pdo->query('SELECT migration_name FROM schema_migrations');
    $appliedNames = $appliedRows ? db_stmt_fetch_all($appliedRows, PDO::FETCH_COLUMN) : [];
    $appliedMap = [];
    foreach ($appliedNames as $name) {
        if (is_string($name) && $name !== '') {
            $appliedMap[$name] = true;
        }
    }

    $insert = $pdo->prepare('INSERT INTO schema_migrations (migration_name, applied_at) VALUES (:name, NOW())');

    foreach ($migrationFiles as $filePath) {
        $migrationName = basename($filePath);
        if (isset($appliedMap[$migrationName])) {
            continue;
        }

        db_apply_sql_file($pdo, $filePath);
        $insert->execute([':name' => $migrationName]);
        $appliedMap[$migrationName] = true;
    }
}

function db_ensure_initialized(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $checked = true;

    db_apply_sql_file($pdo, db_schema_file_path());
    db_repair_schema($pdo);
    db_apply_migrations($pdo);
    db_repair_schema($pdo);
    db_seed_default_admin_user($pdo);
}

function db_connect_and_initialize(array $db): PDO
{
    $user = (string)($db['user'] ?? 'root');
    $password = (string)($db['password'] ?? ($db['pass'] ?? ''));

    $defaultOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ];
    $configOptions = isset($db['options']) && is_array($db['options']) ? $db['options'] : [];
    $options = array_replace($defaultOptions, $configOptions);
    $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
    $options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = false;

    $connectionInfo = db_build_connection_info(is_array($db) ? $db : []);

    try {
        $pdo = new PDO($connectionInfo['dsn'], $user, $password, $options);
    } catch (PDOException $e) {
        if (db_mysql_driver_error_code($e) !== 1049) {
            throw new RuntimeException(db_build_init_error_message('データベース接続', $connectionInfo, $e), (int)$e->getCode(), $e);
        }

        try {
            $bootstrapPdo = new PDO($connectionInfo['server_dsn'], $user, $password, $options);
            db_create_database($bootstrapPdo, $connectionInfo);
            $pdo = new PDO($connectionInfo['dsn'], $user, $password, $options);
        } catch (Throwable $bootstrapError) {
            throw new RuntimeException(db_build_init_error_message('データベース作成', $connectionInfo, $bootstrapError), (int)$bootstrapError->getCode(), $bootstrapError);
        }
    } catch (Throwable $e) {
        throw new RuntimeException(db_build_init_error_message('データベース接続', $connectionInfo, $e), (int)$e->getCode(), $e);
    }

    try {
        db_ensure_initialized($pdo);
    } catch (Throwable $e) {
        throw new RuntimeException(db_build_init_error_message('schema/migration適用', $connectionInfo, $e), (int)$e->getCode(), $e);
    }

    return $pdo;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = config_get('db', []);
    $pdo = db_connect_and_initialize(is_array($db) ? $db : []);

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
