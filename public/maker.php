<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

$id = (int)get('id', 0);
$row = null;
$list = [];
try {
    $row = fetch_maker($id);
    if ($row !== null) {
        $list = dedupe_items_by_key(fetch_items_by_maker((int)$row['id'], 100, 0));
    }
} catch (Throwable) {
    $row = null;
    $list = [];
}
if ($row === null) {
    http_response_code(404);
    exit('not found');
}

$title = (string)($row['name'] ?? 'メーカー詳細');
require __DIR__ . '/partials/header.php';
$oldestItem = pcf_pick_oldest_item($list);
$oldestImage = pcf_item_image(is_array($oldestItem) ? $oldestItem : []);
?>
<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => 'メーカー一覧', 'url' => public_url('makers.php')],
    ['label' => (string)($row['name'] ?? 'メーカー詳細')],
]); ?>

<section class="pcf-topic-head">
  <img class="pcf-topic-head__image" src="<?= e($oldestImage) ?>" alt="<?= e((string)($row['name'] ?? 'メーカー詳細')) ?>">
  <div>
    <h1 class="pcf-hero__title"><?= e((string)($row['name'] ?? 'メーカー詳細')) ?></h1>
    <?php if (!empty($row['ruby'])): ?><p class="pcf-list-card__meta">読み: <?= e((string)$row['ruby']) ?></p><?php endif; ?>
    <p class="pcf-list-card__meta">関連商品: <?= e((string)count($list)) ?>件 / 最古作品画像</p>
  </div>
</section>

<h2 class="pcf-section-title">関連商品</h2>
<?php if ($list !== []): ?>
  <section class="pcf-related-grid">
    <?php foreach ($list as $item): pcf_render_item_card(is_array($item) ? $item : []); endforeach; ?>
  </section>
<?php else: ?>
  <?php pcf_render_empty('このメーカーの関連商品はありません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
