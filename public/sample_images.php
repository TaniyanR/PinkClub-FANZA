<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

function sample_images_parse_list(?string $value): array
{
    if ($value === null || trim($value) === '') {
        return [];
    }

    $trimmed = trim($value);
    if ($trimmed !== '' && $trimmed[0] === '[') {
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded)));
        }
    }

    $parts = preg_split('/[\r\n,|\s]+/', $value);
    if (!is_array($parts)) {
        return [];
    }

    return array_values(array_filter(array_map('trim', $parts), static fn(string $v): bool => $v !== ''));
}

function sample_images_maybe_decode_json_value(mixed $value): mixed
{
    if (!is_string($value)) {
        return $value;
    }

    $trimmed = trim($value);
    if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
        return $value;
    }

    $decoded = json_decode($trimmed, true);
    if (is_string($decoded)) {
        $decodedAgain = json_decode($decoded, true);
        return is_array($decodedAgain) ? $decodedAgain : $decoded;
    }

    return is_array($decoded) ? $decoded : $value;
}

function sample_images_collect_from_value(mixed $value, array &$images): void
{
    $value = sample_images_maybe_decode_json_value($value);
    if (is_string($value)) {
        foreach (sample_images_parse_list($value) as $candidate) {
            $url = trim((string)$candidate);
            if ($url !== '') {
                $images[] = $url;
            }
        }
        return;
    }

    if (!is_array($value)) {
        return;
    }

    foreach ($value as $child) {
        sample_images_collect_from_value($child, $images);
    }
}

$contentId = trim((string)get('content_id', ''));
if ($contentId === '') {
    http_response_code(404);
    exit('content_id が指定されていません。');
}

$stmt = db()->prepare('SELECT content_id, title, raw_json, image_list FROM items WHERE content_id = ? LIMIT 1');
$stmt->execute([$contentId]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) {
    http_response_code(404);
    exit('指定の商品が見つかりません。');
}

$decoded = sample_images_maybe_decode_json_value((string)($item['raw_json'] ?? ''));
$images = [];
if (is_array($decoded) && isset($decoded['sampleImageURL']) && is_array($decoded['sampleImageURL'])) {
    foreach (['sample_l', 'sample_s'] as $sizeKey) {
        $sampleImages = [];
        sample_images_collect_from_value($decoded['sampleImageURL'][$sizeKey]['image'] ?? null, $sampleImages);
        if ($sampleImages !== []) {
            $images = array_merge($images, $sampleImages);
            break;
        }
    }
}
$images = array_values(array_unique($images));
if ($images === []) {
    $images = array_values(array_unique(sample_images_parse_list((string)($item['image_list'] ?? ''))));
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e((string)$item['title']) ?> - サンプル画像</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 12px; background: #f8f9fa; overflow-y: hidden; }
    h1 { font-size: 18px; margin-bottom: 12px; position: sticky; top: 0; background: #f8f9fa; padding: 6px 0; }
    .message { text-align: center; color: #555; margin-top: 32px; }
    .sample-scroll { display: flex; gap: 10px; overflow-x: auto; overflow-y: hidden; padding-bottom: 6px; }
    .sample-scroll::-webkit-scrollbar { height: 10px; }
    .sample-scroll::-webkit-scrollbar-thumb { background: #b9bdc5; border-radius: 8px; }
    .sample-frame { width: min(720px, 88vw); flex: 0 0 min(720px, 88vw); max-width: none; background: #fff; border: 1px solid #dcdcde; margin: 0; display: flex; align-items: center; justify-content: center; }
    .sample-frame img { width: 100%; height: auto; object-fit: contain; display: block; }
  </style>
</head>
<body>
  <h1><?= e((string)$item['title']) ?> のサンプル画像</h1>
  <div class="sample-scroll">
  <?php if ($images === []): ?>
    <p class="message">画像がありません</p>
  <?php else: ?>
    <?php foreach ($images as $index => $image): ?>
      <div class="sample-frame">
        <img src="<?= e($image) ?>" alt="サンプル画像 <?= e((string)($index + 1)) ?>">
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
  </div>
</body>
</html>
