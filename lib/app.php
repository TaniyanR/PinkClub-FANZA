<?php

declare(strict_types=1);

require_once __DIR__ . '/dmm_api_client.php';
require_once __DIR__ . '/dmm_sync_service.php';
require_once __DIR__ . '/site_settings.php';
require_once __DIR__ . '/config.php';

function settings_get(): array
{
    $defaults = app_config()['dmm'] ?? [];

    $envApiId = trim((string)(getenv('DMM_API_ID') ?: getenv('FANZA_API_ID') ?: ''));
    $envAffiliateId = trim((string)(getenv('DMM_AFFILIATE_ID') ?: getenv('FANZA_AFFILIATE_ID') ?: ''));

    $dbApiId = trim(site_setting_get('fanza_api_id', ''));
    $dbAffiliateId = trim(site_setting_get('fanza_affiliate_id', ''));

    return [
        'api_id' => $dbApiId !== '' ? $dbApiId : ($envApiId !== '' ? $envApiId : ''),
        'affiliate_id' => $dbAffiliateId !== '' ? $dbAffiliateId : ($envAffiliateId !== '' ? $envAffiliateId : ''),
        'site' => trim(site_setting_get('fanza_site', (string)($defaults['site'] ?? 'FANZA'))),
        'service' => trim(site_setting_get('fanza_service', 'digital')),
        'floor' => trim(site_setting_get('fanza_floor', 'videoa')),
        'master_floor_id' => trim(site_setting_get('master_floor_id', '43')),
        'item_sync_batch' => settings_int('item_sync_batch', 100),
        'item_sync_enabled' => settings_bool('item_sync_enabled', false),
        'item_sync_interval_minutes' => settings_int('item_sync_interval_minutes', 60),
        'last_item_sync_at' => site_setting_get('last_item_sync_at', ''),
        'item_sync_offset' => settings_int('item_sync_offset', 1),
        'item_sync_test_offset' => settings_int('item_sync_test_offset', 1),
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

    $payload = [
        'fanza_api_id' => trim($apiId),
        'fanza_affiliate_id' => trim($affiliateId),
        'item_sync_batch' => (string)$itemSyncBatch,
    ];
    if ($masterFloorId !== null) {
        $payload['master_floor_id'] = (string)max(1, $masterFloorId);
    }

    site_setting_set_many($payload);
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
