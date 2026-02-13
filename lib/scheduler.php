<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/site_settings.php';
require_once __DIR__ . '/dmm_api.php';
require_once __DIR__ . '/repository.php';

function scheduler_allowed_intervals(): array
{
    return [10, 30, 60, 120];
}

function scheduler_allowed_item_limits(): array
{
    return [10, 100, 500, 1000];
}

function scheduler_normalize_interval(int $minutes): int
{
    return in_array($minutes, scheduler_allowed_intervals(), true) ? $minutes : 60;
}

function scheduler_normalize_item_limit(int $limit): int
{
    return in_array($limit, scheduler_allowed_item_limits(), true) ? $limit : 100;
}

function scheduler_ensure_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS api_schedules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        interval_minutes INT NOT NULL,
        last_run DATETIME NULL,
        lock_until DATETIME NULL,
        fail_count INT NOT NULL DEFAULT 0,
        last_error TEXT NULL,
        last_success_at DATETIME NULL,
        updated_at DATETIME NULL,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $columns = [
        'interval_minutes' => 'ALTER TABLE api_schedules ADD COLUMN interval_minutes INT NOT NULL DEFAULT 60',
        'last_run' => 'ALTER TABLE api_schedules ADD COLUMN last_run DATETIME NULL',
        'lock_until' => 'ALTER TABLE api_schedules ADD COLUMN lock_until DATETIME NULL',
        'fail_count' => 'ALTER TABLE api_schedules ADD COLUMN fail_count INT NOT NULL DEFAULT 0',
        'last_error' => 'ALTER TABLE api_schedules ADD COLUMN last_error TEXT NULL',
        'last_success_at' => 'ALTER TABLE api_schedules ADD COLUMN last_success_at DATETIME NULL',
        'updated_at' => 'ALTER TABLE api_schedules ADD COLUMN updated_at DATETIME NULL',
        'created_at' => 'ALTER TABLE api_schedules ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ];

    $existing = [];
    $stmt = $pdo->query('SHOW COLUMNS FROM api_schedules');
    if ($stmt !== false) {
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existing[strtolower((string)($row['Field'] ?? ''))] = true;
        }
    }

    foreach ($columns as $name => $sql) {
        if (!isset($existing[$name])) {
            $pdo->exec($sql);
        }
    }
}

function scheduler_get_state(PDO $pdo): array
{
    scheduler_ensure_schema($pdo);

    $schedule = $pdo->query('SELECT * FROM api_schedules ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!is_array($schedule)) {
        $pdo->prepare('INSERT INTO api_schedules(interval_minutes,last_run,lock_until,fail_count,last_error,last_success_at,updated_at,created_at) VALUES (60,NULL,NULL,0,NULL,NULL,NOW(),NOW())')->execute();
        $schedule = $pdo->query('SELECT * FROM api_schedules ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    }

    if (!is_array($schedule)) {
        throw new RuntimeException('Failed to load scheduler state');
    }

    return $schedule;
}

function scheduler_settings(): array
{
    return [
        'api_key' => trim(site_setting_get('api_key', '')),
        'api_affiliate_id' => trim(site_setting_get('api_affiliate_id', '')),
        'api_endpoint' => trim(site_setting_get('api_endpoint', 'ItemList')),
        'api_item_limit' => scheduler_normalize_item_limit((int)site_setting_get('api_item_limit', '100')),
    ];
}

function scheduler_run_import(int $limit): void
{
    if (function_exists('run_import')) {
        run_import($limit);
        return;
    }

    $settings = scheduler_settings();
    $apiKey = $settings['api_key'];
    $affiliateId = $settings['api_affiliate_id'];
    $endpoint = $settings['api_endpoint'] !== '' ? $settings['api_endpoint'] : 'ItemList';

    if ($apiKey === '' || $affiliateId === '') {
        throw new RuntimeException('API key または affiliate id が未設定です');
    }

    $defaults = config_get('dmm_api', []);
    $site = (string)($defaults['site'] ?? 'FANZA');
    $service = (string)($defaults['service'] ?? 'digital');
    $floor = (string)($defaults['floor'] ?? 'videoa');

    $remaining = max(1, $limit);
    $offset = 1;
    while ($remaining > 0) {
        $hits = min(100, $remaining);
        $response = dmm_api_request($endpoint, [
            'api_id' => $apiKey,
            'affiliate_id' => $affiliateId,
            'site' => $site,
            'service' => $service,
            'floor' => $floor,
            'hits' => $hits,
            'offset' => $offset,
            'sort' => 'date',
        ]);

        if (!($response['ok'] ?? false)) {
            throw new RuntimeException('API request failed: ' . (string)($response['error'] ?? 'unknown'));
        }

        $items = $response['data']['result']['items'] ?? [];
        if (!is_array($items) || $items === []) {
            break;
        }

        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }

            $contentId = (string)($row['content_id'] ?? '');
            if ($contentId === '') {
                continue;
            }

            $price = $row['prices']['price'] ?? ($row['price'] ?? null);
            upsert_item([
                'content_id' => $contentId,
                'product_id' => (string)($row['product_id'] ?? ''),
                'title' => (string)($row['title'] ?? ''),
                'url' => (string)($row['URL'] ?? ''),
                'affiliate_url' => (string)($row['affiliateURL'] ?? ''),
                'image_list' => (string)($row['imageURL']['list'] ?? ''),
                'image_small' => (string)($row['imageURL']['small'] ?? ''),
                'image_large' => (string)($row['imageURL']['large'] ?? ''),
                'date_published' => $row['date'] ?? null,
                'service_code' => (string)($row['service_code'] ?? ''),
                'floor_code' => (string)($row['floor_code'] ?? ''),
                'category_name' => (string)($row['category_name'] ?? ''),
                'price_min' => is_numeric($price) ? (int)$price : null,
            ]);
        }

        $remaining -= count($items);
        if (count($items) < $hits) {
            break;
        }
        $offset += $hits;
    }
}

function maybe_run_scheduled_jobs(): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    try {
        $pdo = db();
        $state = scheduler_get_state($pdo);
        $now = new DateTimeImmutable('now');

        $lockUntilRaw = (string)($state['lock_until'] ?? '');
        if ($lockUntilRaw !== '') {
            $lockUntil = new DateTimeImmutable($lockUntilRaw);
            if ($now < $lockUntil) {
                return;
            }
        }

        $intervalMinutes = scheduler_normalize_interval((int)($state['interval_minutes'] ?? 60));
        $lastRunRaw = (string)($state['last_run'] ?? '');
        $shouldRun = $lastRunRaw === '';

        if (!$shouldRun) {
            $lastRun = new DateTimeImmutable($lastRunRaw);
            $nextRun = $lastRun->modify('+' . $intervalMinutes . ' minutes');
            $shouldRun = $now >= $nextRun;
        }

        if (!$shouldRun) {
            return;
        }

        $lockStmt = $pdo->prepare('UPDATE api_schedules SET lock_until=DATE_ADD(NOW(), INTERVAL 5 MINUTE), updated_at=NOW() WHERE id=:id AND (lock_until IS NULL OR lock_until < NOW())');
        $lockStmt->execute([':id' => (int)$state['id']]);
        if ($lockStmt->rowCount() !== 1) {
            return;
        }

        try {
            $limit = scheduler_settings()['api_item_limit'];
            scheduler_run_import($limit);
            $pdo->prepare('UPDATE api_schedules SET last_run=NOW(), last_success_at=NOW(), fail_count=0, last_error=NULL, lock_until=DATE_SUB(NOW(), INTERVAL 1 SECOND), updated_at=NOW() WHERE id=:id')
                ->execute([':id' => (int)$state['id']]);
        } catch (Throwable $e) {
            $pdo->prepare('UPDATE api_schedules SET fail_count=fail_count+1, last_error=:error, lock_until=DATE_SUB(NOW(), INTERVAL 1 SECOND), updated_at=NOW() WHERE id=:id')
                ->execute([
                    ':id' => (int)$state['id'],
                    ':error' => mb_substr($e->getMessage(), 0, 1000),
                ]);
            log_message('[scheduler] ' . $e->getMessage());
        }
    } catch (Throwable $e) {
        log_message('[scheduler_boot] ' . $e->getMessage());
    }
}
