<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/local_config_writer.php';
require_once __DIR__ . '/../../lib/dmm_api.php';
require_once __DIR__ . '/../../lib/site_settings.php';

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
$site = trim((string)($_POST['site'] ?? 'FANZA'));
$service = trim((string)($_POST['service'] ?? 'digital'));
$floor = trim((string)($_POST['floor'] ?? 'videoa'));
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
    'site' => $site,
    'service' => $service,
    'floor' => $floor,
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

$allowedSites = ['FANZA', 'DMM'];
$allowedServices = ['digital'];
$allowedFloors = ['videoa', 'videoc', 'amateur'];

if (!in_array($site, $allowedSites, true)) {
    $site = 'FANZA';
}
if (!in_array($service, $allowedServices, true)) {
    $service = 'digital';
}
if (!in_array($floor, $allowedFloors, true)) {
    $floor = 'videoa';
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


$local['dmm_api'] = [
    'api_id' => $apiId,
    'affiliate_id' => $affiliateId,
    'site' => $site,
    'service' => $service,
    'floor' => $floor,
    'connect_timeout' => $connectTimeout,
    'timeout' => $timeout,
];

try {
    local_config_write($local);
    setting_set('api.prod_hits', (string)$prodHits);
} catch (Throwable $e) {
    error_log('save_settings failed: ' . $e->getMessage());
    app_redirect(admin_url('settings.php?tab=api&error=write_failed'));
    exit;
}

if ((string)($_POST['connection_test'] ?? '') === '1') {
    $response = dmm_api_request('ItemList', [
        'api_id' => $apiId,
        'affiliate_id' => $affiliateId,
        'site' => $site,
        'service' => $service,
        'floor' => $floor,
        'hits' => 10,
        'sort' => 'date',
        'output' => 'json',
    ]);

    $items = $response['data']['result']['items'] ?? [];
    $titles = [];
    if (is_array($items)) {
        foreach (array_slice($items, 0, 10) as $item) {
            if (is_array($item)) {
                $titles[] = (string)($item['title'] ?? '(タイトルなし)');
            }
        }
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['api_test_result'] = [
        'ok' => (bool)($response['ok'] ?? false),
        'http_code' => (int)($response['http_code'] ?? 0),
        'error' => (string)($response['error'] ?? ''),
        'titles' => $titles,
    ];
    app_redirect(admin_url('settings.php?tab=api&tested=1'));
    exit;
}

app_redirect(admin_url('settings.php?tab=api&saved=1'));
exit;
