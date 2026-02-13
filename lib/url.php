<?php
declare(strict_types=1);

require_once __DIR__ . '/site_settings.php';

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
    $override = trim(site_setting_get('site.base_url', ''));
    if ($override !== '') {
        return rtrim($override, '/');
    }

    return rtrim(detect_base_url(), '/');
}

function admin_url(string $path = ''): string
{
    return base_url() . '/admin/' . ltrim($path, '/');
}

function login_url(): string
{
    return base_url() . '/login0718.php';
}
