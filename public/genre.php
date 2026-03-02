<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$id = (int)get('id', 0);
$row = fetch_genre($id);
if ($row === null) {
    http_response_code(404);
    exit('not found');
}

$list = fetch_items_by_genre((int)$row['id'], 100, 0);

$title = 'ジャンル詳細';
require __DIR__ . '/partials/header.php';
?>
<h2><?= e((string)$row['name']) ?></h2>
<h3>商品一覧</h3>
<ul>
  <?php foreach ($list as $item): ?>
    <li><a href="<?= e(public_url('item.php?id=' . (int)$item['id'])) ?>"><?= e((string)$item['title']) ?></a></li>
  <?php endforeach; ?>
</ul>
<?php require __DIR__ . '/partials/footer.php'; ?>
