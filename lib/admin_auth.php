<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/url.php';
require_once __DIR__ . '/db.php';

const ADMIN_DEFAULT_USERNAME = 'admin';
const ADMIN_DEFAULT_PASSWORD = 'password';

/**
 * 開発環境でのみエラー表示を有効化する。
 */
function admin_enable_debug_if_dev(): void
{
    if (!admin_is_dev_env()) {
        return;
    }

    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

/**
 * 管理画面向けのリダイレクト先を正規化する。
 */
function normalize_admin_redirect_target(string $target): string
{
    $fallback = admin_path('index.php');
    $trimmed = trim(str_replace(["\r", "\n"], '', $target));
    if ($trimmed === '') {
        return $fallback;
    }

    if (preg_match('#^https?://#i', $trimmed) === 1) {
        return $fallback;
    }

    $parsed = parse_url($trimmed);
    if (!is_array($parsed)) {
        return $fallback;
    }

    $path = (string)($parsed['path'] ?? '');
    if ($path === '') {
        return $fallback;
    }

    if ($path[0] !== '/') {
        $path = admin_path($path);
    }

    $adminBase = rtrim(admin_path(''), '/');
    if ($path !== $adminBase && !str_starts_with($path, $adminBase . '/')) {
        return $fallback;
    }

    if (preg_match('#^[A-Za-z0-9_./\-]+$#', str_replace($adminBase, '', $path)) !== 1) {
        return $fallback;
    }

    $query = isset($parsed['query']) && is_string($parsed['query']) ? $parsed['query'] : '';
    $fragment = isset($parsed['fragment']) && is_string($parsed['fragment']) ? $parsed['fragment'] : '';

    return $path
        . ($query !== '' ? '?' . $query : '')
        . ($fragment !== '' ? '#' . $fragment : '');
}

function admin_is_dev_env(): bool
{
    $env = strtolower((string)config_get('app.env', ''));
    return in_array($env, ['dev', 'development', 'local', 'staging'], true);
}

/**
 * 管理画面セッションを開始する。
 */
function start_admin_session(): void
{
    admin_enable_debug_if_dev();

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
    } catch (Throwable $e) {
        error_log('[admin_auth] admin_users_table_available failed: ' . $e->getMessage());
        return false;
    }
}

function admin_ensure_default_user(): void
{
    if (!admin_users_table_available()) {
        return;
    }

    $stmt = db()->prepare('SELECT id FROM admin_users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => ADMIN_DEFAULT_USERNAME]);
    if ($stmt->fetchColumn() !== false) {
        return;
    }

    $insert = db()->prepare('INSERT INTO admin_users (username, password_hash, display_name, email, login_mode, role, is_active, created_at, updated_at) VALUES (:username, :password_hash, :display_name, NULL, "username", "admin", 1, NOW(), NOW())');
    $insert->execute([
        ':username' => ADMIN_DEFAULT_USERNAME,
        ':password_hash' => password_hash(ADMIN_DEFAULT_PASSWORD, PASSWORD_DEFAULT),
        ':display_name' => ADMIN_DEFAULT_USERNAME,
    ]);
}

function admin_current_user(): ?array
{
    start_admin_session();

    $current = $_SESSION['admin_user'] ?? null;

    if (!is_array($current)) {
        return null;
    }

    $id = $current['id'] ?? null;
    $username = $current['username'] ?? null;

    if ((!is_int($id) && !ctype_digit((string)$id)) || !is_string($username) || $username === '') {
        return null;
    }

    return [
        'id' => (int)$id,
        'username' => $username,
        'email' => is_string($current['email'] ?? null) ? (string)$current['email'] : null,
        'login_mode' => is_string($current['login_mode'] ?? null) ? (string)$current['login_mode'] : null,
        'password_hash' => is_string($current['password_hash'] ?? null) ? (string)$current['password_hash'] : '',
    ];
}

function admin_is_logged_in(): bool
{
    $current = admin_current_user();
    return is_array($current) && (int)($current['id'] ?? 0) > 0;
}

function admin_find_user_by_identifier(string $identifier): ?array
{
    $pdo = db();

    $hasLegacyPasswordColumn = false;
    try {
        $hasLegacyPasswordColumn = db_column_exists($pdo, 'admin_users', 'password');
    } catch (Throwable $e) {
        error_log('[admin_auth] failed to inspect admin_users.password column: ' . $e->getMessage());
    }

    $selectColumns = [
        'id',
        'username',
        'email',
        'password_hash',
        'is_active',
    ];
    if ($hasLegacyPasswordColumn) {
        $selectColumns[] = 'password';
    }

    $sql = 'SELECT ' . implode(', ', $selectColumns) . '
            FROM admin_users
            WHERE (username = :identifier OR email = :identifier)
            LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':identifier' => $identifier,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function admin_verify_password_with_legacy_support(array $adminUser, string $plainPassword): bool
{
    $passwordHash = (string)($adminUser['password_hash'] ?? '');
    if ($passwordHash !== '' && password_verify($plainPassword, $passwordHash)) {
        return true;
    }

    if (!array_key_exists('password', $adminUser)) {
        return false;
    }

    $legacyPassword = (string)($adminUser['password'] ?? '');
    if ($legacyPassword === '' || !hash_equals($legacyPassword, $plainPassword)) {
        return false;
    }

    try {
        $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $update = db()->prepare('UPDATE admin_users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id LIMIT 1');
        $update->execute([
            ':password_hash' => $newHash,
            ':id' => (int)($adminUser['id'] ?? 0),
        ]);
    } catch (Throwable $exception) {
        error_log('[admin_auth] legacy password migration failed: ' . $exception->getMessage());
    }

    return true;
}

function admin_config_authenticate(string $identifier, string $password): ?array
{
    $configUsername = (string)config_get('admin.username', ADMIN_DEFAULT_USERNAME);
    $configPasswordHash = (string)config_get('admin.password_hash', '');

    if ($configPasswordHash === '' || !hash_equals($configUsername, $identifier)) {
        return null;
    }

    if (!password_verify($password, $configPasswordHash)) {
        return null;
    }

    return [
        'id' => 1,
        'username' => $configUsername,
        'email' => null,
        'login_mode' => 'config',
        'password_hash' => $configPasswordHash,
    ];
}

/**
 * 管理者ログインを試行し、結果メタ情報を返す。
 */
function admin_attempt_login(string $username_or_email, string $password): array
{
    start_admin_session();

    $identifier = trim($username_or_email);
    if ($identifier === '') {
        return [
            'success' => false,
            'auth_source' => 'unknown',
            'admin_users_table_available' => admin_users_table_available(),
            'failure_reason' => 'identifier empty',
        ];
    }

    $tableAvailable = admin_users_table_available();
    if ($tableAvailable) {
        admin_ensure_default_user();

        $admin = admin_find_user_by_identifier($identifier);
        if (!is_array($admin)) {
            error_log('[admin_auth] login failed: user not found');
            return [
                'success' => false,
                'auth_source' => 'db',
                'admin_users_table_available' => true,
                'failure_reason' => 'user not found',
            ];
        }

        if (!admin_verify_password_with_legacy_support($admin, $password)) {
            error_log('[admin_auth] login failed: password mismatch');
            return [
                'success' => false,
                'auth_source' => 'db',
                'admin_users_table_available' => true,
                'failure_reason' => 'password mismatch',
            ];
        }

        admin_login_store_session([
            'id' => (int)$admin['id'],
            'username' => (string)$admin['username'],
            'email' => isset($admin['email']) && is_string($admin['email']) ? $admin['email'] : null,
            'login_mode' => isset($admin['login_mode']) && is_string($admin['login_mode']) ? $admin['login_mode'] : null,
            'password_hash' => (string)($admin['password_hash'] ?? ''),
        ]);

        return [
            'success' => true,
            'auth_source' => 'db',
            'admin_users_table_available' => true,
            'failure_reason' => '',
        ];
    }

    $admin = admin_config_authenticate($identifier, $password);
    if (!is_array($admin)) {
        error_log('[admin_auth] login failed: config auth failed');
        return [
            'success' => false,
            'auth_source' => 'config',
            'admin_users_table_available' => false,
            'failure_reason' => 'config auth failed',
        ];
    }

    admin_login_store_session([
        'id' => 1,
        'username' => (string)$admin['username'],
        'email' => null,
        'login_mode' => 'config',
        'password_hash' => (string)$admin['password_hash'],
    ]);

    return [
        'success' => true,
        'auth_source' => 'config',
        'admin_users_table_available' => false,
        'failure_reason' => '',
    ];
}

/**
 * 管理者ログインの成否のみを返す。
 */
function admin_login(string $username_or_email, string $password): bool
{
    $attempt = admin_attempt_login($username_or_email, $password);
    if (($attempt['success'] ?? false) !== true) {
        error_log('[admin_auth] admin_login failed');
    }

    return ($attempt['success'] ?? false) === true;
}

function admin_login_store_session(array $adminUser): void
{
    start_admin_session();
    session_regenerate_id(true);
    $_SESSION['admin_user'] = [
        'id' => (int)($adminUser['id'] ?? 0),
        'username' => (string)($adminUser['username'] ?? ''),
        'email' => isset($adminUser['email']) && is_string($adminUser['email']) ? $adminUser['email'] : null,
    ];
}

function admin_login_success(array $adminUser, string $redirectPath): never
{
    admin_login_store_session($adminUser);
    app_redirect($redirectPath);
}

/**
 * 管理者セッションを破棄する。
 */
function admin_logout(): void
{
    start_admin_session();
    $_SESSION = [];
    session_destroy();
}

/**
 * 未ログイン時にログインページへ遷移させる。
 */
function require_admin_auth(): void
{
    if (admin_is_logged_in()) {
        return;
    }

    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $target = normalize_admin_redirect_target($requestUri);
    $location = login_path() . '?return_to=' . rawurlencode($target);
    app_redirect($location);
}

function admin_require_login(): void
{
    require_admin_auth();
}


function require_admin_login(): void
{
    require_admin_auth();
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
