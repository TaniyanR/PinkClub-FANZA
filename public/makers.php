<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$sql = "
    SELECT
        COALESCE(m.id, 0) AS id,
        im.maker_name AS name,
        COUNT(DISTINCT im.item_id) AS item_count
    FROM item_makers im
    LEFT JOIN makers m
      ON ((im.dmm_id IS NOT NULL AND im.dmm_id != '' AND m.dmm_id = im.dmm_id) OR im.maker_name = m.name)
    WHERE im.maker_name IS NOT NULL AND im.maker_name != ''
    GROUP BY COALESCE(m.id, 0), im.maker_name
    ORDER BY item_count DESC, im.maker_name ASC
    LIMIT 500
";
$rows = db()->query($sql)->fetchAll();

$title = 'メーカー一覧';
require __DIR__ . '/partials/header.php';
?>
<section class="block">
  <div class="section-head"><h1 class="section-title">メーカー一覧</h1></div>
  <div class="taxonomy-grid">
    <?php foreach ($rows as $r): ?>
      <?php
        $id = (int)($r['id'] ?? 0);
        $name = (string)($r['name'] ?? '');
        $count = (int)($r['item_count'] ?? 0);
        $href = $id > 0
          ? app_url('public/maker.php?id=' . $id)
          : app_url('public/maker.php?name=' . rawurlencode($name));
      ?>
      <a class="taxonomy-card" href="<?= e($href) ?>">
        <div class="taxonomy-card__media"><?= e((string)$count) ?>件</div>
        <div class="taxonomy-card__name"><?= e($name) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
