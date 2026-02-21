<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/local_config_writer.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    app_redirect(admin_url('settings.php?tab=api'));
}

$token = $_POST['_token'] ?? null;
if (!csrf_verify(is_string($token) ? $token : null)) {
    app_redirect(admin_url('settings.php?tab=api&error=csrf_failed'));
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
    app_redirect(admin_url('settings.php?tab=api&error=missing_required'));
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
    $connectTimeout = 10;
}
if ($timeout === false) {
    $timeout = 20;
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
} catch (Throwable $e) {
    error_log('save_settings failed: ' . $e->getMessage());
    app_redirect(admin_url('settings.php?tab=api&error=write_failed'));
}

app_redirect(admin_url('settings.php?tab=api&saved=1'));
