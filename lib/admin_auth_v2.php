<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/url.php';
require_once __DIR__ . '/db.php';

const ADMIN_V2_DEFAULT_USERNAME = 'admin';
const ADMIN_V2_DEFAULT_PASSWORD = 'password';
const ADMIN_V2_SESSION_KEY = 'admin_user';
const ADMIN_V2_CSRF_KEY = 'admin_login_csrf';

function admin_v2_log(string $message, ?Throwable $exception = null): void
{
    if ($exception !== null) {
        error_log('[admin_auth_v2] ' . $message . ': ' . $exception->getMessage());
        return;
    }

    error_log('[admin_auth_v2] ' . $message);
}

function admin_v2_cookie_secure(): bool
{
    $https = (string)($_SERVER['HTTPS'] ?? '');
    if ($https === 'on' || $https === '1') {
        return true;
    }

    return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function admin_v2_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (headers_sent($file, $line)) {
        admin_v2_log('session start skipped: headers already sent at ' . $file . ':' . (string)$line);
        return;
    }

    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => (string)($params['path'] ?? '/'),
        'domain' => (string)($params['domain'] ?? ''),
        'secure' => admin_v2_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function admin_v2_users_table_available(): bool
{
    try {
        $stmt = db()->query("SHOW TABLES LIKE 'admin_users'");
        return $stmt !== false && $stmt->fetchColumn() !== false;
    } catch (Throwable $exception) {
        admin_v2_log('admin_users table check failed', $exception);
        return false;
    }
}

function admin_v2_ensure_default_admin(): void
{
    if (!admin_v2_users_table_available()) {
        return;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => ADMIN_V2_DEFAULT_USERNAME]);
        if ($stmt->fetchColumn() !== false) {
            return;
        }

        $columnsStmt = $pdo->query('SHOW COLUMNS FROM admin_users');
        $columns = $columnsStmt ? $columnsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
        if (!is_array($columns)) {
            $columns = [];
        }

        $values = [
            'username' => ADMIN_V2_DEFAULT_USERNAME,
            'email' => '',
            'password_hash' => password_hash(ADMIN_V2_DEFAULT_PASSWORD, PASSWORD_DEFAULT),
            'password' => null,
            'display_name' => ADMIN_V2_DEFAULT_USERNAME,
            'role' => 'admin',
            'login_mode' => 'username',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $insertColumns = [];
        $params = [];
        foreach ($values as $column => $value) {
            if (!in_array($column, $columns, true)) {
                continue;
            }
            $insertColumns[] = $column;
            $params[':' . $column] = $value;
        }

        if (!in_array('username', $insertColumns, true) || !in_array('password_hash', $insertColumns, true)) {
            return;
        }

        $sql = sprintf(
            'INSERT INTO admin_users (%s) VALUES (%s)',
            implode(', ', $insertColumns),
            implode(', ', array_keys($params))
        );
        $pdo->prepare($sql)->execute($params);
    } catch (Throwable $exception) {
        admin_v2_log('default admin creation failed', $exception);
    }
}

function admin_v2_find_user(string $identifier): ?array
{
    if (!admin_v2_users_table_available()) {
        return null;
    }

    try {
        $pdo = db();
        $hasPassword = db_column_exists($pdo, 'admin_users', 'password');
        $fields = ['id', 'username', 'email', 'password_hash', 'is_active'];
        if ($hasPassword) {
            $fields[] = 'password';
        }

        $sql = 'SELECT ' . implode(', ', $fields) . ' FROM admin_users WHERE username = :id OR email = :id LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    } catch (Throwable $exception) {
        admin_v2_log('find user failed', $exception);
        return null;
    }
}

function admin_v2_upgrade_legacy_password(array $user, string $password): void
{
    $userId = (int)($user['id'] ?? 0);
    $legacyPassword = (string)($user['password'] ?? '');
    if ($userId <= 0 || $legacyPassword === '' || !hash_equals($legacyPassword, $password)) {
        return;
    }

    try {
        db()->prepare('UPDATE admin_users SET password_hash = :hash, password = NULL, updated_at = NOW() WHERE id = :id LIMIT 1')
            ->execute([
                ':hash' => password_hash($password, PASSWORD_DEFAULT),
                ':id' => $userId,
            ]);
    } catch (Throwable $exception) {
        admin_v2_log('legacy password migration failed', $exception);
    }
}

function admin_v2_config_authenticate(string $identifier, string $password): ?array
{
    $configUsername = trim((string)config_get('admin.username', ADMIN_V2_DEFAULT_USERNAME));
    if ($configUsername === '') {
        $configUsername = ADMIN_V2_DEFAULT_USERNAME;
    }

    if (!hash_equals($configUsername, $identifier)) {
        return null;
    }

    $configHash = trim((string)config_get('admin.password_hash', ''));
    if ($configHash !== '' && password_verify($password, $configHash)) {
        return ['id' => 1, 'username' => $configUsername, 'email' => ''];
    }

    $configPlain = (string)config_get('admin.password', ADMIN_V2_DEFAULT_PASSWORD);
    if ($configPlain === '') {
        $configPlain = ADMIN_V2_DEFAULT_PASSWORD;
    }

    if (!hash_equals($configPlain, $password)) {
        return null;
    }

    return ['id' => 1, 'username' => $configUsername, 'email' => ''];
}

function admin_v2_store_login(array $user): void
{
    admin_v2_session_start();
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    session_regenerate_id(true);
    $_SESSION[ADMIN_V2_SESSION_KEY] = [
        'id' => (int)($user['id'] ?? 0),
        'username' => (string)($user['username'] ?? ''),
        'email' => (string)($user['email'] ?? ''),
    ];
}

function admin_v2_login(string $identifier, string $password): bool
{
    admin_v2_session_start();

    $identifier = trim($identifier);
    if ($identifier === '' || $password === '') {
        return false;
    }

    admin_v2_ensure_default_admin();

    $user = admin_v2_find_user($identifier);
    if (is_array($user)) {
        if ((int)($user['is_active'] ?? 0) !== 1) {
            return false;
        }

        $hash = (string)($user['password_hash'] ?? '');
        if ($hash !== '' && password_verify($password, $hash)) {
            admin_v2_store_login($user);
            return true;
        }

        $legacy = (string)($user['password'] ?? '');
        if ($hash === '' && $legacy !== '' && hash_equals($legacy, $password)) {
            admin_v2_upgrade_legacy_password($user, $password);
            admin_v2_store_login($user);
            return true;
        }

        return false;
    }

    $configUser = admin_v2_config_authenticate($identifier, $password);
    if (!is_array($configUser)) {
        return false;
    }

    admin_v2_store_login($configUser);
    return true;
}

function admin_v2_current_user(): ?array
{
    admin_v2_session_start();

    $sessionUser = $_SESSION[ADMIN_V2_SESSION_KEY] ?? null;
    if (!is_array($sessionUser)) {
        return null;
    }

    $id = (int)($sessionUser['id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    if (!admin_v2_users_table_available()) {
        return [
            'id' => $id,
            'username' => (string)($sessionUser['username'] ?? ''),
            'email' => (string)($sessionUser['email'] ?? ''),
        ];
    }

    try {
        $stmt = db()->prepare('SELECT id, username, email, is_active, password_hash FROM admin_users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($user)) {
            return [
                'id' => $id,
                'username' => (string)($sessionUser['username'] ?? ''),
                'email' => (string)($sessionUser['email'] ?? ''),
            ];
        }

        if ((int)($user['is_active'] ?? 0) !== 1) {
            return null;
        }

        return [
            'id' => (int)$user['id'],
            'username' => (string)($user['username'] ?? ''),
            'email' => (string)($user['email'] ?? ''),
            'password_hash' => (string)($user['password_hash'] ?? ''),
        ];
    } catch (Throwable $exception) {
        admin_v2_log('current user lookup failed', $exception);
        return [
            'id' => $id,
            'username' => (string)($sessionUser['username'] ?? ''),
            'email' => (string)($sessionUser['email'] ?? ''),
        ];
    }
}

function admin_v2_is_logged_in(): bool
{
    return admin_v2_current_user() !== null;
}

function admin_v2_require_login(): void
{
    if (admin_v2_is_logged_in()) {
        return;
    }

    app_redirect(login_path());
}

function admin_v2_logout(): void
{
    admin_v2_session_start();
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => (string)($params['path'] ?? '/'),
            'domain' => (string)($params['domain'] ?? ''),
            'secure' => (bool)($params['secure'] ?? false),
            'httponly' => (bool)($params['httponly'] ?? true),
            'samesite' => (string)($params['samesite'] ?? 'Lax'),
        ]);
    }

    session_destroy();
}

function admin_v2_csrf_token(): string
{
    admin_v2_session_start();
    $token = $_SESSION[ADMIN_V2_CSRF_KEY] ?? null;
    if (!is_string($token) || $token === '') {
        $token = bin2hex(random_bytes(32));
        $_SESSION[ADMIN_V2_CSRF_KEY] = $token;
    }

    return $token;
}

function admin_v2_csrf_verify(?string $token): bool
{
    admin_v2_session_start();
    $sessionToken = $_SESSION[ADMIN_V2_CSRF_KEY] ?? null;
    return is_string($token) && is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);
}
