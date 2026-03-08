<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$rows = db()->query('SELECT id,name FROM genres ORDER BY name LIMIT 500')->fetchAll();
if ($rows === []) {
    $rows = db()->query("SELECT MIN(g.id) AS id, ig.genre_name AS name FROM item_genres ig LEFT JOIN genres g ON ((ig.dmm_id IS NOT NULL AND ig.dmm_id != '' AND g.dmm_id = ig.dmm_id) OR ig.genre_name = g.name) WHERE ig.genre_name IS NOT NULL AND ig.genre_name != '' GROUP BY ig.genre_name ORDER BY ig.genre_name LIMIT 500")->fetchAll();
}

$title = 'ジャンル一覧';
require __DIR__ . '/partials/header.php';
?>
<h2>ジャンル一覧</h2>
<ul>
<?php foreach ($rows as $r): ?>
  <li>
    <?php if ((int)($r['id'] ?? 0) > 0): ?>
      <a href="<?= e(app_url('public/genre.php?id=' . (int)$r['id'])) ?>"><?= e((string)$r['name']) ?></a>
    <?php else: ?>
      <?= e((string)$r['name']) ?>
    <?php endif; ?>
  </li>
<?php endforeach; ?>
</ul>
<?php require __DIR__ . '/partials/footer.php'; ?>
