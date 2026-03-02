<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$id = (int)get('id', 0);
$series = fetch_series_one($id);
if ($series === null) {
    http_response_code(404);
    exit('not found');
}

$items = fetch_items_by_series((int)$series['id'], 100, 0);

$title = 'シリーズ詳細';
require __DIR__ . '/partials/header.php';
?>
<h2><?= e((string)$series['name']) ?></h2>
<p>ruby: <?= e((string)($series['ruby'] ?? '')) ?></p>
<h3>商品一覧</h3>
<ul>
  <?php foreach ($items as $item): ?>
    <li><a href="<?= e(public_url('item.php?id=' . (int)$item['id'])) ?>"><?= e((string)$item['title']) ?></a></li>
  <?php endforeach; ?>
</ul>
<?php require __DIR__ . '/partials/footer.php'; ?>
