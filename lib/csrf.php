<?php
declare(strict_types=1);

function csrf_token(): string
{
    if (function_exists('admin_v2_session_start')) {
        admin_v2_session_start();
    } elseif (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $sent): bool
{
    if (function_exists('admin_v2_session_start')) {
        admin_v2_session_start();
    } elseif (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $token = $_SESSION['csrf_token'] ?? '';
    return is_string($sent) && is_string($token) && hash_equals($token, $sent);
}
