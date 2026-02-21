<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/url.php';
require_once __DIR__ . '/admin_auth.php';

const ADMIN_SIMPLE_DEFAULT_USERNAME = 'admin';
const ADMIN_SIMPLE_DEFAULT_PASSWORD = 'password';

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

if (!function_exists('admin_simple_verify_credentials')) {
    function admin_simple_verify_credentials(string $username, string $password): bool
    {
        $attempt = admin_attempt_login($username, $password);
        return ($attempt['success'] ?? false) === true;
    }
}

if (!function_exists('admin_simple_login')) {
    function admin_simple_login(string $username): void
    {
        // admin_simple_verify_credentials() 内で admin_attempt_login() がセッションを更新済み。
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
