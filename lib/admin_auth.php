<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/url.php';
require_once __DIR__ . '/db.php';

const ADMIN_DEFAULT_USERNAME = 'admin';
const ADMIN_DEFAULT_PASSWORD = 'password';

function admin_auth_log_error(string $message, ?Throwable $exception = null): void
{
    if ($exception !== null) {
        error_log('[admin_auth] ' . $message . ': ' . $exception->getMessage());
        return;
    }
    error_log('[admin_auth] ' . $message);
}

function start_admin_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function admin_session_start(): void
{
    start_admin_session();
}

function admin_users_table_available(): bool
{
    try {
        $stmt = db()->query("SHOW TABLES LIKE 'admin_users'");
        return $stmt !== false && $stmt->fetchColumn() !== false;
    } catch (Throwable $exception) {
        admin_auth_log_error('admin_users_table_available failed', $exception);
        return false;
    }
}

function admin_ensure_default_user(): void
{
    if (!admin_users_table_available()) {
        return;
    }

    try {
        $exists = db()->prepare('SELECT id FROM admin_users WHERE username = ? LIMIT 1');
        $exists->execute([ADMIN_DEFAULT_USERNAME]);
        if ($exists->fetchColumn() !== false) {
            return;
        }

        $hash = password_hash(ADMIN_DEFAULT_PASSWORD, PASSWORD_DEFAULT);
        $insert = db()->prepare('INSERT INTO admin_users (username, password_hash, display_name, email, login_mode, role, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())');
        $insert->execute([ADMIN_DEFAULT_USERNAME, $hash, ADMIN_DEFAULT_USERNAME, '', 'username', 'admin']);
    } catch (Throwable $exception) {
        admin_auth_log_error('admin_ensure_default_user failed', $exception);
    }
}

function admin_find_user_by_identifier(string $identifier): ?array
{
    if (!admin_users_table_available()) {
        return null;
    }

    try {
        $pdo = db();
        $hasLegacyPassword = db_column_exists($pdo, 'admin_users', 'password');
        $columns = ['id', 'username', 'email', 'password_hash', 'is_active'];
        if ($hasLegacyPassword) {
            $columns[] = 'password';
        }

        $sql = 'SELECT ' . implode(', ', $columns) . ' FROM admin_users WHERE (username = ? OR email = ?) LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$identifier, $identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    } catch (Throwable $exception) {
        admin_auth_log_error('admin_find_user_by_identifier failed', $exception);
        return null;
    }
}

function admin_verify_password(array $user, string $password): bool
{
    $hash = (string)($user['password_hash'] ?? '');
    if ($hash !== '' && password_verify($password, $hash)) {
        return true;
    }

    if (!array_key_exists('password', $user)) {
        return false;
    }

    $legacy = (string)($user['password'] ?? '');
    return $legacy !== '' && hash_equals($legacy, $password);
}

function admin_password_verified_by_legacy(array $user, string $password): bool
{
    if (!array_key_exists('password', $user)) {
        return false;
    }

    $hash = (string)($user['password_hash'] ?? '');
    if ($hash !== '' && password_verify($password, $hash)) {
        return false;
    }

    $legacy = (string)($user['password'] ?? '');
    return $legacy !== '' && hash_equals($legacy, $password);
}

function admin_upgrade_password_hash_if_needed(array $user, string $plainPassword): void
{
    if (!admin_password_verified_by_legacy($user, $plainPassword)) {
        return;
    }

    $id = (int)($user['id'] ?? 0);
    if ($id <= 0) {
        return;
    }

    try {
        $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $sql = 'UPDATE admin_users SET password_hash = ?, updated_at = NOW() WHERE id = ? LIMIT 1';
        if (array_key_exists('password', $user)) {
            $sql = 'UPDATE admin_users SET password_hash = ?, password = NULL, updated_at = NOW() WHERE id = ? LIMIT 1';
        }
        db()->prepare($sql)->execute([$newHash, $id]);
    } catch (Throwable $exception) {
        admin_auth_log_error('admin_upgrade_password_hash_if_needed failed', $exception);
    }
}

function admin_config_authenticate(string $identifier, string $password): ?array
{
    $configUsername = (string)config_get('admin.username', ADMIN_DEFAULT_USERNAME);
    if ($configUsername === '') {
        $configUsername = ADMIN_DEFAULT_USERNAME;
    }

    if (!hash_equals($configUsername, $identifier)) {
        return null;
    }

    $configHash = (string)config_get('admin.password_hash', '');
    if ($configHash !== '' && password_verify($password, $configHash)) {
        return ['id' => 1, 'username' => $configUsername, 'email' => ''];
    }

    $configPlain = (string)config_get('admin.password', ADMIN_DEFAULT_PASSWORD);
    if ($configPlain === '') {
        $configPlain = ADMIN_DEFAULT_PASSWORD;
    }

    if (hash_equals($configPlain, $password)) {
        return ['id' => 1, 'username' => $configUsername, 'email' => ''];
    }

    return null;
}

function admin_login(string $identifier, string $password): bool
{
    start_admin_session();

    $identifier = trim($identifier);
    if ($identifier === '') {
        return false;
    }

    admin_ensure_default_user();
    $user = admin_find_user_by_identifier($identifier);

    if (is_array($user)) {
        if ((int)($user['is_active'] ?? 1) !== 1) {
            return false;
        }
        if (!admin_verify_password($user, $password)) {
            return false;
        }

        admin_upgrade_password_hash_if_needed($user, $password);
        admin_login_store_session($user);
        return true;
    }

    $configUser = admin_config_authenticate($identifier, $password);
    if (!is_array($configUser)) {
        return false;
    }

    admin_login_store_session($configUser);
    return true;
}

function admin_login_store_session(array $user): void
{
    start_admin_session();
    session_regenerate_id(true);
    $_SESSION['admin_user'] = [
        'id' => (int)($user['id'] ?? 0),
        'username' => (string)($user['username'] ?? ''),
        'email' => (string)($user['email'] ?? ''),
    ];
}

function admin_find_user_by_id(int $id): ?array
{
    if ($id <= 0 || !admin_users_table_available()) {
        return null;
    }

    try {
        $stmt = db()->prepare('SELECT id, username, email, password_hash, is_active FROM admin_users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    } catch (Throwable $exception) {
        admin_auth_log_error('admin_find_user_by_id failed', $exception);
        return null;
    }
}

function admin_current_user(): ?array
{
    start_admin_session();

    $sessionUser = $_SESSION['admin_user'] ?? null;
    if (!is_array($sessionUser)) {
        return null;
    }

    $id = (int)($sessionUser['id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $dbUser = admin_find_user_by_id($id);
    if (is_array($dbUser) && (int)($dbUser['is_active'] ?? 0) === 1) {
        return [
            'id' => (int)$dbUser['id'],
            'username' => (string)($dbUser['username'] ?? ''),
            'email' => (string)($dbUser['email'] ?? ''),
            'password_hash' => (string)($dbUser['password_hash'] ?? ''),
        ];
    }

    return [
        'id' => $id,
        'username' => (string)($sessionUser['username'] ?? ''),
        'email' => (string)($sessionUser['email'] ?? ''),
    ];
}

function admin_is_logged_in(): bool
{
    return admin_current_user() !== null;
}

function admin_logout(): void
{
    start_admin_session();
    unset($_SESSION['admin_user']);
    session_regenerate_id(true);
}

function require_admin_auth(): void
{
    require_admin_login();
}

function admin_require_login(): void
{
    require_admin_login();
}

function require_admin_login(): void
{
    if (admin_is_logged_in()) {
        return;
    }
    app_redirect(login_path());
}

function admin_is_default_password(array $admin): bool
{
    $passwordHash = (string)($admin['password_hash'] ?? '');
    if ($passwordHash === '') {
        return false;
    }

    return password_verify(ADMIN_DEFAULT_PASSWORD, $passwordHash);
}

function admin_require_password_change_if_needed(): void
{
    return;
}
