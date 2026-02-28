<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

function scheduler_tick(): array
{
    $pdo = db();
    scheduler_ensure_schedule_table($pdo);
    scheduler_seed_default_schedules($pdo);

    $stmt = $pdo->query("SELECT * FROM api_schedules WHERE is_enabled = 1 ORDER BY id ASC");
    $schedules = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($schedules as $schedule) {
        if (!scheduler_is_due($schedule)) {
            continue;
        }
        $lockUntil = date('Y-m-d H:i:s', time() + 55);
        $locked = $pdo->prepare('UPDATE api_schedules SET lock_until = :lock_until WHERE id = :id AND (lock_until IS NULL OR lock_until < NOW())');
        $locked->execute(['lock_until' => $lockUntil, 'id' => $schedule['id']]);
        if ($locked->rowCount() === 0) {
            continue;
        }

        try {
            $result = scheduler_run_schedule($schedule);
            $pdo->prepare('UPDATE api_schedules SET last_run_at = NOW(), lock_until = NULL WHERE id = ?')->execute([$schedule['id']]);
            return array_merge(['status' => 'ran', 'schedule_type' => $schedule['schedule_type']], $result);
        } catch (Throwable $e) {
            $pdo->prepare('UPDATE api_schedules SET lock_until = NULL WHERE id = ?')->execute([$schedule['id']]);
            return ['status' => 'error', 'schedule_type' => $schedule['schedule_type'], 'message' => $e->getMessage()];
        }
    }

    return ['status' => 'idle', 'message' => '実行対象なし'];
}

function scheduler_run_schedule(array $schedule): array
{
    $service = dmm_sync_service();
    $settings = settings_get();
    $type = (string)$schedule['schedule_type'];
    $floorId = (string)($settings['master_floor_id'] ?? '43');

    return match ($type) {
        'items' => ['synced_count' => (int)$service->syncItemsBatch('digital', 'videoa', (int)($settings['item_sync_batch'] ?? 100), 1)['synced_count'], 'message' => '商品を同期しました'],
        'genres' => ['synced_count' => $service->syncGenres($floorId, 'あ', 100, 1), 'message' => 'ジャンルを同期しました'],
        'makers' => ['synced_count' => $service->syncMakers($floorId, 'あ', 100, 1), 'message' => 'メーカーを同期しました'],
        'series' => ['synced_count' => $service->syncSeries($floorId, 'あ', 100, 1), 'message' => 'シリーズを同期しました'],
        'authors' => ['synced_count' => $service->syncAuthors($floorId, 'あ', 100, 1), 'message' => '作者を同期しました'],
        default => ['synced_count' => 0, 'message' => '未対応スケジュールです'],
    };
}

function scheduler_is_due(array $schedule): bool
{
    $interval = max(1, (int)($schedule['interval_minutes'] ?? 60));
    $lastRun = isset($schedule['last_run_at']) ? strtotime((string)$schedule['last_run_at']) : false;
    if ($lastRun === false || $lastRun <= 0) return true;
    return $lastRun <= (time() - ($interval * 60));
}

function scheduler_ensure_schedule_table(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS api_schedules (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,schedule_type VARCHAR(32) NOT NULL UNIQUE,interval_minutes INT NOT NULL DEFAULT 60,is_enabled TINYINT(1) NOT NULL DEFAULT 1,last_run_at DATETIME NULL,lock_until DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

function scheduler_seed_default_schedules(PDO $pdo): void
{
    foreach (['items', 'genres', 'makers', 'series', 'authors'] as $type) {
        $pdo->prepare('INSERT INTO api_schedules(schedule_type, interval_minutes, is_enabled, created_at, updated_at) VALUES(?, 60, 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE updated_at = updated_at')->execute([$type]);
    }
}

function maybe_run_scheduled_jobs(): void {}
