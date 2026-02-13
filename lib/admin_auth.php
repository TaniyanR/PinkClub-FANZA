<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/url.php';
require_once __DIR__ . '/db.php';

const ADMIN_DEFAULT_USERNAME = 'admin';
// default = admin/password
const ADMIN_DEFAULT_PASSWORD_HASH = '$2y$12$SS2ptXwc56Bwj.VaTsErye.Dmyde0fi/XefAYSy7v0KQPf7w2dUqG';

function admin_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function admin_config(): array
{
    $admin = config_get('admin', []);
    $username = ADMIN_DEFAULT_USERNAME;
    $passwordHash = ADMIN_DEFAULT_PASSWORD_HASH;

    if (is_array($admin)) {
        $candidateUser = $admin['username'] ?? null;
        if (is_string($candidateUser) && $candidateUser !== '') {
            $username = $candidateUser;
        }

        $candidateHash = $admin['password_hash'] ?? null;
        if (is_string($candidateHash) && $candidateHash !== '') {
            $passwordHash = $candidateHash;
        }
    }

    return [
        'username' => $username,
        'password_hash' => $passwordHash,
    ];
}

function admin_current_user(): ?string
{
    admin_session_start();

    if (($_SESSION['admin_logged_in'] ?? false) !== true) {
        return null;
    }

    $user = $_SESSION['admin_user'] ?? null;
    if (!is_string($user) || $user === '') {
        return null;
    }

    return $user;
}

function admin_is_logged_in(): bool
{
    return admin_current_user() !== null;
}

function admin_require_login(): void
{
    if (admin_is_logged_in()) {
        return;
    }

    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $path = str_replace('\\', '/', $scriptName);
    $publicPos = strripos($path, '/public/');
    if ($publicPos !== false) {
        $path = substr($path, $publicPos + 7);
    }

    $returnTo = '';
    if ($path !== '' && $path[0] === '/' && strpos($path, '/admin/') === 0) {
        $returnTo = $path;
    }

    $location = login_url();
    if ($returnTo !== '') {
        $location .= '?return_to=' . rawurlencode($returnTo);
    }

    header('Location: ' . $location);
    exit;
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

    $count = (int)(db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() ?: 0);
    if ($count > 0) {
        return;
    }

    db()->prepare('INSERT INTO admin_users(username,password_hash,role,is_active,created_at,updated_at) VALUES (:u,:p,"admin",1,NOW(),NOW())')
        ->execute([':u' => ADMIN_DEFAULT_USERNAME, ':p' => ADMIN_DEFAULT_PASSWORD_HASH]);
}

function admin_login(string $username, string $password): bool
{
    admin_session_start();

    if (admin_users_table_available()) {
        admin_ensure_default_user();
        $stmt = db()->prepare('SELECT username,password_hash FROM admin_users WHERE username=:u AND is_active=1 LIMIT 1');
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row) && password_verify($password, (string)$row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = (string)$row['username'];
            $_SESSION['admin_default_password'] = false;
            return true;
        }
        return false;
    }

    $admin = admin_config();
    if (!hash_equals($admin['username'], $username)) {
        return false;
    }

    if (!password_verify($password, $admin['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = $admin['username'];
    $_SESSION['admin_default_password'] = hash_equals(ADMIN_DEFAULT_PASSWORD_HASH, $admin['password_hash']);

    return true;
}

function admin_is_default_password(): bool
{
    admin_session_start();

    $flag = $_SESSION['admin_default_password'] ?? null;
    if (is_bool($flag)) {
        return $flag;
    }

    $admin = admin_config();
    return hash_equals(ADMIN_DEFAULT_PASSWORD_HASH, $admin['password_hash']);
}

function admin_require_password_change_if_needed(): void
{
    if (!admin_is_logged_in()) {
        return;
    }

    if (!admin_is_default_password()) {
        return;
    }

    header('Location: ' . admin_url('change_password.php'));
    exit;
}

function admin_logout(): void
{
    admin_session_start();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool)$params['secure'],
            (bool)$params['httponly']
        );
    }

    session_regenerate_id(true);
    session_destroy();
}
