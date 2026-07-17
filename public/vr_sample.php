<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$itemId = max(0, (int)($_GET['id'] ?? 0));
if ($itemId <= 0) {
    http_response_code(404);
    exit;
}

try {
    $stmt = db()->prepare('SELECT content_id, title FROM items WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable) {
    $item = false;
}

if (!is_array($item)) {
    http_response_code(404);
    exit;
}

$title = trim((string)($item['title'] ?? ''));
$contentId = trim((string)($item['content_id'] ?? ''));

if (preg_match('/(?:【|\[|［)?\s*VR\s*(?:】|\]|］)?/iu', $title) !== 1) {
    http_response_code(404);
    exit;
}

if ($contentId === '' || preg_match('/^[a-z0-9_-]+$/i', $contentId) !== 1) {
    http_response_code(404);
    exit;
}

$playerUrl = 'https://www.dmm.co.jp/digital/-/vr-sample-player/=/cid=' . rawurlencode($contentId) . '/';
header('Cache-Control: private, max-age=300');
header('Location: ' . $playerUrl, true, 302);
exit;
