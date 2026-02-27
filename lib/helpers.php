<?php

declare(strict_types=1);

function app_config(): array
{
    $loaded = $GLOBALS['app_config'] ?? null;
    if (is_array($loaded)) {
        return $loaded;
    }

    $config = require __DIR__ . '/../config/config.php';
    $GLOBALS['app_config'] = is_array($config) ? $config : [];

    return $GLOBALS['app_config'];
}

function url_path(string $path): string
{
    return rtrim(BASE_URL, '/') . $path;
}

function app_url(string $path = ''): string
{
    $cleanPath = trim(str_replace(["\r", "\n"], '', $path));
    if ($cleanPath === '') {
        return rtrim(BASE_URL, '/');
    }
    if (str_starts_with($cleanPath, 'http://') || str_starts_with($cleanPath, 'https://')) {
        return $cleanPath;
    }

    return str_starts_with($cleanPath, '/') ? url_path($cleanPath) : url_path('/' . ltrim($cleanPath, '/'));
}

function asset_url(string $path): string
{
    return rtrim(BASE_URL, '/') . '/assets/' . ltrim($path, '/');
}

if (!function_exists('login_url')) {
    function login_url(): string
    {
        return url_path(LOGIN_PATH);
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return url_path('/admin/' . ltrim($path, '/'));
    }
}

function public_url(string $path = ''): string
{
    return url_path('/public/' . ltrim($path, '/'));
}

function app_redirect(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function post(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $default;
}

function get(string $key, mixed $default = null): mixed
{
    return $_GET[$key] ?? $default;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
