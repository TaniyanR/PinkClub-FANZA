<?php

declare(strict_types=1);

require_once __DIR__ . '/dmm_api_client.php';
require_once __DIR__ . '/dmm_sync_service.php';

function settings_get(): array
{
    $row = db()->query('SELECT * FROM settings ORDER BY id ASC LIMIT 1')->fetch();
    if (!is_array($row)) {
        return ['api_id' => '', 'affiliate_id' => '', 'item_sync_batch' => 100, 'master_floor_id' => null];
    }
    $row['item_sync_batch'] = (int)($row['item_sync_batch'] ?? 100);
    $row['master_floor_id'] = isset($row['master_floor_id']) && $row['master_floor_id'] !== null ? (int)$row['master_floor_id'] : null;
    return $row;
}

function settings_save(string $apiId, string $affiliateId, int $itemSyncBatch = 100, ?int $masterFloorId = null): void
{
    $allowed = [100, 200, 300, 500, 1000];
    if (!in_array($itemSyncBatch, $allowed, true)) {
        $itemSyncBatch = 100;
    }

    $stmt = db()->prepare('INSERT INTO settings(id,api_id,affiliate_id,item_sync_batch,master_floor_id,created_at,updated_at) VALUES(1,:api_id,:affiliate_id,:item_sync_batch,:master_floor_id,NOW(),NOW()) ON DUPLICATE KEY UPDATE api_id=VALUES(api_id),affiliate_id=VALUES(affiliate_id),item_sync_batch=VALUES(item_sync_batch),master_floor_id=VALUES(master_floor_id),updated_at=NOW()');
    $stmt->execute([
        'api_id' => $apiId,
        'affiliate_id' => $affiliateId,
        'item_sync_batch' => $itemSyncBatch,
        'master_floor_id' => $masterFloorId,
    ]);
}

function dmm_client_from_settings(): DmmApiClient
{
    $s = settings_get();
    $endpoint = app_config()['dmm']['endpoint'];
    return new DmmApiClient((string)$s['api_id'], (string)$s['affiliate_id'], $endpoint);
}

function dmm_sync_service(): DmmSyncService
{
    return new DmmSyncService(dmm_client_from_settings(), db());
}
