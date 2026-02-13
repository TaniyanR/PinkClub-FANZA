<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/url.php';
require_once __DIR__ . '/db.php';

const ADMIN_DEFAULT_USERNAME = 'admin';
const ADMIN_DEFAULT_PASSWORD = 'password';

function admin_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function admin_users_table_available(): bool
{
    try {
        $stmt = db()->query("SHOW TABLES LIKE 'admin_users'");
        return $stmt !== false && $stmt->fetchColumn() !== false;
    } catch (Throwable $e) {
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
    admin_session_start();

    $current = $_SESSION['admin_user'] ?? null;
    if (!is_array($current)) {
        return null;
    }

    $id = $current['id'] ?? null;
    $username = $current['username'] ?? null;

    if (!is_int($id) || !is_string($username) || $username === '') {
        return null;
    }

    return [
        'id' => $id,
        'username' => $username,
        'email' => is_string($current['email'] ?? null) ? (string)$current['email'] : null,
        'login_mode' => is_string($current['login_mode'] ?? null) ? (string)$current['login_mode'] : null,
        'password_hash' => is_string($current['password_hash'] ?? null) ? (string)$current['password_hash'] : '',
    ];
}

function admin_is_logged_in(): bool
{
    return admin_current_user() !== null;
}

function admin_find_user_by_identifier(string $usernameOrEmail): ?array
{
    $sql = <<<'SQL'
SELECT id, username, email, login_mode, password_hash
FROM admin_users
WHERE is_active = 1
  AND (
    username = :identifier
    OR (email IS NOT NULL AND email = :identifier)
  )
LIMIT 1
SQL;

    $stmt = db()->prepare($sql);
    $stmt->execute([':identifier' => $usernameOrEmail]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function admin_login(string $username_or_email, string $password): bool
{
    admin_session_start();

    if (!admin_users_table_available()) {
        return false;
    }

    admin_ensure_default_user();

    $identifier = trim($username_or_email);
    if ($identifier === '') {
        return false;
    }

    $admin = admin_find_user_by_identifier($identifier);
    if (!is_array($admin)) {
        return false;
    }

    $passwordHash = (string)($admin['password_hash'] ?? '');
    if ($passwordHash === '' || !password_verify($password, $passwordHash)) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_user'] = [
        'id' => (int)$admin['id'],
        'username' => (string)$admin['username'],
        'email' => isset($admin['email']) && is_string($admin['email']) ? $admin['email'] : null,
        'login_mode' => isset($admin['login_mode']) && is_string($admin['login_mode']) ? $admin['login_mode'] : null,
        'password_hash' => $passwordHash,
    ];

    return true;
}

function admin_logout(): void
{
    admin_session_start();
    $_SESSION = [];
    session_destroy();
}

function admin_require_login(): void
{
    if (admin_is_logged_in()) {
        return;
    }

    header('Location: ' . login_url());
    exit;
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
