<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$id = (int)get('id', 0);
$name = trim((string)get('name', ''));

$row = null;
if ($id > 0) {
    $stmt = db()->prepare('SELECT * FROM makers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch() ?: null;
}

if ($row === null && $name !== '') {
    $stmt = db()->prepare('SELECT * FROM makers WHERE name = :name ORDER BY id ASC LIMIT 1');
    $stmt->execute([':name' => $name]);
    $row = $stmt->fetch() ?: null;
}

$displayName = is_array($row) ? (string)($row['name'] ?? '') : $name;
if ($displayName === '') {
    http_response_code(404);
    exit('not found');
}

if (is_array($row) && isset($row['id'])) {
    $list = fetch_items_by_maker((int)$row['id'], 100, 0);
} else {
    $stmt = db()->prepare(
        'SELECT i.*
         FROM items i
         INNER JOIN item_makers im ON im.item_id = i.id
         WHERE im.maker_name = :name
         ORDER BY i.release_date DESC, i.id DESC
         LIMIT 100'
    );
    $stmt->execute([':name' => $displayName]);
    $list = $stmt->fetchAll() ?: [];
}

$title = 'メーカー詳細';
require __DIR__ . '/partials/header.php';
?>
<h2><?= e($displayName) ?></h2>
<?php if (is_array($row)): ?>
  <p>ruby: <?= e((string)($row['ruby'] ?? '')) ?></p>
<?php endif; ?>
<h3>商品一覧</h3>
<ul>
  <?php foreach ($list as $item): ?>
    <li><a href="<?= e(public_url('item.php?id=' . (int)$item['id'])) ?>"><?= e((string)$item['title']) ?></a></li>
  <?php endforeach; ?>
</ul>
<?php require __DIR__ . '/partials/footer.php'; ?>
