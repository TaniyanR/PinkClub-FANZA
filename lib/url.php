<?php
declare(strict_types=1);

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
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');

    $publicRoot = '';
    $publicPos = strripos($scriptName, '/public/');
    if ($publicPos !== false) {
        $publicRoot = substr($scriptName, 0, $publicPos + 7);
    } elseif (substr($scriptName, -7) === '/public') {
        $publicRoot = $scriptName;
    } else {
        $dir = str_replace('\\', '/', dirname($scriptName));
        if ($dir === '/' || $dir === '.') {
            $dir = '';
        }

        if (substr($dir, -6) === '/admin') {
            $dir = substr($dir, 0, -6);
        }

        $publicRoot = rtrim($dir, '/');
    }

    return request_scheme() . '://' . $host . $publicRoot;
}

function admin_url(string $path = ''): string
{
    return base_url() . '/admin/' . ltrim($path, '/');
}

function login_url(): string
{
    return base_url() . '/login0718.php';
}
