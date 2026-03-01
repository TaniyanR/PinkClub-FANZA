<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$contentId = trim((string)get('content_id', ''));
if ($contentId === '') {
    http_response_code(404);
    exit('content_id が指定されていません。');
}

$stmt = db()->prepare('SELECT content_id, title, raw_json FROM items WHERE content_id = ? LIMIT 1');
$stmt->execute([$contentId]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) {
    http_response_code(404);
    exit('指定の商品が見つかりません。');
}

$decoded = json_decode((string)($item['raw_json'] ?? ''), true);
$images = [];
if (is_array($decoded) && isset($decoded['sampleImageURL']) && is_array($decoded['sampleImageURL'])) {
    foreach (['sample_l', 'sample_s'] as $sizeKey) {
        $list = $decoded['sampleImageURL'][$sizeKey]['image'] ?? null;
        if (is_array($list)) {
            foreach ($list as $image) {
                $url = trim((string)$image);
                if ($url !== '') {
                    $images[] = $url;
                }
            }
            if ($images !== []) {
                break;
            }
        }
    }
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e((string)$item['title']) ?> - サンプル画像</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 12px; background: #f8f9fa; }
    h1 { font-size: 18px; margin-bottom: 12px; position: sticky; top: 0; background: #f8f9fa; padding: 6px 0; }
    .message { text-align: center; color: #555; margin-top: 32px; }
    .sample-scroll { max-height: calc(100vh - 90px); overflow-y: auto; padding-right: 6px; }
    .sample-scroll::-webkit-scrollbar { width: 10px; }
    .sample-scroll::-webkit-scrollbar-thumb { background: #b9bdc5; border-radius: 8px; }
    .sample-frame { width: 800px; max-width: 100%; height: 450px; background: #fff; border: 1px solid #dcdcde; margin: 10px auto; display: flex; align-items: center; justify-content: center; scroll-margin-top: 12px; }
    .sample-frame img { width: 800px; height: 450px; object-fit: cover; display: block; }
  </style>
</head>
<body>
  <h1><?= e((string)$item['title']) ?> のサンプル画像</h1>
  <div class="sample-scroll">
  <?php if ($images === []): ?>
    <p class="message">画像がありません</p>
  <?php else: ?>
    <?php foreach ($images as $index => $image): ?>
      <div class="sample-frame" id="image-<?= e((string)($index + 1)) ?>">
        <img src="<?= e($image) ?>" alt="サンプル画像 <?= e((string)($index + 1)) ?>">
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
  </div>
</body>
</html>
