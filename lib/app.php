<?php

declare(strict_types=1);

require_once __DIR__ . '/dmm_api_client.php';
require_once __DIR__ . '/dmm_sync_service.php';
require_once __DIR__ . '/site_settings.php';

function settings_get(): array
{
    return [
        'api_id' => site_setting_get('fanza_api_id', ''),
        'affiliate_id' => site_setting_get('fanza_affiliate_id', ''),
        'item_sync_batch' => settings_int('item_sync_batch', 100),
        'item_sync_enabled' => settings_bool('item_sync_enabled', false),
        'item_sync_interval_minutes' => settings_int('item_sync_interval_minutes', 60),
        'last_item_sync_at' => site_setting_get('last_item_sync_at', ''),
        'item_sync_offset' => settings_int('item_sync_offset', 1),
    ];
}

function settings_int(string $key, int $default): int
{
    $value = site_setting_get($key, (string)$default);
    if (!preg_match('/^-?\d+$/', $value)) {
        return $default;
    }
    return (int)$value;
}

function settings_bool(string $key, bool $default): bool
{
    return settings_int($key, $default ? 1 : 0) === 1;
}

function settings_save(string $apiId, string $affiliateId, int $itemSyncBatch = 100, ?int $masterFloorId = null): void
{
    $allowed = [100, 200, 300, 500, 1000];
    if (!in_array($itemSyncBatch, $allowed, true)) {
        $itemSyncBatch = 100;
    }

    site_setting_set_many([
        'fanza_api_id' => trim($apiId),
        'fanza_affiliate_id' => trim($affiliateId),
        'item_sync_batch' => (string)$itemSyncBatch,
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
