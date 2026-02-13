<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app_features.php';

function maybe_run_scheduled_jobs(): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    try {
        $pdo = db();
        $pdo->prepare('INSERT INTO api_schedules(schedule_type,interval_minutes,last_run_at,lock_until,fail_count,last_error,is_enabled,created_at,updated_at) VALUES("rss_fetch",60,NULL,NULL,0,NULL,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE updated_at=NOW()')->execute();
        $schedule = $pdo->query("SELECT * FROM api_schedules WHERE schedule_type='rss_fetch' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!is_array($schedule) || (int)($schedule['is_enabled'] ?? 0) !== 1) {
            return;
        }

        $interval = (int)($schedule['interval_minutes'] ?? 60);
        if (!in_array($interval, [10, 30, 60, 120], true)) {
            $interval = 60;
        }

        $lastRun = isset($schedule['last_run_at']) ? strtotime((string)$schedule['last_run_at']) : false;
        if ($lastRun !== false && $lastRun > time() - ($interval * 60)) {
            return;
        }

        $lockUntil = isset($schedule['lock_until']) ? strtotime((string)$schedule['lock_until']) : false;
        if ($lockUntil !== false && $lockUntil > time()) {
            return;
        }

        $lockStmt = $pdo->prepare('UPDATE api_schedules SET lock_until=DATE_ADD(NOW(), INTERVAL 5 MINUTE), updated_at=NOW() WHERE id=:id AND (lock_until IS NULL OR lock_until < NOW())');
        $lockStmt->execute([':id' => (int)$schedule['id']]);
        if ($lockStmt->rowCount() !== 1) {
            return;
        }

        try {
            $sources = $pdo->query('SELECT id FROM rss_sources WHERE is_enabled=1')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($sources as $sourceId) {
                rss_fetch_source((int)$sourceId, 5);
            }
            $pdo->prepare('UPDATE api_schedules SET last_run_at=NOW(), lock_until=DATE_SUB(NOW(), INTERVAL 1 SECOND), fail_count=0, last_error=NULL, updated_at=NOW() WHERE id=:id')
                ->execute([':id' => (int)$schedule['id']]);
        } catch (Throwable $e) {
            $pdo->prepare('UPDATE api_schedules SET lock_until=DATE_SUB(NOW(), INTERVAL 1 SECOND), fail_count=fail_count+1, last_error=:error, updated_at=NOW() WHERE id=:id')
                ->execute([':id' => (int)$schedule['id'], ':error' => mb_substr($e->getMessage(), 0, 1000)]);
            log_message('[scheduler] ' . $e->getMessage());
        }
    } catch (Throwable $e) {
        log_message('[scheduler_boot] ' . $e->getMessage());
    }
}
