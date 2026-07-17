<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

function vr_sample_json(bool $available): never
{
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: private, max-age=300');
    echo json_encode(['available' => $available], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function vr_sample_official_page_has_player(string $contentId): bool
{
    $cacheDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'pinkclub-vr-sample-cache';
    // v2: 旧判定で保存された「全件なし」の誤キャッシュを読み込まない。
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'v2-' . hash('sha256', $contentId) . '.json';
    $cacheLifetime = 21600;

    if (is_file($cacheFile) && (time() - (int)filemtime($cacheFile)) < $cacheLifetime) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached) && array_key_exists('available', $cached)) {
            return (bool)$cached['available'];
        }
    }

    $url = 'https://video.dmm.co.jp/av/content/?id=' . rawurlencode($contentId);
    $html = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_ENCODING => '',
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Accept-Language: ja,en-US;q=0.9,en;q=0.8',
                    'Cookie: age_check_done=1; ckcy=1',
                    'Referer: https://www.dmm.co.jp/',
                    'Upgrade-Insecure-Requests: 1',
                ],
            ]);
            $response = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if (is_string($response) && $status >= 200 && $status < 400) {
                $html = $response;
            }
        }
    }

    $available = false;
    if ($html !== '') {
        $quotedContentId = preg_quote($contentId, '/');
        $isAgeGate = str_contains($html, 'あなたは18歳以上ですか')
            || str_contains($html, '日本を代表するアダルトポータルへようこそ');

        if (!$isAgeGate) {
            $available = preg_match('/vr-sample-player[^\r\n"\']*' . $quotedContentId . '/iu', $html) === 1
                || (
                    preg_match('/cid[=\/:％%22]+'. $quotedContentId . '/iu', $html) === 1
                    && preg_match('/VR.{0,80}サンプル|サンプル.{0,80}VR/iu', $html) === 1
                );
        }
    }

    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
    if (is_dir($cacheDir) && is_writable($cacheDir)) {
        @file_put_contents($cacheFile, json_encode([
            'available' => $available,
            'checked_at' => time(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    return $available;
}

$itemId = max(0, (int)($_GET['id'] ?? 0));
$isCheckRequest = (string)($_GET['check'] ?? '') === '1';
if ($itemId <= 0) {
    if ($isCheckRequest) {
        vr_sample_json(false);
    }
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
    if ($isCheckRequest) {
        vr_sample_json(false);
    }
    http_response_code(404);
    exit;
}

$title = trim((string)($item['title'] ?? ''));
$contentId = trim((string)($item['content_id'] ?? ''));
$isVr = preg_match('/(?:【|\[|［)?\s*VR\s*(?:】|\]|］)?/iu', $title) === 1;
$isValidContentId = $contentId !== '' && preg_match('/^[a-z0-9_-]+$/i', $contentId) === 1;
$available = $isVr && $isValidContentId && vr_sample_official_page_has_player($contentId);

if ($isCheckRequest) {
    vr_sample_json($available);
}

if (!$available) {
    http_response_code(404);
    exit;
}

$playerUrl = 'https://www.dmm.co.jp/digital/-/vr-sample-player/=/cid=' . rawurlencode($contentId) . '/';
header('Cache-Control: private, max-age=300');
header('Location: ' . $playerUrl, true, 302);
exit;
