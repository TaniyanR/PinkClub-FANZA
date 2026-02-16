<?php
declare(strict_types=1);

require_once __DIR__ . '/site_settings.php';

function normalize_base_url(string $value): string
{
    $normalized = trim($value);
    if ($normalized === '') {
        return '';
    }

    $normalized = preg_replace('/[?#].*$/', '', $normalized);
    if (!is_string($normalized)) {
        return '';
    }

    // Misconfigured values such as .../index.php or .../admin/settings.php break redirect destinations.
    $normalized = preg_replace('#/(index\.php|login\.php|login0718\.php)$#i', '', $normalized);
    if (!is_string($normalized)) {
        return '';
    }

    $normalized = preg_replace('#/admin(?:/[^/]+\.php)?$#i', '', $normalized);
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

function admin_url(string $path = ''): string
{
    return base_url() . '/admin/' . ltrim($path, '/');
}

function login_url(): string
{
    return base_url() . '/login0718.php';
}
