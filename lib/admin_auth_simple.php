<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/url.php';

const ADMIN_SIMPLE_DEFAULT_USERNAME = 'admin';
const ADMIN_SIMPLE_DEFAULT_PASSWORD = 'password';
const ADMIN_SIMPLE_DEFAULT_PASSWORD_HASH = '$2y$12$lrWdfz4sxTR6N3fvb/F5qeH/N1W0exdLVqgUbS7ZHEqo6DbZQqlSC';

if (!function_exists('admin_simple_session_start')) {
    function admin_simple_session_start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}

if (!function_exists('admin_simple_expected_username')) {
    function admin_simple_expected_username(): string
    {
        $configured = (string)config_get('admin.username', ADMIN_SIMPLE_DEFAULT_USERNAME);
        return $configured !== '' ? $configured : ADMIN_SIMPLE_DEFAULT_USERNAME;
    }
}

if (!function_exists('admin_simple_expected_hash')) {
    function admin_simple_expected_hash(): string
    {
        $configured = (string)config_get('admin.password_hash', '');
        return $configured !== '' ? $configured : ADMIN_SIMPLE_DEFAULT_PASSWORD_HASH;
    }
}

if (!function_exists('admin_simple_verify_credentials')) {
    function admin_simple_verify_credentials(string $username, string $password): bool
    {
        $expectedUsername = admin_simple_expected_username();
        $expectedHash = admin_simple_expected_hash();

        if (hash_equals($expectedUsername, $username) && password_verify($password, $expectedHash)) {
            return true;
        }

        // TODO: config のみで認証できる状態へ統一し、このフォールバックは削除する。
        return hash_equals(ADMIN_SIMPLE_DEFAULT_USERNAME, $username)
            && hash_equals(ADMIN_SIMPLE_DEFAULT_PASSWORD, $password);
    }
}

if (!function_exists('admin_simple_login')) {
    function admin_simple_login(string $username): void
    {
        admin_simple_session_start();
        session_regenerate_id(true);

        $_SESSION['admin_user'] = [
            'id' => 0,
            'username' => $username,
            'email' => null,
            'login_mode' => 'simple',
            'password_hash' => admin_simple_expected_hash(),
        ];
    }
}

if (!function_exists('admin_simple_is_logged_in')) {
    function admin_simple_is_logged_in(): bool
    {
        admin_simple_session_start();
        $adminUser = $_SESSION['admin_user'] ?? null;

        return is_array($adminUser)
            && is_string($adminUser['username'] ?? null)
            && ($adminUser['username'] ?? '') !== '';
    }
}

if (!function_exists('admin_simple_require_login')) {
    function admin_simple_require_login(): void
    {
        if (admin_simple_is_logged_in()) {
            return;
        }

        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $location = login_path();
        if ($requestUri !== '') {
            $location .= '?return_to=' . rawurlencode($requestUri);
        }

        header('Location: ' . $location);
        exit;
    }
}
