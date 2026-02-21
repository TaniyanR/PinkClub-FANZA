<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_auth_v2.php';

const ADMIN_DEFAULT_USERNAME = ADMIN_V2_DEFAULT_USERNAME;
const ADMIN_DEFAULT_PASSWORD = ADMIN_V2_DEFAULT_PASSWORD;

function admin_auth_log_error(string $message, ?Throwable $exception = null): void
{
    admin_v2_log($message, $exception);
}

function admin_session_cookie_secure(): bool
{
    return admin_v2_cookie_secure();
}

function admin_session_start(): void
{
    admin_v2_session_start();
}

function start_admin_session(): void
{
    admin_v2_session_start();
}

function admin_users_table_available(): bool
{
    return admin_v2_users_table_available();
}

function admin_ensure_default_user(): void
{
    admin_v2_ensure_default_admin();
}

function admin_login(string $identifier, string $password): bool
{
    return admin_v2_login($identifier, $password);
}

function admin_current_user(): ?array
{
    return admin_v2_current_user();
}

function admin_is_logged_in(): bool
{
    return admin_v2_is_logged_in();
}

function admin_logout(): void
{
    admin_v2_logout();
}

function require_admin_login(): void
{
    admin_v2_require_login();
}

function require_admin_auth(): void
{
    admin_v2_require_login();
}

function admin_require_login(): void
{
    admin_v2_require_login();
}

function admin_is_default_password(array $admin): bool
{
    $passwordHash = (string)($admin['password_hash'] ?? '');
    return $passwordHash !== '' && password_verify(ADMIN_DEFAULT_PASSWORD, $passwordHash);
}

function admin_require_password_change_if_needed(): void
{
}
