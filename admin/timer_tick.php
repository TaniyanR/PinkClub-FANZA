<?php
declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';

function timer_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function timer_job_due(array $state, int $intervalMinutes): bool
{
    $lastRunAt = trim((string)($state['last_run_at'] ?? ''));
    if ($lastRunAt === '') {
        return true;
    }
    $ts = strtotime($lastRunAt);
    if ($ts === false) {
        return true;
    }
    return $ts <= (time() - ($intervalMinutes * 60));
}

function timer_lock_job(PDO $pdo, string $jobKey, int $seconds = 55): bool
{
    $lockUntil = date('Y-m-d H:i:s', time() + max(5, $seconds));
    $stmt = $pdo->prepare(
        'UPDATE sync_job_state
         SET lock_until = :lock_until
         WHERE job_key = :job_key AND (lock_until IS NULL OR lock_until < NOW())'
    );
    $stmt->execute([':lock_until' => $lockUntil, ':job_key' => $jobKey]);
    return $stmt->rowCount() > 0;
}

function timer_unlock_job(PDO $pdo, string $jobKey, bool $success, string $message, int $nextOffset, ?string $lastRunAt = null): void
{
    $stmt = $pdo->prepare(
        'UPDATE sync_job_state
         SET lock_until = NULL,
             last_success = :ok,
             last_message = :msg,
             next_offset = :next_offset,
             last_run_at = :last_run_at,
             updated_at = NOW()
         WHERE job_key = :job_key'
    );
    $stmt->execute([
        ':ok' => $success ? 1 : 0,
        ':msg' => mb_substr($message, 0, 1000),
        ':next_offset' => max(1, $nextOffset),
        ':last_run_at' => $lastRunAt ?? date('Y-m-d H:i:s'),
        ':job_key' => $jobKey,
    ]);
}

function timer_seed_jobs(PDO $pdo): void
{
    foreach (['items', 'genres', 'makers', 'series', 'authors'] as $jobKey) {
        $pdo->prepare('INSERT INTO sync_job_state (job_key, next_offset, updated_at) VALUES (:job_key, 1, NOW()) ON DUPLICATE KEY UPDATE updated_at = updated_at')
            ->execute([':job_key' => $jobKey]);
    }
}

auth_require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    timer_json(['ran' => false, 'saved_items' => 0, 'message' => 'POST only'], 405);
}
csrf_validate_or_fail((string)post('_csrf', ''));

$now = date('Y-m-d H:i:s');
$settings = settings_get();
if ((string)$settings['api_id'] === '' || (string)$settings['affiliate_id'] === '') {
    timer_json(['ran' => false, 'saved_items' => 0, 'message' => 'API ID / アフィリエイトID を設定してください。', 'at' => $now]);
}

$pdo = db();
timer_seed_jobs($pdo);
$interval = max(1, settings_int('item_sync_interval_minutes', 60));
$masterFloorId = (string)($settings['master_floor_id'] ?? '43');
$service = (string)($settings['service'] ?? 'digital');
$floor = (string)($settings['floor'] ?? 'videoa');
$itemBatch = (int)($settings['item_sync_batch'] ?? 100);
if (!in_array($itemBatch, [100, 200, 300, 500, 1000], true)) {
    $itemBatch = 100;
}

$jobs = [
    'items' => static function (DmmSyncService $sync, int $offset) use ($service, $floor, $itemBatch): array {
        $result = $sync->syncItemsBatch($service, $floor, $itemBatch, $offset);
        return ['count' => (int)($result['synced_count'] ?? 0), 'next_offset' => (int)($result['next_offset'] ?? ($offset + 100)), 'message' => 'ItemListを同期しました'];
    },
    'genres' => static fn(DmmSyncService $sync, int $offset): array => ['count' => $sync->syncGenres($masterFloorId, null, 100, $offset), 'next_offset' => $offset + 100, 'message' => 'GenreSearchを同期しました'],
    'makers' => static fn(DmmSyncService $sync, int $offset): array => ['count' => $sync->syncMakers($masterFloorId, null, 100, $offset), 'next_offset' => $offset + 100, 'message' => 'MakerSearchを同期しました'],
    'series' => static fn(DmmSyncService $sync, int $offset): array => ['count' => $sync->syncSeries($masterFloorId, null, 100, $offset), 'next_offset' => $offset + 100, 'message' => 'SeriesSearchを同期しました'],
    'authors' => static fn(DmmSyncService $sync, int $offset): array => ['count' => $sync->syncAuthors($masterFloorId, null, 100, $offset), 'next_offset' => $offset + 100, 'message' => 'AuthorSearchを同期しました'],
];

$stateStmt = $pdo->query("SELECT job_key, next_offset, last_run_at, lock_until FROM sync_job_state ORDER BY FIELD(job_key, 'items','genres','makers','series','authors')");
$states = $stateStmt ? $stateStmt->fetchAll(PDO::FETCH_ASSOC) : [];
$stateMap = [];
foreach ($states as $state) {
    $stateMap[(string)$state['job_key']] = $state;
}

if (!settings_bool('item_sync_enabled', false)) {
    timer_json(['ran' => false, 'saved_items' => 0, 'message' => '自動取得はOFFです', 'at' => $now]);
}

$syncService = dmm_sync_service();
foreach (array_keys($jobs) as $jobKey) {
    $state = $stateMap[$jobKey] ?? ['next_offset' => 1, 'last_run_at' => null];
    if (!timer_job_due($state, $interval)) {
        continue;
    }

    if (!timer_lock_job($pdo, $jobKey)) {
        continue;
    }

    $offset = max(1, (int)($state['next_offset'] ?? 1));
    try {
        $result = $jobs[$jobKey]($syncService, $offset);
        $nextOffset = max(1, (int)($result['next_offset'] ?? ($offset + 100)));
        if ($nextOffset > 50000) {
            $nextOffset = 1;
        }
        timer_unlock_job($pdo, $jobKey, true, (string)($result['message'] ?? '同期成功'), $nextOffset, $now);
        if ($jobKey === 'items') {
            site_setting_set_many(['last_item_sync_at' => $now, 'item_sync_offset' => (string)$nextOffset]);
        }

        timer_json([
            'ran' => true,
            'job' => $jobKey,
            'saved_items' => (int)($result['count'] ?? 0),
            'message' => (string)($result['message'] ?? '同期しました'),
            'at' => $now,
        ]);
    } catch (Throwable $e) {
        timer_unlock_job($pdo, $jobKey, false, $e->getMessage(), $offset, $now);
        timer_json(['ran' => false, 'job' => $jobKey, 'saved_items' => 0, 'message' => '同期失敗: ' . $e->getMessage(), 'at' => $now], 500);
    }
}

timer_json(['ran' => false, 'saved_items' => 0, 'message' => '次回実行待ちです', 'at' => $now]);
