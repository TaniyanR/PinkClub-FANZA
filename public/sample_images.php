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
    h1 { font-size: 18px; margin-bottom: 12px; }
    .message { text-align: center; color: #555; margin-top: 32px; }
    .sample-frame { width: 800px; max-width: 100%; height: 450px; background: #fff; border: 1px solid #dcdcde; margin: 10px auto; display: flex; align-items: center; justify-content: center; }
    .sample-frame img { width: 800px; height: 450px; object-fit: contain; display: block; }
  </style>
</head>
<body>
  <h1><?= e((string)$item['title']) ?> のサンプル画像</h1>
  <?php if ($images === []): ?>
    <p class="message">画像がありません</p>
  <?php else: ?>
    <?php foreach ($images as $index => $image): ?>
      <div class="sample-frame">
        <img src="<?= e($image) ?>" alt="サンプル画像 <?= e((string)($index + 1)) ?>">
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</body>
</html>
