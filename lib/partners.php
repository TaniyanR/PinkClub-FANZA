<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

function base_url(): string
{
    $base = (string)config_get('site.base_url', '');
    return rtrim($base, '/');
}

function normalize_partner_token(string $token): string
{
    $token = trim($token);
    if ($token === '' || strlen($token) > 128) {
        return '';
    }
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $token)) {
        return '';
    }
    return $token;
}

function fetch_partners(): array
{
    $stmt = db()->query('SELECT * FROM partners ORDER BY id DESC');
    return $stmt->fetchAll() ?: [];
}

function fetch_partner_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM partners WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => max(1, $id)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function fetch_partner_by_token(string $token): ?array
{
    $token = normalize_partner_token($token);
    if ($token === '') {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM partners WHERE token = :token LIMIT 1');
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function supports_images_final(array $partner): bool
{
    if (!empty($partner['supports_images_override'])) {
        return (int)$partner['supports_images_override'] === 1;
    }
    return (int)($partner['supports_images_detected'] ?? 1) === 1;
}

function update_partner_supports_images_detected(int $partnerId, bool $supports): void
{
    $stmt = db()->prepare(
        'UPDATE partners SET supports_images_detected = :supports, last_checked_at = :checked, updated_at = :updated WHERE id = :id'
    );
    $stmt->execute([
        ':supports' => $supports ? 1 : 0,
        ':checked' => now(),
        ':updated' => now(),
        ':id' => $partnerId,
    ]);
}

function update_partner_supports_images_override(int $partnerId, ?bool $supports): void
{
    $value = $supports === null ? null : ($supports ? 1 : 0);
    $stmt = db()->prepare(
        'UPDATE partners SET supports_images_override = :supports, updated_at = :updated WHERE id = :id'
    );
    $stmt->execute([
        ':supports' => $value,
        ':updated' => now(),
        ':id' => $partnerId,
    ]);
}

function add_partner(array $payload): int
{
    $stmt = db()->prepare(
        'INSERT INTO partners (name, site_url, rss_url, token, supports_images_override, supports_images_detected, created_at, updated_at)\n         VALUES (:name, :site_url, :rss_url, :token, :override, :detected, :created, :updated)'
    );
    $stmt->execute([
        ':name' => (string)($payload['name'] ?? ''),
        ':site_url' => (string)($payload['site_url'] ?? ''),
        ':rss_url' => (string)($payload['rss_url'] ?? ''),
        ':token' => (string)($payload['token'] ?? ''),
        ':override' => $payload['supports_images_override'] ?? null,
        ':detected' => $payload['supports_images_detected'] ?? 1,
        ':created' => now(),
        ':updated' => now(),
    ]);
    return (int)db()->lastInsertId();
}

function update_partner(int $id, array $payload): void
{
    $stmt = db()->prepare(
        'UPDATE partners SET name = :name, site_url = :site_url, rss_url = :rss_url, token = :token,
            supports_images_override = :override, updated_at = :updated WHERE id = :id'
    );
    $stmt->execute([
        ':name' => (string)($payload['name'] ?? ''),
        ':site_url' => (string)($payload['site_url'] ?? ''),
        ':rss_url' => (string)($payload['rss_url'] ?? ''),
        ':token' => (string)($payload['token'] ?? ''),
        ':override' => $payload['supports_images_override'] ?? null,
        ':updated' => now(),
        ':id' => max(1, $id),
    ]);
}

function count_in_access(int $partnerId, int $windowHours): int
{
    $windowHours = max(1, $windowHours);
    [$clause, $params] = time_window_clause('hour', $windowHours);
    $stmt = db()->prepare("SELECT COUNT(*) AS cnt FROM in_access_logs WHERE partner_id = :id AND created_at >= {$clause}");
    $stmt->bindValue(':id', $partnerId, PDO::PARAM_INT);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    return (int)($stmt->fetchColumn() ?: 0);
}

function cleanup_in_access_logs(int $retentionDays): void
{
    $retentionDays = max(1, $retentionDays);
    [$clause, $params] = time_window_clause('day', $retentionDays);
    $stmt = db()->prepare("DELETE FROM in_access_logs WHERE created_at < {$clause}");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
}

function log_in_access(int $partnerId, string $ipHash, string $uaHash, ?string $ref): void
{
    $stmt = db()->prepare(
        'INSERT INTO in_access_logs (partner_id, ip_hash, ua_hash, ref, created_at)\n         VALUES (:partner_id, :ip_hash, :ua_hash, :ref, :created_at)'
    );
    $stmt->execute([
        ':partner_id' => $partnerId,
        ':ip_hash' => $ipHash,
        ':ua_hash' => $uaHash,
        ':ref' => $ref,
        ':created_at' => now(),
    ]);
}

function is_recent_duplicate_access(int $partnerId, string $ipHash, string $uaHash, int $windowSeconds): bool
{
    $windowSeconds = max(10, $windowSeconds);
    [$clause, $params] = time_window_clause('second', $windowSeconds);
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM in_access_logs\n         WHERE partner_id = :partner_id AND ip_hash = :ip_hash AND ua_hash = :ua_hash\n         AND created_at >= {$clause}"
    );
    $stmt->execute(array_merge([
        ':partner_id' => $partnerId,
        ':ip_hash' => $ipHash,
        ':ua_hash' => $uaHash,
    ], $params));
    return (int)($stmt->fetchColumn() ?: 0) > 0;
}

function time_window_clause(string $unit, int $amount): array
{
    $driver = db()->getAttribute(PDO::ATTR_DRIVER_NAME);
    $amount = max(1, $amount);
    $unit = strtolower($unit);

    if ($driver === 'sqlite') {
        $modifier = sprintf('-%d %s', $amount, $unit . ($amount === 1 ? '' : 's'));
        return ["datetime('now', :modifier)", [':modifier' => $modifier]];
    }

    $valid = ['hour' => 'HOUR', 'day' => 'DAY', 'second' => 'SECOND'];
    $mysqlUnit = $valid[$unit] ?? 'HOUR';
    return [sprintf('(NOW() - INTERVAL :amount %s)', $mysqlUnit), [':amount' => $amount]];
}

function hash_for_log(string $value): string
{
    $secret = (string)config_get('security.secret', '');
    if ($secret === '') {
        $secret = 'default-secret';
    }
    return hash_hmac('sha256', $value, $secret);
}

function partner_cache_path(string $token): string
{
    $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $token);
    return __DIR__ . '/../cache/rss_' . $safe . '.xml';
}

function read_partner_cache(string $token, int $ttlSeconds): ?string
{
    $path = partner_cache_path($token);
    if (!is_file($path)) {
        return null;
    }
    if (time() - filemtime($path) > $ttlSeconds) {
        return null;
    }
    $content = file_get_contents($path);
    return $content === false ? null : $content;
}

function write_partner_cache(string $token, string $content): void
{
    $dir = __DIR__ . '/../cache';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $path = partner_cache_path($token);
    file_put_contents($path, $content);
}
