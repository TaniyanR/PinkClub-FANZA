<?php
declare(strict_types=1);

require_once __DIR__ . '/site_settings.php';

function normalize_base_url(string $value): string
{
    $normalized = rtrim(trim($value), '/');
    if ($normalized === '') {
        return '';
    }

    // Misconfigured values such as .../index.php, .../login0718.php, or .../admin break redirect destinations.
    $normalized = preg_replace('#/(index\.php|login\.php|login0718\.php|admin/login\.php|admin(?:/index\.php)?)/*$#i', '', $normalized);
    if (!is_string($normalized)) {
        return '';
    }

    return rtrim($normalized, '/');
}

function request_scheme(): string
{
    $https = $_SERVER['HTTPS'] ?? '';
    if ($https === 'on' || $https === '1') {
        return 'https';
    }

    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if (is_string($forwardedProto) && strtolower($forwardedProto) === 'https') {
        return 'https';
    }

    return 'http';
}

function base_url(): string
{
    $override = normalize_base_url(site_setting_get('site.base_url', ''));
    if ($override !== '') {
        return $override;
    }

    return normalize_base_url(detect_base_url());
}

function base_path(): string
{
    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    if ($scriptName === '') {
        return '';
    }

    $scriptName = str_replace('\\', '/', $scriptName);
    $publicPos = strripos($scriptName, '/public/');
    if ($publicPos !== false) {
        return rtrim(substr($scriptName, 0, $publicPos + 7), '/');
    }

    $dir = str_replace('\\', '/', dirname($scriptName));
    if ($dir === '/' || $dir === '.') {
        return '';
    }

    if (substr($dir, -6) === '/admin') {
        $dir = substr($dir, 0, -6);
    }

    return rtrim($dir, '/');
}

function admin_path(string $path = ''): string
{
    return base_path() . '/admin/' . ltrim($path, '/');
}

function login_path(): string
{
    return base_path() . '/login0718.php';
}

function admin_url(string $path = ''): string
{
    return url('/admin/' . ltrim($path, '/'));
}

function login_url(): string
{
    return url('/login0718.php');
}

function url(string $path = ''): string
{
    $target = trim(str_replace(["\r", "\n"], '', $path));
    if ($target === '') {
        return base_url() . '/';
    }

    if (preg_match('#^https?://#i', $target) === 1) {
        return $target;
    }

    $normalizedPath = '/' . ltrim($target, '/');
    $basePath = rtrim(base_path(), '/');
    if ($basePath !== '' && str_starts_with($normalizedPath, $basePath . '/')) {
        $normalizedPath = substr($normalizedPath, strlen($basePath));
        if ($normalizedPath === false || $normalizedPath === '') {
            $normalizedPath = '/';
        }
    }

    return rtrim(base_url(), '/') . $normalizedPath;
}
