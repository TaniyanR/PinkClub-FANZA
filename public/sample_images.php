<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/images.php';

$contentId = trim((string)get('content_id', ''));
$validatedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$id = ($validatedId === null || $validatedId === false) ? 0 : (int)$validatedId;
if ($contentId === '' && $id <= 0) {
    error_log('sample_images.php request missing item identifier');
    http_response_code(404);
    exit('商品識別子が指定されていません。');
}

$params = [];
$where = '';
if ($contentId !== '') {
    $where = 'content_id = :content_id';
    $params[':content_id'] = $contentId;
} else {
    $where = 'id = :id';
    $params[':id'] = $id;
}

$stmt = db()->prepare('SELECT id, content_id, title, sample_images, raw_json, image_list FROM items WHERE ' . $where . ' LIMIT 1');
$stmt->execute($params);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) {
    error_log('sample_images.php item not found');
    http_response_code(404);
    exit('指定の商品が見つかりません。');
}

$images = pcf_item_sample_images($item);
if ($images === []) {
    error_log('sample_images.php no displayable sample images for item ' . (string)($item['id'] ?? ''));
}

if (strtolower(trim((string)get('format', ''))) === 'json') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: public, max-age=300');
    echo json_encode([
        'title' => (string)$item['title'],
        'images' => $images,
        'error' => $images === [] ? 'no_images' : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    exit;
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e((string)$item['title']) ?> - サンプル画像</title>
  <style>
    html, body { height: 100%; }
    body { font-family: Arial, sans-serif; margin: 12px; background: #f8f9fa; overflow: hidden; box-sizing: border-box; height: calc(100vh - 24px); display: flex; flex-direction: column; }
    h1 { font-size: 18px; margin-bottom: 12px; position: sticky; top: 0; background: #f8f9fa; padding: 6px 0; flex: 0 0 auto; }
    .message { text-align: center; color: #555; margin-top: 32px; }
    .sample-viewer { position: relative; flex: 1 1 auto; min-height: 0; }
    .sample-scroll { display: flex; flex-wrap: nowrap; gap: 10px; overflow-x: auto; overflow-y: hidden; padding-bottom: 6px; height: 100%; min-height: 0; scroll-behavior: smooth; }
    .sample-scroll::-webkit-scrollbar { height: 10px; }
    .sample-scroll::-webkit-scrollbar-thumb { background: #b9bdc5; border-radius: 8px; }
    .sample-frame { width: min(840px, calc(100vw - 54px)); height: 100%; flex: 0 0 min(840px, calc(100vw - 54px)); max-width: none; background: #fff; border: 1px solid #dcdcde; margin: 0; display: flex; align-items: center; justify-content: center; box-sizing: border-box; }
    .sample-frame img { width: 100%; height: 100%; max-width: 100%; max-height: 100%; object-fit: contain; display: block; }
    .sample-arrow { position: absolute; top: 50%; z-index: 2; width: 48px; height: 48px; margin-top: -24px; border: 0; border-radius: 50%; background: rgba(255, 255, 255, 0.92); color: #222; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.28); font-size: 30px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: opacity .2s ease, transform .2s ease; }
    .sample-prev { left: 14px; }
    .sample-next { right: 14px; }
    .sample-arrow:hover { transform: scale(1.06); }
    .sample-arrow:focus-visible { outline: 3px solid #ff4f9a; outline-offset: 2px; }
    .sample-arrow[hidden] { display: none; }
    @media (max-width: 600px) {
      .sample-arrow { width: 44px; height: 44px; margin-top: -22px; font-size: 27px; }
      .sample-prev { left: 8px; }
      .sample-next { right: 8px; }
    }
  </style>
</head>
<body>
  <h1><?= e((string)$item['title']) ?> のサンプル画像</h1>
  <div class="sample-viewer">
    <div class="sample-scroll" id="sampleScroll">
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
    <?php if (count($images) > 1): ?>
      <button type="button" class="sample-arrow sample-prev" id="samplePrev" aria-label="前のサンプル画像へ" hidden>‹</button>
      <button type="button" class="sample-arrow sample-next" id="sampleNext" aria-label="次のサンプル画像へ">›</button>
    <?php endif; ?>
  </div>
  <?php if (count($images) > 1): ?>
  <script>
  (function () {
    var scroller = document.getElementById('sampleScroll');
    var prevButton = document.getElementById('samplePrev');
    var nextButton = document.getElementById('sampleNext');
    if (!scroller || !prevButton || !nextButton) {
      return;
    }

    var updateButtons = function () {
      var remaining = scroller.scrollWidth - scroller.clientWidth - scroller.scrollLeft;
      prevButton.hidden = scroller.scrollLeft <= 4;
      nextButton.hidden = remaining <= 4;
    };

    var scrollOneFrame = function (direction) {
      var frame = scroller.querySelector('.sample-frame');
      var distance = frame ? frame.getBoundingClientRect().width + 10 : Math.max(280, scroller.clientWidth * 0.85);
      scroller.scrollBy({ left: direction * distance, behavior: 'smooth' });
    };

    prevButton.addEventListener('click', function () {
      scrollOneFrame(-1);
    });

    nextButton.addEventListener('click', function () {
      scrollOneFrame(1);
    });

    scroller.addEventListener('scroll', updateButtons, { passive: true });
    window.addEventListener('resize', updateButtons);
    updateButtons();
  }());
  </script>
  <?php endif; ?>
</body>
</html>
