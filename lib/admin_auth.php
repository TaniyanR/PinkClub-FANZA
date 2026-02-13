<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/url.php';
require_once __DIR__ . '/db.php';

const ADMIN_DEFAULT_USERNAME = 'admin';
const ADMIN_DEFAULT_PASSWORD_HASH = '$2y$12$SS2ptXwc56Bwj.VaTsErye.Dmyde0fi/XefAYSy7v0KQPf7w2dUqG';

function admin_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function admin_current_user(): ?string
{
    admin_session_start();
    return (($_SESSION['admin_logged_in'] ?? false) === true && is_string($_SESSION['admin_user'] ?? null)) ? (string)$_SESSION['admin_user'] : null;
}

function admin_current_user_id(): ?int
{
    admin_session_start();
    $id = $_SESSION['admin_user_id'] ?? null;
    return is_int($id) ? $id : null;
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
    $location = login_url();
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

    db()->prepare('INSERT INTO admin_users(username,password_hash,display_name,email,login_mode,role,is_active,created_at,updated_at) VALUES (:u,:p,:d,NULL,"username","admin",1,NOW(),NOW())')
        ->execute([':u' => ADMIN_DEFAULT_USERNAME, ':p' => ADMIN_DEFAULT_PASSWORD_HASH, ':d' => ADMIN_DEFAULT_USERNAME]);
}

function admin_login(string $identifier, string $password): bool
{
    admin_session_start();
    if (!admin_users_table_available()) {
        return false;
    }

    admin_ensure_default_user();
    $identifier = trim($identifier);
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE is_active=1 AND ((login_mode="username" AND username=:identifier) OR (login_mode="email_only" AND email=:identifier)) LIMIT 1');
    $stmt->execute([':identifier' => $identifier]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row) || !password_verify($password, (string)$row['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = (string)$row['username'];
    $_SESSION['admin_user_id'] = (int)$row['id'];
    $_SESSION['admin_default_password'] = hash_equals(ADMIN_DEFAULT_PASSWORD_HASH, (string)$row['password_hash']);
    return true;
}

function admin_is_default_password(): bool
{
    admin_session_start();
    return (bool)($_SESSION['admin_default_password'] ?? false);
}

function admin_logout(): void
{
    admin_session_start();
    $_SESSION = [];
    session_destroy();
}
