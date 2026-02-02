<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/admin_auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/partners.php';

admin_basic_auth_required();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!csrf_verify($_POST['_token'] ?? null)) {
    header('Location: /admin/partners.php?error=csrf');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$name = trim((string)($_POST['name'] ?? ''));
$siteUrl = trim((string)($_POST['site_url'] ?? ''));
$rssUrl = trim((string)($_POST['rss_url'] ?? ''));
$token = trim((string)($_POST['token'] ?? ''));
$override = (string)($_POST['supports_images_override'] ?? 'auto');

if ($name === '') {
    header('Location: /admin/partners.php?error=name');
    exit;
}

if ($siteUrl !== '' && filter_var($siteUrl, FILTER_VALIDATE_URL) === false) {
    header('Location: /admin/partners.php?error=site_url');
    exit;
}

if ($rssUrl !== '' && filter_var($rssUrl, FILTER_VALIDATE_URL) === false) {
    header('Location: /admin/partners.php?error=rss_url');
    exit;
}

if ($token === '') {
    $token = bin2hex(random_bytes(16));
}

$token = normalize_partner_token($token);
if ($token === '') {
    header('Location: /admin/partners.php?error=token');
    exit;
}

$existing = fetch_partner_by_token($token);
if ($existing !== null && (int)$existing['id'] !== $id) {
    header('Location: /admin/partners.php?error=token_used');
    exit;
}

$overrideValue = null;
if ($override === 'yes') {
    $overrideValue = 1;
} elseif ($override === 'no') {
    $overrideValue = 0;
}

$payload = [
    'name' => $name,
    'site_url' => $siteUrl,
    'rss_url' => $rssUrl,
    'token' => $token,
    'supports_images_override' => $overrideValue,
];

if ($id > 0) {
    update_partner($id, $payload);
} else {
    add_partner($payload);
}

header('Location: /admin/partners.php?saved=1');
exit;
