<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: private, max-age=300');
    header('X-Robots-Tag: noindex, nofollow', true);
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['images' => []], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$rawIds = trim((string)($_GET['ids'] ?? ''));
$ids = [];
foreach (preg_split('/\s*,\s*/', $rawIds) ?: [] as $value) {
    if ($value === '' || !ctype_digit($value)) {
        continue;
    }
    $id = (int)$value;
    if ($id > 0 && !in_array($id, $ids, true)) {
        $ids[] = $id;
    }
    if (count($ids) >= 10) {
        break;
    }
}

if ($ids === []) {
    echo json_encode(['images' => []], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$normalizeImageUrl = static function (string $value): string {
    $url = trim($value);
    if ($url === '') {
        return '';
    }
    if (str_starts_with($url, '//')) {
        $url = 'https:' . $url;
    }
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return '';
    }
    return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : '';
};

$firstImageFromMixed = static function (mixed $value) use (&$firstImageFromMixed, $normalizeImageUrl): string {
    if (is_string($value)) {
        return $normalizeImageUrl($value);
    }
    if (!is_array($value)) {
        return '';
    }
    foreach (['large', 'small', 'image', 'url', 'src', 'value'] as $key) {
        if (array_key_exists($key, $value)) {
            $candidate = $firstImageFromMixed($value[$key]);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }
    foreach ($value as $child) {
        $candidate = $firstImageFromMixed($child);
        if ($candidate !== '') {
            return $candidate;
        }
    }
    return '';
};

$images = [];
try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        'SELECT id, image_small, raw_json FROM items WHERE id IN (' . $placeholders . ')'
    );
    foreach ($ids as $index => $id) {
        $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
    }
    $stmt->execute();

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $raw = [];
        $rawJson = trim((string)($row['raw_json'] ?? ''));
        if ($rawJson !== '') {
            $decoded = json_decode($rawJson, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }

        // 「最近見た作品」では見開きのfull packageではなく、商品表紙を優先する。
        $candidates = [
            $firstImageFromMixed($raw['imageURL']['large'] ?? null),
            $normalizeImageUrl((string)($row['image_small'] ?? '')),
            $firstImageFromMixed($raw['imageURL']['small'] ?? null),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                $images[(string)$id] = $candidate;
                break;
            }
        }
    }
} catch (Throwable $e) {
    error_log('[recent_images] failed: ' . $e->getMessage());
}

echo json_encode(['images' => $images], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
