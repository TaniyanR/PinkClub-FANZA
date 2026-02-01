<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/settings.php');
    exit;
}

$apiId = trim((string)($_POST['api_id'] ?? ''));
$affiliateId = trim((string)($_POST['affiliate_id'] ?? ''));
$site = trim((string)($_POST['site'] ?? 'FANZA'));
$service = trim((string)($_POST['service'] ?? 'digital'));
$floor = trim((string)($_POST['floor'] ?? 'videoa'));

if ($apiId === '' || $affiliateId === '') {
    header('Location: /admin/settings.php?error=1');
    exit;
}

$localPath = __DIR__ . '/../../config.local.php';

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
];

$export = "<?php\n";
$export .= "declare(strict_types=1);\n\n";
$export .= "return " . var_export($local, true) . ";\n";

$result = @file_put_contents($localPath, $export, LOCK_EX);
if ($result === false) {
    header('Location: /admin/settings.php?error=2');
    exit;
}

header('Location: /admin/settings.php?saved=1');
exit;
