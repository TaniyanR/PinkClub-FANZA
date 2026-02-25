<?php

declare(strict_types=1);

function app_config(): array
{
    $loaded = $GLOBALS['app_config'] ?? null;
    if (is_array($loaded)) {
        return $loaded;
    }

    $config = require __DIR__ . '/../config/config.php';
    if (!is_array($config)) {
        $config = [];
    }
    $GLOBALS['app_config'] = $config;

    return $config;
}

function app_origin(): string
{
    $parts = parse_url(BASE_URL);
    $scheme = $parts['scheme'] ?? 'http';
    $host = $parts['host'] ?? 'localhost';
    $port = isset($parts['port']) ? ':' . (string)$parts['port'] : '';

    return $scheme . '://' . $host . $port;
}

function app_base_path(): string
{
    $parts = parse_url(BASE_URL);
    $path = (string)($parts['path'] ?? '');
    $path = '/' . trim($path, '/');

    return $path === '/' ? '' : $path;
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

    if (str_starts_with($cleanPath, '/')) {
        return rtrim(app_origin(), '/') . $cleanPath;
    }

    return rtrim(BASE_URL, '/') . '/' . ltrim($cleanPath, '/');
}

function asset_url(string $path): string
{
    return app_url('assets/' . ltrim($path, '/'));
}

if (!function_exists('login_url')) {
    function login_url(): string
    {
        return app_url(LOGIN_PATH);
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return app_url('admin/' . ltrim($path, '/'));
    }
}

function public_url(string $path = ''): string
{
    return app_url('public/' . ltrim($path, '/'));
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
