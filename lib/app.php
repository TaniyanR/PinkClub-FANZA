<?php

declare(strict_types=1);

require_once __DIR__ . '/dmm_api_client.php';
require_once __DIR__ . '/dmm_sync_service.php';

function settings_get(): array
{
    $row = db()->query('SELECT * FROM settings ORDER BY id ASC LIMIT 1')->fetch();
    return $row ?: ['api_id' => '', 'affiliate_id' => ''];
}

function settings_save(string $apiId, string $affiliateId): void
{
    $stmt = db()->prepare('UPDATE settings SET api_id=:api_id, affiliate_id=:affiliate_id, updated_at=NOW() WHERE id=1');
    $stmt->execute(['api_id' => $apiId, 'affiliate_id' => $affiliateId]);
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
