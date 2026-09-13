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
$duration = (int)($_POST['duration'] ?? 0);
$active = (int)($_POST['active'] ?? 0);
$scroll = (int)($_POST['scroll'] ?? 0);

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
    if (!db_table_exists('analytics_page_engagement')) {
        http_response_code(204);
        exit;
    }

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

    if (random_int(1, 200) === 1) {
        $retentionDays = (int)(setting_get('analytics.cleanup.retention_days', '730') ?? '730');
        $retentionDays = max(365, min(3650, $retentionDays));
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . $retentionDays . ' days'));
        $cleanup = $pdo->prepare('DELETE FROM analytics_page_engagement WHERE viewed_at < :cutoff ORDER BY viewed_at ASC LIMIT 2000');
        $cleanup->execute([':cutoff' => $cutoff]);
    }
} catch (Throwable $e) {
    error_log('analytics engagement failed: ' . $e->getMessage());
}

http_response_code(204);