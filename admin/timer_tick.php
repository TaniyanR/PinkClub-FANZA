<?php
declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';

auth_require_admin();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ran' => false, 'saved_items' => 0, 'message' => 'POST only'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

csrf_validate_or_fail((string)post('_csrf', ''));

$enabled = settings_bool('item_sync_enabled', false);
$interval = max(1, settings_int('item_sync_interval_minutes', 60));
$last = site_setting_get('last_item_sync_at', '');
$lastTs = $last !== '' ? strtotime($last) : false;
$now = date('Y-m-d H:i:s');

if (!$enabled) {
    echo json_encode(['ran' => false, 'saved_items' => 0, 'message' => '自動取得はOFFです', 'at' => $last !== '' ? $last : $now], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($lastTs !== false && $lastTs > (time() - ($interval * 60))) {
    echo json_encode(['ran' => false, 'saved_items' => 0, 'message' => '次回実行待ちです', 'at' => $last], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $batch = settings_int('item_sync_batch', 100);
    if (!in_array($batch, [100, 200, 300, 500, 1000], true)) {
        $batch = 100;
    }
    $offset = max(1, settings_int('item_sync_offset', 1));
    $result = dmm_sync_service()->syncItemsBatch('digital', 'videoa', $batch, $offset);
    $nextOffset = (int)($result['next_offset'] ?? ($offset + $batch));
    if ($nextOffset > 50000) {
        $nextOffset = 1;
    }

    site_setting_set_many([
        'last_item_sync_at' => $now,
        'item_sync_offset' => (string)$nextOffset,
    ]);

    echo json_encode([
        'ran' => true,
        'saved_items' => (int)($result['synced_count'] ?? 0),
        'message' => 'タイマー取得を実行しました',
        'at' => $now,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ran' => false, 'saved_items' => 0, 'message' => $e->getMessage(), 'at' => $now], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
