<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

$id = (int)get('id', 0);
$row = null;
$list = [];
try {
    $row = fetch_genre($id);
    if ($row !== null) {
        $list = fetch_items_by_genre((int)$row['id'], 100, 0);
    }
} catch (Throwable) {
    $row = null;
    $list = [];
}
if ($row === null) {
    http_response_code(404);
    exit('not found');
}

$title = (string)($row['name'] ?? 'ジャンル詳細');
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => 'ジャンル一覧', 'url' => public_url('genres.php')],
    ['label' => (string)($row['name'] ?? 'ジャンル詳細')],
]); ?>
<?php pcf_render_hero((string)($row['name'] ?? 'ジャンル詳細')); ?>

<h2 class="pcf-section-title">関連商品</h2>
<?php if ($list !== []): ?>
  <section class="pcf-related-grid">
    <?php foreach ($list as $item): pcf_render_item_card(is_array($item) ? $item : []); endforeach; ?>
  </section>
<?php else: ?>
  <?php pcf_render_empty('このジャンルに紐づく商品はまだありません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
