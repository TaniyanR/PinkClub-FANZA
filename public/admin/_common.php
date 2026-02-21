<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../lib/admin_auth.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/csrf.php';

function admin_dev_auth_bypass_enabled(): bool
{
    $configBypass = config_get('app.dev_auth_bypass', false);
    if (!is_bool($configBypass)) {
        $configBypass = in_array(strtolower((string)$configBypass), ['1', 'true', 'on', 'yes'], true);
    }

    if ($configBypass !== true) {
        return false;
    }

    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    return str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
}

function admin_apply_dev_auth_bypass(): void
{
    if (!admin_dev_auth_bypass_enabled()) {
        return;
    }

    admin_session_start();
    if (!isset($_SESSION['admin_user']) || !is_array($_SESSION['admin_user'])) {
        $_SESSION['admin_user'] = [
            'id' => 1,
            'username' => 'admin',
            'email' => '',
        ];
    }
    $_SESSION['admin_dev_auth_bypass_active'] = true;

    error_log('[admin_auth] dev auth bypass enabled on ' . (string)($_SERVER['REQUEST_URI'] ?? 'unknown'));
}

function admin_dev_auth_bypass_active(): bool
{
    return admin_dev_auth_bypass_enabled()
        && !empty($_SESSION['admin_dev_auth_bypass_active'])
        && isset($_SESSION['admin_user'])
        && is_array($_SESSION['admin_user']);
}

admin_apply_dev_auth_bypass();

require_admin_login();
admin_require_password_change_if_needed();

function admin_post_csrf_valid(): bool
{
    $token = $_POST['_token'] ?? null;
    return csrf_verify(is_string($token) ? $token : null);
}

function admin_flash_set(string $key, string $message): void
{
    admin_session_start();
    $_SESSION['admin_flash'][$key] = $message;
}

function admin_flash_get(string $key): string
{
    admin_session_start();
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
