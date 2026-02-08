<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /admin/settings.php');
    exit;
}

$token = $_POST['_token'] ?? null;
if (!csrf_verify(is_string($token) ? $token : null)) {
    header('Location: /admin/settings.php?error=csrf_failed');
    exit;
}

$apiId = trim((string)($_POST['api_id'] ?? ''));
$affiliateId = trim((string)($_POST['affiliate_id'] ?? ''));
$site = trim((string)($_POST['site'] ?? 'FANZA'));
$service = trim((string)($_POST['service'] ?? 'digital'));
$floor = trim((string)($_POST['floor'] ?? 'videoa'));
$connectTimeout = (int)($_POST['connect_timeout'] ?? 10);
$timeout = (int)($_POST['timeout'] ?? 20);

if ($apiId === '' || $affiliateId === '') {
    header('Location: /admin/settings.php?error=missing_required');
    exit;
}

$allowedSites = ['FANZA', 'DMM'];
$allowedServices = ['digital'];
$allowedFloors = ['videoa'];

if (!in_array($site, $allowedSites, true)) {
    $site = 'FANZA';
}
if (!in_array($service, $allowedServices, true)) {
    $service = 'digital';
}
if (!in_array($floor, $allowedFloors, true)) {
    $floor = 'videoa';
}
if ($connectTimeout < 1 || $connectTimeout > 30) {
    $connectTimeout = 10;
}
if ($timeout < 5 || $timeout > 60) {
    $timeout = 20;
}

$localPath = __DIR__ . '/../../config.local.php';
$dir = dirname($localPath);
$tmp = $localPath . '.tmp';

if (!is_dir($dir) || !is_writable($dir)) {
    header('Location: /admin/settings.php?error=not_writable_dir');
    exit;
}

if (is_file($localPath) && !is_writable($localPath)) {
    header('Location: /admin/settings.php?error=not_writable_file');
    exit;
}

$local = [];
if (is_file($localPath)) {
    $loaded = require $localPath;
    if (is_array($loaded)) {
        $local = $loaded;
    }
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

$export = "<?php\n";
$export .= "declare(strict_types=1);\n\n";
$export .= "return " . var_export($local, true) . ";\n";

$result = @file_put_contents($tmp, $export, LOCK_EX);
if ($result === false) {
    header('Location: /admin/settings.php?error=write_failed');
    exit;
}

if (!@rename($tmp, $localPath)) {
    @unlink($tmp);
    header('Location: /admin/settings.php?error=rename_failed');
    exit;
}

@chmod($localPath, 0640);

header('Location: /admin/settings.php?saved=1');
exit;
