<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

$id = (int)get('id', 0);
$series = null;
$items = [];
try {
    $series = fetch_series_one($id);
    if ($series !== null) {
        $items = fetch_items_by_series((int)$series['id'], 100, 0);
    }
} catch (Throwable) {
    $series = null;
    $items = [];
}
if ($series === null) {
    http_response_code(404);
    exit('not found');
}

$title = (string)($series['name'] ?? 'シリーズ詳細');
require __DIR__ . '/partials/header.php';
$oldestItem = pcf_pick_oldest_item($items);
$oldestImage = pcf_item_image(is_array($oldestItem) ? $oldestItem : []);
?>
<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => 'シリーズ一覧', 'url' => public_url('series_list.php')],
    ['label' => (string)($series['name'] ?? 'シリーズ詳細')],
]); ?>
<section class="pcf-topic-head">
  <img class="pcf-topic-head__image" src="<?= e($oldestImage) ?>" alt="<?= e((string)($series['name'] ?? 'シリーズ詳細')) ?>">
  <div>
    <h1 class="pcf-hero__title"><?= e((string)($series['name'] ?? 'シリーズ詳細')) ?></h1>
    <?php if (!empty($series['ruby'])): ?><p class="pcf-list-card__meta">読み: <?= e((string)$series['ruby']) ?></p><?php endif; ?>
    <p class="pcf-list-card__meta">関連商品: <?= e((string)count($items)) ?>件 / 最古作品画像</p>
  </div>
</section>

<h2 class="pcf-section-title">関連商品</h2>
<?php if ($items !== []): ?>
  <section class="pcf-related-grid">
    <?php foreach ($items as $item): pcf_render_item_card(is_array($item) ? $item : []); endforeach; ?>
  </section>
<?php else: ?>
  <?php pcf_render_empty('このシリーズの関連商品はありません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
