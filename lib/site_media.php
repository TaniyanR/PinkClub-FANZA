<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const PCF_SITE_MEDIA_KEYS = ['logo', 'favicon', 'ogp'];

function site_media_key_allowed(string $key): bool
{
    return in_array($key, PCF_SITE_MEDIA_KEYS, true);
}

function site_media_get(string $key): ?array
{
    if (!site_media_key_allowed($key)) {
        return null;
    }

    try {
        $stmt = db()->prepare('SELECT media_key, file_name, mime_type, width, height, byte_size, sha256, media_data, created_at, updated_at FROM site_media WHERE media_key = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    } catch (Throwable) {
        return null;
    }
}

function site_media_exists(string $key): bool
{
    if (!site_media_key_allowed($key)) {
        return false;
    }

    try {
        $stmt = db()->prepare('SELECT 1 FROM site_media WHERE media_key = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function site_media_put(string $key, string $fileName, string $mimeType, int $width, int $height, string $bytes): void
{
    if (!site_media_key_allowed($key)) {
        throw new InvalidArgumentException('Unsupported site media key.');
    }
    if ($bytes === '') {
        throw new InvalidArgumentException('Site media bytes are empty.');
    }

    $size = strlen($bytes);
    $sha256 = hash('sha256', $bytes);
    $stmt = db()->prepare(
        'INSERT INTO site_media (media_key, file_name, mime_type, width, height, byte_size, sha256, media_data, created_at, updated_at) '
        . 'VALUES (:key, :file_name, :mime_type, :width, :height, :byte_size, :sha256, :media_data, NOW(), NOW()) '
        . 'ON DUPLICATE KEY UPDATE file_name = VALUES(file_name), mime_type = VALUES(mime_type), width = VALUES(width), height = VALUES(height), byte_size = VALUES(byte_size), sha256 = VALUES(sha256), media_data = VALUES(media_data), updated_at = NOW()'
    );
    $stmt->bindValue(':key', $key, PDO::PARAM_STR);
    $stmt->bindValue(':file_name', $fileName, PDO::PARAM_STR);
    $stmt->bindValue(':mime_type', $mimeType, PDO::PARAM_STR);
    $stmt->bindValue(':width', max(0, $width), PDO::PARAM_INT);
    $stmt->bindValue(':height', max(0, $height), PDO::PARAM_INT);
    $stmt->bindValue(':byte_size', $size, PDO::PARAM_INT);
    $stmt->bindValue(':sha256', $sha256, PDO::PARAM_STR);
    $stmt->bindValue(':media_data', $bytes, PDO::PARAM_LOB);
    $stmt->execute();
}

function site_media_delete(string $key): void
{
    if (!site_media_key_allowed($key)) {
        return;
    }
    try {
        db()->prepare('DELETE FROM site_media WHERE media_key = :key')->execute([':key' => $key]);
    } catch (Throwable) {
    }
}

function site_media_public_url(string $key): string
{
    if (!site_media_key_allowed($key) || !site_media_exists($key)) {
        return '';
    }

    $media = site_media_get($key);
    $revision = is_array($media) ? substr((string)($media['sha256'] ?? ''), 0, 12) : '';
    $query = ['key' => $key];
    if ($revision !== '') {
        $query['v'] = $revision;
    }
    return public_url('site-media.php') . '?' . http_build_query($query);
}

function site_media_url_or_legacy(string $key, string $legacyPath = ''): string
{
    $dbUrl = site_media_public_url($key);
    if ($dbUrl !== '') {
        return $dbUrl;
    }

    $legacyPath = ltrim(trim($legacyPath), '/');
    return $legacyPath !== '' ? public_versioned_url($legacyPath) : '';
}
