<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function is_assoc_array(array $array): bool
{
    if ($array === []) {
        return false;
    }
    return array_keys($array) !== range(0, count($array) - 1);
}

function get_setting(string $key, ?string $default = null): ?string
{
    $stmt = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :k LIMIT 1');
    $stmt->execute([':k' => $key]);
    $row = $stmt->fetch();
    return $row['setting_value'] ?? $default;
}

function set_setting(string $key, string $value): void
{
    $sql = 'INSERT INTO app_settings (setting_key, setting_value, updated_at) VALUES (:k,:v,NOW())
            ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=VALUES(updated_at)';
    db()->prepare($sql)->execute([':k' => $key, ':v' => $value]);
}

function safe_datetime(?string $value): ?string
{
    if (!$value) return null;
    $ts = strtotime($value);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

function safe_date(?string $value): ?string
{
    if (!$value) return null;
    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : null;
}
