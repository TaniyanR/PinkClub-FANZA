<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$rows = db()->query('SELECT id,name FROM makers ORDER BY name LIMIT 500')->fetchAll();
if ($rows === []) {
    $rows = db()->query("SELECT MIN(m.id) AS id, im.maker_name AS name FROM item_makers im LEFT JOIN makers m ON ((im.dmm_id IS NOT NULL AND im.dmm_id != '' AND m.dmm_id = im.dmm_id) OR im.maker_name = m.name) WHERE im.maker_name IS NOT NULL AND im.maker_name != '' GROUP BY im.maker_name ORDER BY im.maker_name LIMIT 500")->fetchAll();
}

$title = 'メーカー一覧';
require __DIR__ . '/partials/header.php';
?>
<h2>メーカー一覧</h2>
<ul>
<?php foreach ($rows as $r): ?>
  <li>
    <?php if ((int)($r['id'] ?? 0) > 0): ?>
      <a href="<?= e(app_url('public/maker.php?id=' . (int)$r['id'])) ?>"><?= e((string)$r['name']) ?></a>
    <?php else: ?>
      <?= e((string)$r['name']) ?>
    <?php endif; ?>
  </li>
<?php endforeach; ?>
</ul>
<?php require __DIR__ . '/partials/footer.php'; ?>
