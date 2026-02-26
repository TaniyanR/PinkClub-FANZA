<?php

declare(strict_types=1);

function installer_logs_dir(): string
{
    return __DIR__ . '/../logs';
}

function installer_log_file_path(): string
{
    return installer_logs_dir() . '/install.log';
}

function installer_last_error_file_path(): string
{
    return installer_logs_dir() . '/install_last_error.json';
}

function installer_log(string $message): void
{
    $logDir = installer_logs_dir();
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    @file_put_contents(
        installer_log_file_path(),
        sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message),
        FILE_APPEND
    );
}

function installer_log_exception(string $step, Throwable $exception, ?string $sql = null): void
{
    installer_log(sprintf(
        'step=%s exception=%s message=%s location=%s:%d',
        $step,
        get_class($exception),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));

    if ($sql !== null && trim($sql) !== '') {
        installer_log('failed_sql=' . $sql);
    }
}

function installer_clear_last_error(): void
{
    $path = installer_last_error_file_path();
    if (is_file($path)) {
        @unlink($path);
    }
}

function installer_record_error_summary(string $step, Throwable $exception, ?string $failedSql = null): void
{
    $payload = [
        'time' => date('c'),
        'step' => $step,
        'class' => get_class($exception),
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
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
        return 'DBユーザー認証に失敗しました。config/config.php の設定を確認してください。';
    }

    return 'セットアップ中にエラーが発生しました。logs/install.log を確認してください。';
}

function installer_request_host(): string
{
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === '') {
        return '';
    }

    return explode(':', $host, 2)[0] ?? '';
}

function installer_request_remote_addr(): string
{
    return strtolower(trim((string)($_SERVER['REMOTE_ADDR'] ?? '')));
}

function installer_is_local_request(): bool
{
    $remote = installer_request_remote_addr();
    if (in_array($remote, ['127.0.0.1', '::1', 'localhost'], true)) {
        return true;
    }

    return in_array(installer_request_host(), ['localhost', '127.0.0.1'], true);
}

function installer_can_auto_run(): bool
{
    return installer_is_local_request();
}

function installer_auto_run_if_needed(): array
{
    $status = installer_status();
    if (($status['completed'] ?? false) === true) {
        return ['attempted' => false, 'success' => true, 'blocked' => false, 'result' => null];
    }

    if (!installer_can_auto_run()) {
        $message = '自動セットアップは localhost / 127.0.0.1 / ::1 でのみ実行できます。';
        installer_log('step=server_connection blocked host=' . installer_request_host() . ' remote=' . installer_request_remote_addr());
        return ['attempted' => false, 'success' => false, 'blocked' => true, 'message' => $message, 'result' => null];
    }

    $result = installer_run();
    return ['attempted' => true, 'success' => (bool)($result['success'] ?? false), 'blocked' => false, 'result' => $result];
}

function installer_can_connect_server(): bool
{
    try {
        db_server_pdo();
        return true;
    } catch (Throwable $exception) {
        installer_log_exception('server_connection', $exception);
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

    return (string)file_get_contents($path);
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

    $statements = array_filter(array_map('trim', explode(';', implode("\n", $filtered))), static fn ($s) => $s !== '');
    return array_values($statements);
}

function installer_execute_sql_file(PDO $pdo, string $path, string $step): int
{
    $executed = 0;
    foreach (installer_split_sql_statements(installer_read_sql_file($path)) as $statement) {
        try {
            $pdo->exec($statement);
            $executed++;
        } catch (Throwable $exception) {
            $GLOBALS['installer_last_failed_sql'] = $statement;
            installer_log_exception($step, $exception, $statement);
            throw $exception;
        }
    }

    return $executed;
}

function installer_ensure_admin_user(PDO $pdo, string $stepLabel): bool
{
    $stmt = $pdo->prepare('SELECT id FROM admins WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => 'admin']);
    if ($stmt->fetchColumn() !== false) {
        installer_log('step=' . $stepLabel . ' admin_exists=true');
        return false;
    }

    $insert = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (:username, :password_hash)');
    $insert->execute([
        'username' => 'admin',
        'password_hash' => password_hash('password', PASSWORD_DEFAULT),
    ]);
    installer_log('step=' . $stepLabel . ' admin_created=true');

    return true;
}

function installer_ensure_settings_row(PDO $pdo, string $stepLabel): bool
{
    $stmt = $pdo->query('SELECT COUNT(*) FROM settings WHERE id = 1');
    $exists = (int)$stmt->fetchColumn() > 0;
    if ($exists) {
        installer_log('step=' . $stepLabel . ' settings_row_exists=true');
        return false;
    }

    $insert = $pdo->prepare('INSERT INTO settings (id, api_id, affiliate_id) VALUES (1, :api_id, :affiliate_id)');
    $insert->execute(['api_id' => null, 'affiliate_id' => null]);
    installer_log('step=' . $stepLabel . ' settings_row_created=true');

    return true;
}

function installer_status(): array
{
    $status = [
        'server_connection' => false,
        'db_connection' => false,
        'admins_table' => false,
        'settings_table' => false,
        'admin_user' => false,
        'settings_row' => false,
        'completed' => false,
    ];

    $status['server_connection'] = installer_can_connect_server();
    if (!$status['server_connection']) {
        return $status;
    }

    $status['db_connection'] = db_can_connect();
    if (!$status['db_connection']) {
        return $status;
    }

    $status['admins_table'] = db_table_exists('admins');
    $status['settings_table'] = db_table_exists('settings');

    if ($status['admins_table']) {
        try {
            $stmt = db()->prepare('SELECT COUNT(*) FROM admins WHERE username = :username');
            $stmt->execute(['username' => 'admin']);
            $status['admin_user'] = (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $exception) {
            installer_log_exception('completion_check', $exception);
        }
    }

    if ($status['settings_table']) {
        try {
            $status['settings_row'] = (int)db()->query('SELECT COUNT(*) FROM settings WHERE id = 1')->fetchColumn() > 0;
        } catch (Throwable $exception) {
            installer_log_exception('completion_check', $exception);
        }
    }

    $status['completed'] = $status['server_connection']
        && $status['db_connection']
        && $status['admins_table']
        && $status['settings_table']
        && $status['admin_user']
        && $status['settings_row'];

    return $status;
}

function installer_run(): array
{
    $result = ['success' => false, 'steps' => [], 'error' => null, 'error_detail' => null, 'failed_sql' => null, 'error_summary' => null, 'log_tail' => null];
    $currentStep = 'server_connection';
    $step = static function (string $id, bool $ok, string $message = '') use (&$result): void {
        $result['steps'][] = ['id' => $id, 'status' => $ok ? 'ok' : 'ng', 'message' => $message];
    };

    try {
        installer_clear_last_error();
        unset($GLOBALS['installer_last_failed_sql']);

        if (!installer_can_connect_server()) {
            throw new RuntimeException('MySQLサーバーに接続できません。');
        }
        $step('server_connection', true);

        $currentStep = 'create_database';
        installer_ensure_database_exists();
        $step('create_database', true);

        $currentStep = 'create_tables';
        $tableCount = installer_execute_sql_file(db(), __DIR__ . '/../sql/schema.sql', 'create_tables');
        $step('create_tables', true, 'executed_sql=' . $tableCount);

        $currentStep = 'seed_data';
        $seedPath = __DIR__ . '/../sql/seed.sql';
        if (is_file($seedPath)) {
            installer_execute_sql_file(db(), $seedPath, 'seed_data');
        }
        installer_ensure_admin_user(db(), 'seed_data');
        installer_ensure_settings_row(db(), 'seed_data');
        $step('seed_data', true);

        $currentStep = 'completion_check';
        $databaseName = (string)db()->query('SELECT DATABASE()')->fetchColumn();
        installer_log('step=completion_check selected_database=' . $databaseName);

        installer_ensure_admin_user(db(), 'completion_check_retry');
        installer_ensure_settings_row(db(), 'completion_check_retry');

        $status = installer_status();
        if (($status['completed'] ?? false) !== true) {
            throw new RuntimeException('セットアップ完了条件を満たせませんでした。');
        }

        $step('completion_check', true);
        installer_log('step=completion_check status=ok');
        $result['success'] = true;
    } catch (Throwable $exception) {
        $failedSql = is_string($GLOBALS['installer_last_failed_sql'] ?? null) ? $GLOBALS['installer_last_failed_sql'] : null;
        installer_log_exception($currentStep, $exception, $failedSql);
        installer_record_error_summary($currentStep, $exception, $failedSql);

        $result['error'] = installer_user_error_message($exception);
        $result['error_detail'] = $exception->getMessage();
        $result['failed_sql'] = $failedSql;
        $step($currentStep, false, $result['error']);
    }

    $result['error_summary'] = installer_last_error_summary();
    $result['log_tail'] = installer_log_tail(30);
    return $result;
}
