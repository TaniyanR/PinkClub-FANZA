<?php

declare(strict_types=1);

function installer_log(string $message): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
    @file_put_contents($logDir . '/install.log', $line, FILE_APPEND);
}

function installer_logs_dir(): string
{
    return __DIR__ . '/../logs';
}

function installer_log_file_path(): string
{
    return installer_logs_dir() . '/install.log';
}

function installer_lock_file_path(): string
{
    return installer_logs_dir() . '/install.lock';
}

function installer_last_error_file_path(): string
{
    return installer_logs_dir() . '/install_last_error.json';
}

function installer_lock_exists(): bool
{
    return is_file(installer_lock_file_path());
}

function installer_mark_completed(): void
{
    $logDir = installer_logs_dir();
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents(installer_lock_file_path(), date('c') . "\n");
}

function installer_clear_last_error(): void
{
    $path = installer_last_error_file_path();
    if (is_file($path)) {
        @unlink($path);
    }
}

function installer_record_error_summary(Throwable $exception, ?string $failedSql = null): void
{
    $payload = [
        'time' => date('c'),
        'class' => get_class($exception),
        'message' => $exception->getMessage(),
        'failed_sql' => $failedSql,
    ];

    $logDir = installer_logs_dir();
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    @file_put_contents(
        installer_last_error_file_path(),
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
    );
}

function installer_last_error_summary(): ?array
{
    $path = installer_last_error_file_path();
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function installer_log_tail(int $maxLines = 20): array
{
    $path = installer_log_file_path();
    if (!is_file($path)) {
        return ['lines' => [], 'error' => 'install.log が存在しません。'];
    }

    if (!is_readable($path)) {
        return ['lines' => [], 'error' => 'install.log を読み取る権限がありません。'];
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
        return ['lines' => [], 'error' => 'install.log の読み取りに失敗しました。'];
    }

    return ['lines' => array_slice($lines, -$maxLines), 'error' => null];
}

function installer_user_error_message(Throwable $exception): string
{
    $message = $exception->getMessage();

    if (str_contains($message, 'SQLSTATE[HY000] [2002]')) {
        return 'MySQLサーバーへ接続できません。XAMPPのMySQL起動と接続設定を確認してください。';
    }

    if (str_contains($message, 'Access denied')) {
        return 'DBユーザー認証に失敗しました。config/config.php のユーザー名・パスワードを確認してください。';
    }

    if (str_contains($message, 'Unknown database')) {
        return 'データベースが見つかりません。セットアップを実行してDBを作成してください。';
    }

    return 'セットアップ中にエラーが発生しました。logs/install.log を確認してください。';
}

function installer_request_host(): string
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return '';
    }

    return strtolower(trim(explode(':', $host, 2)[0]));
}

function installer_request_remote_addr(): string
{
    return strtolower(trim((string)($_SERVER['REMOTE_ADDR'] ?? '')));
}

function installer_is_local_request(): bool
{
    $remoteAddr = installer_request_remote_addr();
    if (in_array($remoteAddr, ['127.0.0.1', '::1', 'localhost'], true)) {
        return true;
    }

    $host = installer_request_host();
    return in_array($host, ['localhost', '127.0.0.1'], true);
}

function installer_can_auto_run(): bool
{
    return installer_is_local_request();
}

function installer_auto_run_if_needed(): array
{
    if (installer_is_completed()) {
        return ['attempted' => false, 'success' => true, 'blocked' => false, 'result' => null];
    }

    if (!installer_can_auto_run()) {
        $message = 'auto setup blocked for host: ' . (installer_request_host() ?: '(unknown)');
        installer_log($message);
        return [
            'attempted' => false,
            'success' => false,
            'blocked' => true,
            'message' => '自動セットアップは localhost / 127.0.0.1 / ::1 でのみ実行できます。',
            'result' => null,
        ];
    }

    $result = installer_run();
    return [
        'attempted' => true,
        'success' => (bool)($result['success'] ?? false),
        'blocked' => false,
        'result' => $result,
    ];
}

function installer_can_connect_server(): bool
{
    try {
        db_server_pdo();
        return true;
    } catch (Throwable $exception) {
        installer_log('server connection failed: ' . $exception->getMessage());
        return false;
    }
}

function installer_ensure_database_exists(): void
{
    $cfg = app_config()['db'];
    $dbName = (string)$cfg['dbname'];
    $sql = sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        str_replace('`', '``', $dbName)
    );

    db_server_pdo()->exec($sql);
    db_reset_connections();
}

function installer_read_sql_file(string $path): string
{
    if (!is_file($path)) {
        throw new RuntimeException('SQLファイルが見つかりません: ' . $path);
    }

    $sql = (string)file_get_contents($path);
    if ($sql === '') {
        throw new RuntimeException('SQLファイルが空です: ' . $path);
    }

    return $sql;
}

function installer_split_sql_statements(string $sql): array
{
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql) ?? $sql;
    $lines = preg_split('/\R/', $sql) ?: [];
    $filtered = [];

    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
            continue;
        }
        $filtered[] = $line;
    }

    $sql = implode("\n", $filtered);

    $statements = [];
    $buffer = '';
    $inString = false;
    $quote = '';
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $prev = $i > 0 ? $sql[$i - 1] : '';

        if (($char === '"' || $char === "'") && $prev !== '\\') {
            if (!$inString) {
                $inString = true;
                $quote = $char;
            } elseif ($quote === $char) {
                $inString = false;
                $quote = '';
            }
        }

        if ($char === ';' && !$inString) {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
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

function installer_execute_sql_file(PDO $pdo, string $path): int
{
    $sql = installer_read_sql_file($path);
    $statements = installer_split_sql_statements($sql);

    $executed = 0;
    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
            $executed++;
        } catch (Throwable $exception) {
            $GLOBALS['installer_last_failed_sql'] = $statement;
            installer_log('sql failed [' . basename($path) . ']: ' . $statement);
            installer_log('sql error: ' . $exception->getMessage());
            throw $exception;
        }
    }

    return $executed;
}

function installer_ensure_tables_exist(): int
{
    $schemaPath = __DIR__ . '/../sql/schema.sql';
    return installer_execute_sql_file(db_pdo(), $schemaPath);
}

function installer_ensure_seed_data(): void
{
    $pdo = db_pdo();
    $seedPath = __DIR__ . '/../sql/seed.sql';
    $pdo->beginTransaction();
    try {
        if (is_file($seedPath)) {
            try {
                installer_execute_sql_file($pdo, $seedPath);
                installer_log('seed.sql executed');
            } catch (Throwable $exception) {
                installer_log('seed.sql failed, fallback guarantee continues: ' . $exception->getMessage());
            }
        }

        $stmt = $pdo->prepare('SELECT id FROM admins WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => 'admin']);
        $admin = $stmt->fetch();

        if ($admin === false) {
            $insert = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (:username, :password_hash)');
            $insert->execute([
                'username' => 'admin',
                'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            ]);
            installer_log('seed guarantee: admin user created');
        }

        $settingsExists = (int)$pdo->query('SELECT COUNT(*) FROM settings WHERE id = 1')->fetchColumn() > 0;
        if (!$settingsExists) {
            $insert = $pdo->prepare('INSERT INTO settings (id, api_id, affiliate_id) VALUES (1, :api_id, :affiliate_id)');
            $insert->execute([
                'api_id' => '',
                'affiliate_id' => '',
            ]);
            installer_log('seed guarantee: settings row created');
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function installer_is_completed(): bool
{
    if (!db_can_connect()) {
        return false;
    }

    if (!db_table_exists('admins') || !db_table_exists('settings')) {
        return false;
    }

    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM admins WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => 'admin']);
        $adminExists = (int)$stmt->fetchColumn() > 0;

        $settingsExists = (int)db()->query('SELECT COUNT(*) FROM settings WHERE id = 1')->fetchColumn() > 0;

        return $adminExists && $settingsExists && installer_lock_exists();
    } catch (Throwable) {
        return false;
    }
}

function installer_status(): array
{
    $serverConnected = installer_can_connect_server();
    $dbConnected = db_can_connect();
    $adminsTable = $dbConnected && db_table_exists('admins');
    $settingsTable = $dbConnected && db_table_exists('settings');

    $adminExists = false;
    $settingsRowExists = false;
    if ($adminsTable) {
        try {
            $stmt = db()->prepare('SELECT COUNT(*) FROM admins WHERE username = :username LIMIT 1');
            $stmt->execute(['username' => 'admin']);
            $adminExists = (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $exception) {
            installer_log('status check admin failed: ' . $exception->getMessage());
        }
    }

    if ($settingsTable) {
        try {
            $settingsRowExists = (int)db()->query('SELECT COUNT(*) FROM settings WHERE id = 1')->fetchColumn() > 0;
        } catch (Throwable $exception) {
            installer_log('status check settings row failed: ' . $exception->getMessage());
        }
    }

    return [
        'server_connection' => $serverConnected,
        'db_connection' => $dbConnected,
        'admins_table' => $adminsTable,
        'settings_table' => $settingsTable,
        'admin_user' => $adminExists,
        'settings_row' => $settingsRowExists,
        'install_lock' => installer_lock_exists(),
        'completed' => $dbConnected && $adminsTable && $settingsTable && $adminExists && $settingsRowExists && installer_lock_exists(),
    ];
}

function installer_run(): array
{
    $result = [
        'success' => false,
        'steps' => [],
        'error' => null,
        'error_detail' => null,
    ];

    $step = static function (string $label, string $status, ?string $message = null) use (&$result): void {
        $row = ['label' => $label, 'status' => $status];
        if ($message !== null && $message !== '') {
            $row['message'] = $message;
        }
        $result['steps'][] = $row;
    };

    try {
        installer_clear_last_error();
        unset($GLOBALS['installer_last_failed_sql']);
        if (!installer_can_connect_server()) {
            throw new RuntimeException('MySQLサーバーに接続できません。');
        }
        $step('サーバー接続', 'ok');

        installer_ensure_database_exists();
        $step('DB作成', 'ok');

        $executed = installer_ensure_tables_exist();
        $step('テーブル作成', 'ok', $executed . ' 件のSQLを実行');

        installer_ensure_seed_data();
        $step('初期データ投入', 'ok');

        installer_mark_completed();

        if (!installer_is_completed()) {
            throw new RuntimeException('セットアップ完了条件を満たせませんでした。');
        }
        $step('完了判定', 'ok');
        $result['success'] = true;
        installer_log('setup completed successfully');
    } catch (Throwable $exception) {
        $failedSql = is_string($GLOBALS['installer_last_failed_sql'] ?? null) ? $GLOBALS['installer_last_failed_sql'] : null;
        installer_record_error_summary($exception, $failedSql);
        $result['error'] = installer_user_error_message($exception);
        $result['error_detail'] = $exception->getMessage();
        if ($failedSql !== null) {
            $result['failed_sql'] = $failedSql;
        }
        installer_log('setup failed: ' . $exception->getMessage());
        $step('エラー', 'ng', $result['error']);
    }

    return $result;
}
