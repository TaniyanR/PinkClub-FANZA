<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function site_settings_columns(): array
{
    static $columns = null;
    if (is_array($columns)) {
        return $columns;
    }

    $columns = [
        'key' => 'setting_key',
        'value' => 'setting_value',
    ];

    try {
        $stmt = db()->query('SHOW COLUMNS FROM settings');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $names = array_map(static fn(array $row): string => (string)($row['Field'] ?? ''), $rows);

        foreach (['setting_key', 'key_name', 'setting_name', 'name'] as $candidate) {
            if (in_array($candidate, $names, true)) {
                $columns['key'] = $candidate;
                break;
            }
        }

        foreach (['setting_value', 'value_text', 'setting_text', 'value'] as $candidate) {
            if (in_array($candidate, $names, true)) {
                $columns['value'] = $candidate;
                break;
            }
        }
    } catch (Throwable $e) {
        $columns = [
            'key' => 'setting_key',
            'value' => 'setting_value',
        ];
    }

    return $columns;
}

function site_setting_get(string $key, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $columns = site_settings_columns();
        $sql = sprintf('SELECT `%s` FROM settings WHERE `%s` = :key LIMIT 1', $columns['value'], $columns['key']);
        $stmt = db()->prepare($sql);
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
    $columns = site_settings_columns();

    $sql = sprintf(
        'INSERT INTO settings(`%s`,`%s`,created_at,updated_at) VALUES(:key,:value,NOW(),NOW()) ON DUPLICATE KEY UPDATE `%s`=VALUES(`%s`),updated_at=NOW()',
        $columns['key'],
        $columns['value'],
        $columns['value'],
        $columns['value']
    );

    try {
        db()->prepare($sql)->execute([':key' => $key, ':value' => $value]);
    } catch (Throwable $e) {
        $fallbackSql = sprintf('UPDATE settings SET `%s`=:value, updated_at=NOW() WHERE `%s`=:key', $columns['value'], $columns['key']);
        $updated = db()->prepare($fallbackSql);
        $updated->execute([':key' => $key, ':value' => $value]);

        if ($updated->rowCount() === 0) {
            $insertSql = sprintf('INSERT INTO settings(`%s`,`%s`) VALUES(:key,:value)', $columns['key'], $columns['value']);
            db()->prepare($insertSql)->execute([':key' => $key, ':value' => $value]);
        }
    }
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
