<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function site_setting_get(string $key, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = db()->prepare('SELECT setting_value FROM site_settings WHERE setting_key=:key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $value = $stmt->fetchColumn();
        $cache[$key] = is_string($value) ? $value : $default;
        return $cache[$key];
    } catch (Throwable $e) {
        return $default;
    }
}

function site_setting_set(string $key, string $value): void
{
    db()->prepare('INSERT INTO site_settings(setting_key,setting_value,created_at,updated_at) VALUES(:key,:value,NOW(),NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()')
        ->execute([':key' => $key, ':value' => $value]);
}

function setting_get(string $key, ?string $default = null): ?string
{
    $fallback = $default ?? '';
    $value = site_setting_get($key, $fallback);
    if ($value === '' && $default === null) {
        return null;
    }

    return $value;
}

function setting_set(string $key, string $value): void
{
    site_setting_set($key, $value);
}

function setting_delete(string $key): void
{
    site_setting_set($key, '');
}

function site_setting_set_many(array $pairs): void
{
    foreach ($pairs as $key => $value) {
        site_setting_set((string)$key, (string)$value);
    }
}

function detect_base_url(): string
{
    $https = (string)($_SERVER['HTTPS'] ?? '');
    $proto = ($https === 'on' || $https === '1') ? 'https' : 'http';
    $forwarded = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwarded === 'https') {
        $proto = 'https';
    }

    $host = (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $root = '';
    $publicPos = strripos($scriptName, '/public/');
    if ($publicPos !== false) {
        $root = substr($scriptName, 0, $publicPos + 7);
    } else {
        $dir = str_replace('\\', '/', dirname($scriptName));
        if ($dir === '/' || $dir === '.') {
            $dir = '';
        }
        if (substr($dir, -6) === '/admin') {
            $dir = substr($dir, 0, -6);
        }
        $root = rtrim($dir, '/');
    }

    return $proto . '://' . $host . $root;
}
