<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../lib/admin_auth_v2.php';
require_once __DIR__ . '/../../lib/db.php';

admin_v2_require_login();
admin_require_password_change_if_needed();

function admin_dev_auth_bypass_enabled(): bool
{
    return false;
}

function admin_dev_auth_bypass_active(): bool
{
    return false;
}

function admin_post_csrf_valid(): bool
{
    $token = $_POST['_token'] ?? null;
    return admin_v2_csrf_verify(is_string($token) ? $token : null);
}

function admin_flash_set(string $key, string $message): void
{
    admin_v2_session_start();
    $_SESSION['admin_flash'][$key] = $message;
}

function admin_flash_get(string $key): string
{
    admin_v2_session_start();
    $msg = $_SESSION['admin_flash'][$key] ?? '';
    unset($_SESSION['admin_flash'][$key]);
    return is_string($msg) ? $msg : '';
}

function admin_table_exists(string $table): bool
{
    try {
        $stmt = db()->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1'
        );
        $stmt->execute([':table' => $table]);
        return $stmt->fetchColumn() !== false;
    } catch (Throwable $exception) {
        if (function_exists('admin_log_error')) {
            admin_log_error('admin_table_exists failed for ' . $table, $exception);
        }
        return false;
    }
}
