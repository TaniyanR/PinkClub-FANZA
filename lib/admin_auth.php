<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

const ADMIN_DEFAULT_USERNAME = 'admin';
const ADMIN_DEFAULT_PASSWORD_HASH = '$2y$12$58ws2D57sDIa5vHnPiEZ.e/x6.6T.aVOg3.WfTdoiKfX92Js0MLBu';

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

    $user = $_SESSION['admin_user'] ?? null;
    if (!is_string($user) || $user === '') {
        return null;
    }

    return $user;
}

function admin_require_login(): void
{
    if (admin_current_user() !== null) {
        return;
    }

    header('Location: /admin/login.php');
    exit;
}

function admin_login(string $username, string $password): bool
{
    admin_session_start();

    $admin = admin_config();
    if (!hash_equals($admin['username'], $username)) {
        return false;
    }

    if (!password_verify($password, $admin['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_user'] = $admin['username'];
    return true;
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
