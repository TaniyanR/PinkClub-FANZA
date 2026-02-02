<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/partners.php';
require_once __DIR__ . '/../lib/rss.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Method Not Allowed\n";
    exit;
}

$token = normalize_partner_token((string)($_GET['token'] ?? ''));
if ($token === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Invalid token\n";
    exit;
}

$partner = fetch_partner_by_token($token);
if ($partner === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Partner not found\n";
    exit;
}

$cfg = rss_config();
$ttl = (int)($cfg['cache_ttl_seconds'] ?? 60);
if ($ttl > 0) {
    $cached = read_partner_cache($token, $ttl);
    if ($cached !== null) {
        header('Content-Type: application/rss+xml; charset=UTF-8');
        echo $cached;
        exit;
    }
}

refresh_partner_image_support($partner);
// refresh後の最新値を使う
$partner = fetch_partner_by_id((int)$partner['id']) ?? $partner;
$supportsImages = supports_images_final($partner);

$itemsCount = compute_items_count((int)$partner['id']);
$weightNew = (float)($cfg['weight_new'] ?? 0.6);
$items = fetch_rss_items($itemsCount, $weightNew);

$rssXml = build_rss($partner, $items, $supportsImages);
if ($ttl > 0) {
    write_partner_cache($token, $rssXml);
}

header('Content-Type: application/rss+xml; charset=UTF-8');
echo $rssXml;
