<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$id = (int)get('id', 0);
$row = fetch_actress($id);
if ($row === null) {
    http_response_code(404);
    exit('not found');
}

$list = fetch_items_by_actress((int)$row['id'], 100, 0);

$title = '女優詳細';
require __DIR__ . '/partials/header.php';
?>
<h2><?= e((string)$row['name']) ?></h2>
<p>誕生日: <?= e((string)($row['birthday'] ?? '')) ?></p>
<p>出身: <?= e((string)($row['prefectures'] ?? '')) ?></p>
<?php if (!empty($row['image_url'])): ?>
  <img src="<?= e((string)$row['image_url']) ?>" class="thumb" alt="<?= e((string)$row['name']) ?>">
<?php endif; ?>
<h3>関連商品</h3>
<ul>
  <?php foreach ($list as $item): ?>
    <li><a href="<?= e(public_url('item.php?id=' . (int)$item['id'])) ?>"><?= e((string)$item['title']) ?></a></li>
  <?php endforeach; ?>
</ul>
<?php require __DIR__ . '/partials/footer.php'; ?>
