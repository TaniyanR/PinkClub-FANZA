<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

function scheduler_tick(): array
{
    $pdo = db();
    $service = dmm_sync_service();
    $settings = settings_get();
    $intervalSeconds = 3600;

    $jobs = [
        ['key' => 'items', 'type' => 'items'],
        ['key' => 'floors', 'type' => 'floors'],
        ['key' => 'actresses', 'type' => 'actresses'],
    ];

    $floorId = isset($settings['master_floor_id']) && $settings['master_floor_id'] !== null ? (int)$settings['master_floor_id'] : null;
    if ($floorId !== null && $floorId > 0) {
        $jobs[] = ['key' => 'genres:floor=' . $floorId, 'type' => 'genres', 'floor_id' => (string)$floorId];
        $jobs[] = ['key' => 'makers:floor=' . $floorId, 'type' => 'makers', 'floor_id' => (string)$floorId];
        $jobs[] = ['key' => 'series:floor=' . $floorId, 'type' => 'series', 'floor_id' => (string)$floorId];
        $jobs[] = ['key' => 'authors:floor=' . $floorId, 'type' => 'authors', 'floor_id' => (string)$floorId];
    }

    $selected = null;
    foreach ($jobs as $job) {
        $state = scheduler_get_job_state($pdo, $job['key']);
        if ($state === null || $state['last_run_at'] === null || strtotime((string)$state['last_run_at']) <= (time() - $intervalSeconds)) {
            $selected = ['job' => $job, 'state' => $state];
            break;
        }
    }

    if ($selected === null) {
        return ['status' => 'idle', 'message' => 'no due jobs'];
    }

    $job = $selected['job'];
    $state = $selected['state'] ?? ['next_offset' => 1, 'next_initial' => null];
    $jobKey = (string)$job['key'];
    $nextOffset = max(1, (int)($state['next_offset'] ?? 1));

    try {
        $synced = 0;
        $message = 'ok';
        $newOffset = $nextOffset;
        if ($job['type'] === 'items') {
            $batch = (int)($settings['item_sync_batch'] ?? 100);
            if (!in_array($batch, [100, 200, 300, 500, 1000], true)) {
                $batch = 100;
            }
            $result = $service->syncItemsBatch('digital', 'videoa', $batch, $nextOffset);
            $synced = (int)$result['synced_count'];
            $newOffset = (int)$result['next_offset'];
            $message = "items synced: {$synced}";
        } elseif ($job['type'] === 'floors') {
            $synced = $service->syncFloors();
            $newOffset = 1;
            $message = "floors synced: {$synced}";
        } else {
            $map = ['actresses' => 'actress', 'genres' => 'genre', 'makers' => 'maker', 'series' => 'series', 'authors' => 'author'];
            $kind = $map[$job['type']] ?? 'actress';
            $synced = $service->syncMaster($kind, $job['floor_id'] ?? null, $nextOffset, 100);
            $newOffset = $synced < 100 ? 1 : ($nextOffset + 100);
            $message = "{$job['type']} synced: {$synced}";
        }

        scheduler_update_job_state($pdo, $jobKey, $newOffset, null, true, $message);
        return ['status' => 'ran', 'job_key' => $jobKey, 'synced_count' => $synced, 'next_offset' => $newOffset, 'message' => $message];
    } catch (Throwable $e) {
        scheduler_update_job_state($pdo, $jobKey, $nextOffset, null, false, $e->getMessage());
        return ['status' => 'error', 'job_key' => $jobKey, 'synced_count' => 0, 'message' => $e->getMessage()];
    }
}

function scheduler_get_job_state(PDO $pdo, string $jobKey): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM sync_job_state WHERE job_key = ? LIMIT 1');
    $stmt->execute([$jobKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function scheduler_update_job_state(PDO $pdo, string $jobKey, int $offset, ?string $initial, bool $success, string $message): void
{
    $stmt = $pdo->prepare('INSERT INTO sync_job_state(job_key,next_offset,next_initial,last_run_at,last_success,last_message,updated_at) VALUES(:job_key,:next_offset,:next_initial,NOW(),:last_success,:last_message,NOW()) ON DUPLICATE KEY UPDATE next_offset=VALUES(next_offset),next_initial=VALUES(next_initial),last_run_at=NOW(),last_success=VALUES(last_success),last_message=VALUES(last_message),updated_at=NOW()');
    $stmt->execute([
        'job_key' => $jobKey,
        'next_offset' => max(1, $offset),
        'next_initial' => $initial,
        'last_success' => $success ? 1 : 0,
        'last_message' => mb_substr($message, 0, 1000),
    ]);
}

function maybe_run_scheduled_jobs(): void
{
    // cron 不使用要件に合わせ、管理画面のタイマー呼び出しで scheduler_tick() を使用する。
}
