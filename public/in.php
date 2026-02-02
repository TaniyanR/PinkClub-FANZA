<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/partners.php';

header('Content-Type: text/plain; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo "Method Not Allowed\n";
    exit;
}

$token = normalize_partner_token((string)($_GET['token'] ?? ''));
if ($token === '') {
    http_response_code(400);
    echo "Invalid token\n";
    exit;
}

$partner = fetch_partner_by_token($token);
if ($partner === null) {
    http_response_code(404);
    echo "Partner not found\n";
    exit;
}

$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
$ipHash = hash_for_log($ip);
$uaHash = hash_for_log($ua);

$cfg = config_get('rss', []);
$dedupeWindow = (int)($cfg['dedupe_window_seconds'] ?? 600);

if (!is_recent_duplicate_access((int)$partner['id'], $ipHash, $uaHash, $dedupeWindow)) {
    $ref = isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : null;
    log_in_access((int)$partner['id'], $ipHash, $uaHash, $ref);
}

$retentionDays = (int)($cfg['log_retention_days'] ?? 14);
cleanup_in_access_logs($retentionDays);

http_response_code(204);
