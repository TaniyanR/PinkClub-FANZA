<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

if (!analytics_request_is_valid_browser_beacon() || analytics_request_is_automated()) {
    http_response_code(204);
    exit;
}

$rawPath = (string)($_POST['path'] ?? '/');
$token = trim((string)($_POST['token'] ?? ''));
$duration = (int)($_POST['duration'] ?? 0);
$active = (int)($_POST['active'] ?? 0);
$scroll = (int)($_POST['scroll'] ?? 0);

if (preg_match('/^(\d{10})\.([a-f0-9]{64})$/', $token, $matches) !== 1) {
    http_response_code(204);
    exit;
}

$issuedAt = (int)$matches[1];
if ($issuedAt > time() || $issuedAt < time() - 43200) {
    http_response_code(204);
    exit;
}

if (!hash_equals(analytics_beacon_token($rawPath, $issuedAt), $token)) {
    http_response_code(204);
    exit;
}

$duration = max(0, min(43200, $duration));
$active = max(0, min($duration, $active));
$scroll = max(0, min(100, $scroll));
if ($duration < 2) {
    http_response_code(204);
    exit;
}

$path = analytics_normalize_beacon_path($rawPath);
$visitorHash = analytics_visitor_hash((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

try {
    installer_apply_migrations(dirname(__DIR__) . '/sql/migrations', 'analytics_engagement_endpoint');

    $pdo = db();
    $duplicate = $pdo->prepare(
        'SELECT id FROM analytics_page_engagement
         WHERE visitor_hash = :visitor
           AND path = :path
           AND viewed_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
         ORDER BY id DESC LIMIT 1'
    );
    $duplicate->execute([':visitor' => $visitorHash, ':path' => $path]);
    $existingId = (int)($duplicate->fetchColumn() ?: 0);

    if ($existingId > 0) {
        $stmt = $pdo->prepare(
            'UPDATE analytics_page_engagement
             SET duration_seconds = GREATEST(duration_seconds, :duration),
                 active_seconds = GREATEST(active_seconds, :active),
                 max_scroll_percent = GREATEST(max_scroll_percent, :scroll)
             WHERE id = :id'
        );
        $stmt->execute([
            ':duration' => $duration,
            ':active' => $active,
            ':scroll' => $scroll,
            ':id' => $existingId,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO analytics_page_engagement
             (viewed_at, visitor_hash, path, duration_seconds, active_seconds, max_scroll_percent)
             VALUES (NOW(), :visitor, :path, :duration, :active, :scroll)'
        );
        $stmt->execute([
            ':visitor' => $visitorHash,
            ':path' => $path,
            ':duration' => $duration,
            ':active' => $active,
            ':scroll' => $scroll,
        ]);
    }
} catch (Throwable $e) {
    error_log('analytics engagement failed: ' . $e->getMessage());
}

http_response_code(204);