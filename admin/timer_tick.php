<?php
declare(strict_types=1);

set_time_limit(50);

require_once __DIR__ . '/../public/_bootstrap.php';

function timer_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !auth_user()) {
    timer_json(['ran' => false, 'saved_items' => 0, 'message' => 'session_expired', 'at' => date('Y-m-d H:i:s')], 401);
}
auth_require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    timer_json(['ran' => false, 'saved_items' => 0, 'message' => 'POST only', 'at' => date('Y-m-d H:i:s')], 405);
}
csrf_validate_or_fail((string)post('_csrf', ''));

$now = date('Y-m-d H:i:s');
if (!settings_bool('item_sync_enabled', false)) {
    timer_json(['ran' => false, 'saved_items' => 0, 'message' => '自動取得はOFFです', 'at' => $now]);
}

$result = scheduler_tick();
$status = (string)($result['status'] ?? 'idle');
$message = (string)($result['message'] ?? '実行対象なし');
$ran = $status === 'ran';
if (isset($result['jobs']) && is_array($result['jobs'])) {
    foreach ($result['jobs'] as $job) {
        if (($job['status'] ?? '') === 'success') {
            $ran = true;
            break;
        }
    }
}
$payload = [
    'ran' => $ran,
    'job' => (string)($result['schedule_type'] ?? ''),
    'saved_items' => (int)($result['synced_count'] ?? 0),
    'message' => $message,
    'at' => $now,
];
if (isset($result['jobs']) && is_array($result['jobs'])) {
    $payload['jobs'] = $result['jobs'];
}
if ($status === 'error') {
    if (isset($result['jobs']) && is_array($result['jobs'])) {
        foreach ($result['jobs'] as $job) {
            if (($job['status'] ?? '') === 'error') {
                error_log('[timer_tick] job=' . (string)($job['schedule_type'] ?? '') . ' error=' . (string)($job['message'] ?? ''));
            }
        }
    } else {
        error_log('[timer_tick] job=' . (string)($result['schedule_type'] ?? '') . ' error=' . $message);
    }
    timer_json($payload, 500);
}
if ($status === 'idle' && $message === '') {
    $payload['message'] = '実行対象なし';
}
timer_json($payload);
