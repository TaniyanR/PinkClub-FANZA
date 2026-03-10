<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

$id = (int)get('id', 0);
$series = fetch_series_one($id);
if ($series === null) {
    http_response_code(404);
    exit('not found');
}

$items = fetch_items_by_series((int)$series['id'], 100, 0);

$title = (string)($series['name'] ?? 'シリーズ詳細');
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => 'シリーズ一覧', 'url' => public_url('series_list.php')],
    ['label' => (string)($series['name'] ?? 'シリーズ詳細')],
]); ?>
<?php pcf_render_hero((string)($series['name'] ?? 'シリーズ詳細')); ?>
<?php if (!empty($series['ruby'])): ?><p class="pcf-list-card__meta">読み: <?= e((string)$series['ruby']) ?></p><?php endif; ?>

<h2 class="pcf-section-title">関連商品</h2>
<?php if ($items !== []): ?>
  <section class="pcf-related-grid">
    <?php foreach ($items as $item): pcf_render_item_card(is_array($item) ? $item : []); endforeach; ?>
  </section>
<?php else: ?>
  <?php pcf_render_empty('このシリーズの関連商品はありません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
