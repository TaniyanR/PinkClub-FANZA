<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_auth.php';

if (!function_exists('admin_simple_session_start')) {
    function admin_simple_session_start(): void
    {
        admin_session_start();
    }
}

if (!function_exists('admin_simple_verify_credentials')) {
    function admin_simple_verify_credentials(string $username, string $password): bool
    {
        return admin_login($username, $password);
    }
}

if (!function_exists('admin_simple_login')) {
    function admin_simple_login(string $username): void
    {
        // 互換関数: 現行実装では admin_simple_verify_credentials() がログインを完了する。
    }
}

if (!function_exists('admin_simple_is_logged_in')) {
    function admin_simple_is_logged_in(): bool
    {
        return admin_is_logged_in();
    }
}

if (!function_exists('admin_simple_require_login')) {
    function admin_simple_require_login(): void
    {
        require_admin_login();
    }
}
