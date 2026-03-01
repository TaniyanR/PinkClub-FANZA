<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$contentId = trim((string)get('content_id', ''));
if ($contentId === '') {
    http_response_code(404);
    exit('content_id が指定されていません。');
}

try {
    $stmt = db()->prepare('SELECT content_id, title, raw_json FROM items WHERE content_id = ? LIMIT 1');
    $stmt->execute([$contentId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('public/sample_images.php load failed: ' . $e->getMessage());
    http_response_code(500);
    exit('DB接続に失敗しました（設定を確認してください）。');
}

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
    .sample-frame { width: 800px; max-width: 100%; height: 450px; background: #fff; border: 1px solid #dcdcde; margin: 10px auto; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .sample-frame img { display: block; transform-origin: center center; }
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
  <script>
    (() => {
      const frames = document.querySelectorAll('.sample-frame');
      frames.forEach((frame) => {
        const img = frame.querySelector('img');
        if (!img) return;
        img.addEventListener('load', () => {
          const fw = 800;
          const fh = 450;
          const iw = img.naturalWidth || fw;
          const ih = img.naturalHeight || fh;
          const scale = Math.min(fw / iw, fh / ih);
          img.style.width = `${Math.round(iw * scale)}px`;
          img.style.height = `${Math.round(ih * scale)}px`;
        });
      });
    })();
  </script>
</body>
</html>
