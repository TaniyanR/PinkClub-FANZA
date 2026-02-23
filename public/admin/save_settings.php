<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/local_config_writer.php';
require_once __DIR__ . '/../../lib/site_settings.php';
require_once __DIR__ . '/../../lib/fanza_api_config.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    app_redirect(admin_url('settings.php?tab=api'));
    exit;
}

$token = $_POST['_token'] ?? null;
if (!csrf_verify(is_string($token) ? $token : null)) {
    app_redirect(admin_url('settings.php?tab=api&error=csrf_failed'));
    exit;
}

$apiId = trim((string)($_POST['api_id'] ?? ''));
$affiliateId = trim((string)($_POST['affiliate_id'] ?? ''));
$floorPair = trim((string)($_POST['floor_pair'] ?? ''));
$connectTimeout = filter_var($_POST['connect_timeout'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 30],
]);
$timeout = filter_var($_POST['timeout'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 5, 'max_range' => 60],
]);
$prodHits = filter_var($_POST['prod_hits'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 100],
]);

$oldInputPayload = [
    'api_id' => $apiId,
    'affiliate_id' => $affiliateId,
    'floor_pair' => $floorPair,
    'connect_timeout' => $_POST['connect_timeout'] ?? '',
    'timeout' => $_POST['timeout'] ?? '',
    'prod_hits' => $_POST['prod_hits'] ?? '',
];

$local = local_config_load();
$currentApi = $local['dmm_api'] ?? [];
if (!is_array($currentApi)) {
    $currentApi = [];
}

if ($apiId === '') {
    $apiId = trim((string)($currentApi['api_id'] ?? ''));
}
if ($affiliateId === '') {
    $affiliateId = trim((string)($currentApi['affiliate_id'] ?? ''));
}

if ($apiId === '' || $affiliateId === '') {
    admin_flash_set('api_old', json_encode($oldInputPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    app_redirect(admin_url('settings.php?tab=api&error=missing_required'));
    exit;
}

if ($connectTimeout === false) {
    admin_flash_set('api_old', json_encode($oldInputPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    app_redirect(admin_url('settings.php?tab=api&error=invalid_connect_timeout'));
    exit;
}
if ($timeout === false) {
    admin_flash_set('api_old', json_encode($oldInputPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    app_redirect(admin_url('settings.php?tab=api&error=invalid_timeout'));
    exit;
}
if ($prodHits === false) {
    admin_flash_set('api_old', json_encode($oldInputPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    app_redirect(admin_url('settings.php?tab=api&error=invalid_prod_hits'));
    exit;
}


$parsedFloor = fanza_parse_floor_pair($floorPair);
if (!is_array($parsedFloor)) {
    admin_flash_set('api_old', json_encode($oldInputPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    app_redirect(admin_url('settings.php?tab=api&error=invalid_floor'));
    exit;
}

$site = 'FANZA';
$service = (string)$parsedFloor['service'];
$floor = (string)$parsedFloor['floor'];
$floorPair = (string)$parsedFloor['pair'];

$local['dmm_api'] = [
    'api_id' => $apiId,
    'affiliate_id' => $affiliateId,
    'site' => $site,
    'service' => $service,
    'floor' => $floor,
    'floor_pair' => $floorPair,
    'connect_timeout' => $connectTimeout,
    'timeout' => $timeout,
];

try {
    local_config_write($local);
    setting_set('api.prod_hits', (string)$prodHits);
} catch (Throwable $e) {
    error_log('save_settings failed: ' . $e->getMessage());
    admin_flash_set('api_old', json_encode($oldInputPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    app_redirect(admin_url('settings.php?tab=api&error=write_failed'));
    exit;
}

if ((string)($_POST['connection_test'] ?? '') === '1' || (string)($_POST['item_test'] ?? '') === '1') {
    $timeouts = fanza_api_timeout_config($local['dmm_api'] ?? null);
    $authResult = fanza_test_api_credentials($apiId, $affiliateId, $timeouts['connect_timeout'], $timeouts['timeout']);
    $itemResult = fanza_test_item_fetch($apiId, $affiliateId, $service, $floor, $timeouts['connect_timeout'], $timeouts['timeout']);

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['api_test_result'] = [
        'auth' => [
            'ok' => (bool)($authResult['ok'] ?? false),
            'http_code' => (int)($authResult['http_code'] ?? 0),
            'error_type' => (string)($authResult['error_type'] ?? ''),
            'message' => (string)($authResult['message'] ?? ''),
        ],
        'items' => [
            'ok' => (bool)($itemResult['ok'] ?? false),
            'http_code' => (int)($itemResult['http_code'] ?? 0),
            'error_type' => (string)($itemResult['error_type'] ?? ''),
            'message' => (string)($itemResult['message'] ?? ''),
            'service' => $service,
            'floor' => $floor,
            'item_count' => (int)($itemResult['item_count'] ?? 0),
        ],
    ];
    app_redirect(admin_url('settings.php?tab=api&tested=1&tested_auth=1&tested_items=1'));
    exit;
}

app_redirect(admin_url('settings.php?tab=api&saved=1'));
exit;
