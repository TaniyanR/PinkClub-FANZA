<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../lib/admin_auth.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/csrf.php';

admin_require_login();
admin_require_password_change_if_needed();

function admin_post_csrf_valid(): bool
{
    $token = $_POST['_token'] ?? null;
    return csrf_verify(is_string($token) ? $token : null);
}

function admin_flash_set(string $key, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['admin_flash'][$key] = $message;
}

function admin_flash_get(string $key): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $msg = $_SESSION['admin_flash'][$key] ?? '';
    unset($_SESSION['admin_flash'][$key]);
    return is_string($msg) ? $msg : '';
}

function admin_table_exists(string $table): bool
{
    $stmt = db()->prepare('SHOW TABLES LIKE :table');
    $stmt->execute([':table' => $table]);
    return $stmt->fetchColumn() !== false;
}
