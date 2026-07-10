<?php

declare(strict_types=1);

require_once __DIR__ . '/site_settings.php';

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

function auth_log_failure(string $reason, array $context = []): void
{
    $safeContext = [];
    foreach ($context as $key => $value) {
        if ($value === null || is_scalar($value)) {
            $safeContext[$key] = $value;
        }
    }

    error_log('[admin_login] ' . $reason . ' ' . json_encode($safeContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function auth_attempt(string $username, string $password): bool
{
    auth_set_last_error(null);
    $username = trim($username);

    if ($username === '') {
        auth_set_last_error('username_empty');
        auth_log_failure('username_empty');
        return false;
    }

    try {
        $stmt = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = :u LIMIT 1');
        $stmt->execute(['u' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($user) && strcasecmp($username, 'admin') === 0 && function_exists('setting_admin_email')) {
            $adminEmail = setting_admin_email('');
            if ($adminEmail !== '') {
                $stmt = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = :u LIMIT 2');
                $stmt->execute(['u' => $adminEmail]);
                $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (count($matches) === 1) {
                    $user = $matches[0];
                    auth_log_failure('fallback_admin_resolved_by_site_admin_email', ['admin_id' => (int)$user['id']]);
                }
            }
        }

        if (!is_array($user) && filter_var($username, FILTER_VALIDATE_EMAIL) && function_exists('setting_admin_email')) {
            $adminEmail = setting_admin_email('');
            if (strcasecmp($username, $adminEmail) === 0) {
                $count = (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
                if ($count === 1) {
                    $stmt = db()->query('SELECT id, username, password_hash FROM admins ORDER BY id ASC LIMIT 1');
                    $fallback = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
                    if (is_array($fallback)) {
                        $user = $fallback;
                        auth_log_failure('fallback_email_resolved_single_admin', ['admin_id' => (int)$user['id']]);
                    }
                } else {
                    auth_set_last_error('username_not_found');
                    auth_log_failure('email_matches_admin_email_but_admin_count_not_one', ['admin_count' => $count]);
                    return false;
                }
            }
        }
    } catch (PDOException|RuntimeException $exception) {
        auth_set_last_error('db_error');
        if (function_exists('installer_log')) {
            installer_log('auth db error: ' . $exception->getMessage());
        }
        auth_log_failure('db_error', ['message' => $exception->getMessage()]);
        return false;
    }

    if (!is_array($user)) {
        auth_set_last_error('username_not_found');
        auth_log_failure('username_not_found', ['identifier' => $username]);
        return false;
    }

    if (!password_verify($password, (string)$user['password_hash'])) {
        auth_set_last_error('password_mismatch');
        auth_log_failure('password_mismatch', ['admin_id' => (int)$user['id'], 'identifier' => $username]);
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
