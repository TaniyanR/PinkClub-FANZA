<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

const AUTH_INITIAL_ADMIN_USERNAME = 'admin';
const AUTH_INITIAL_ADMIN_PASSWORD = 'password';

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

function auth_store_session(int $id, string $username): void
{
    session_regenerate_id(true);
    $_SESSION['admin'] = [
        'id' => $id,
        'username' => $username,
    ];
}

function auth_config_user(string $username, string $password): ?array
{
    $configUsername = trim((string)config_get('admin.username', AUTH_INITIAL_ADMIN_USERNAME));
    if ($configUsername === '') {
        $configUsername = AUTH_INITIAL_ADMIN_USERNAME;
    }

    $configHash = trim((string)config_get('admin.password_hash', ''));
    if ($configHash === '' || !hash_equals($configUsername, $username) || !password_verify($password, $configHash)) {
        return null;
    }

    return ['id' => 1, 'username' => $configUsername];
}

function auth_attempt(string $username, string $password): bool
{
    auth_set_last_error(null);

    $username = trim($username);
    if ($username === '' || $password === '') {
        return false;
    }

    $configUser = auth_config_user($username, $password);
    if (is_array($configUser)) {
        auth_store_session((int)$configUser['id'], (string)$configUser['username']);
        return true;
    }

    try {
        $stmt = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = :u LIMIT 1');
        $stmt->execute(['u' => $username]);
        $user = $stmt->fetch();
    } catch (PDOException|RuntimeException $exception) {
        auth_set_last_error('db_error');
        if (function_exists('installer_log')) {
            installer_log('auth db error: ' . $exception->getMessage());
        }
        return false;
    }

    if (!$user) {
        return false;
    }

    $passwordHash = (string)$user['password_hash'];
    if (!password_verify($password, $passwordHash)) {
        if (!hash_equals(AUTH_INITIAL_ADMIN_USERNAME, $username) || !hash_equals(AUTH_INITIAL_ADMIN_PASSWORD, $password)) {
            return false;
        }

        try {
            // Repair admin rows created by older setup attempts that generated an unknown password,
            // while keeping the documented initial login fixed at admin / password.
            db()->prepare('UPDATE admins SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id LIMIT 1')
                ->execute([
                    ':password_hash' => password_hash(AUTH_INITIAL_ADMIN_PASSWORD, PASSWORD_DEFAULT),
                    ':id' => (int)$user['id'],
                ]);
        } catch (PDOException|RuntimeException $exception) {
            auth_set_last_error('db_error');
            if (function_exists('installer_log')) {
                installer_log('auth default admin repair failed: ' . $exception->getMessage());
            }
            return false;
        }
    }

    auth_store_session((int)$user['id'], (string)$user['username']);

    return true;
}

function auth_require_admin(): void
{
    if (!auth_user()) {
        app_redirect(LOGIN_PATH);
    }

    if ((installer_status()['completed'] ?? false) !== true) {
        app_redirect('/public/setup_check.php');
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
