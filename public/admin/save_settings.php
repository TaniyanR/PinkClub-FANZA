<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/admin_auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

admin_basic_auth_required();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

if (!csrf_verify($_POST['_token'] ?? null)) {
    redirect('/admin/settings.php?error=csrf');
}

// 入力（最低限のバリデーション）
$apiId = trim((string)($_POST['api_id'] ?? ''));
$affiliateId = trim((string)($_POST['affiliate_id'] ?? ''));
$site = trim((string)($_POST['site'] ?? 'FANZA'));
$service = trim((string)($_POST['service'] ?? 'digital'));
$floor = trim((string)($_POST['floor'] ?? 'videoa'));

$errors = [];
if ($apiId === '') {
    $errors[] = 'api_id が空です';
}
if ($affiliateId === '') {
    $errors[] = 'affiliate_id が空です';
}
if ($site === '') {
    $errors[] = 'site が空です';
}
if ($service === '') {
    $errors[] = 'service が空です';
}
if ($floor === '') {
    $errors[] = 'floor が空です';
}

if ($errors) {
    redirect('/admin/settings.php?error=validation');
}

// dmm_api のみを config.local.php に保存（秘密情報は local に置く）
$local = [
    'dmm_api' => [
        'api_id' => $apiId,
        'affiliate_id' => $affiliateId,
        'site' => $site,
        'service' => $service,
        'floor' => $floor,
    ],
];

$export = "<?php\nreturn " . var_export($local, true) . ";\n";

$path = __DIR__ . '/../../config.local.php';
$tmp  = $path . '.tmp';

if (file_put_contents($tmp, $export, LOCK_EX) === false) {
    redirect('/admin/settings.php?error=write');
}

if (!@rename($tmp, $path)) {
    @unlink($tmp);
    redirect('/admin/settings.php?error=rename');
}

redirect('/admin/settings.php?saved=1');
