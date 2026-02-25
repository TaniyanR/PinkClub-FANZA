<?php

declare(strict_types=1);

function auth_user(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function auth_set_last_error(?string $error): void
{
    $GLOBALS['auth_last_error'] = $error;
}

function auth_last_error(): ?string
{
    $error = $GLOBALS['auth_last_error'] ?? null;
    return is_string($error) ? $error : null;
}

function auth_attempt(string $username, string $password): bool
{
    auth_set_last_error(null);

    try {
        $stmt = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = :u LIMIT 1');
        $stmt->execute(['u' => $username]);
        $user = $stmt->fetch();
    } catch (PDOException) {
        auth_set_last_error('db_error');
        return false;
    }

    if (!$user || !password_verify($password, (string)$user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin'] = [
        'id' => (int) $user['id'],
        'username' => $user['username'],
    ];

    return true;
}

function auth_require_admin(): void
{
    if (!auth_user()) {
        app_redirect(login_url());
    }
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
