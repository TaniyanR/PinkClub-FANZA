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
        $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key=:key LIMIT 1');
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
    db()->prepare('INSERT INTO settings(setting_key,setting_value,created_at,updated_at) VALUES(:key,:value,NOW(),NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()')
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

function setting(string $key, ?string $default = null): ?string
{
    return setting_get($key, $default);
}

function setting_set_many(array $pairs): void
{
    site_setting_set_many($pairs);
}

function setting_site_title(string $default = ''): string
{
    return trim((string)(setting('site.title', $default) ?? $default));
}

function setting_site_tagline(string $default = ''): string
{
    return trim((string)(setting('site.tagline', $default) ?? $default));
}

function setting_admin_email(string $default = ''): string
{
    return trim((string)(setting('site.admin_email', $default) ?? $default));
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

function site_title_setting(string $default = ''): string
{
    $siteTitle = trim((string)(setting('site.title', '') ?? ''));
    if ($siteTitle !== '') {
        return $siteTitle;
    }

    $legacyKeys = ['site.name', 'site_title', 'site_name'];
    foreach ($legacyKeys as $legacyKey) {
        $legacyValue = trim((string)(setting($legacyKey, '') ?? ''));
        if ($legacyValue !== '') {
            return $legacyValue;
        }
    }

    return $default;
}

function site_title_setting_set(string $value): void
{
    $normalized = trim($value);
    site_setting_set_many([
        'site.title' => $normalized,
        'site.name' => $normalized,
        'site_title' => $normalized,
        'site_name' => $normalized,
    ]);
}
