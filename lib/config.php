<?php
declare(strict_types=1);

function config_base(): array
{
    static $base = null;
    if (is_array($base)) {
        return $base;
    }

    $path = __DIR__ . '/../config.php';
    $base = require $path;
    if (!is_array($base)) {
        $base = [];
    }

    return $base;
}

function config_local(): array
{
    static $local = null;
    if (is_array($local)) {
        return $local;
    }

    $path = __DIR__ . '/../config.local.php';
    if (!is_file($path)) {
        $local = [];
        return $local;
    }

    $local = require $path;
    if (!is_array($local)) {
        $local = [];
    }

    return $local;
}

function config(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }

    $base = config_base();
    $local = config_local();
    $config = array_replace_recursive($base, $local);

    return $config;
}

function config_get(string $path, mixed $default = null): mixed
{
    $segments = explode('.', $path);
    $value = config();

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}
