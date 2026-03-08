<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$sql = "
    SELECT
        COALESCE(a.id, 0) AS id,
        ia.actress_name AS name,
        COUNT(DISTINCT ia.item_id) AS item_count
    FROM item_actresses ia
    LEFT JOIN actresses a
      ON ((ia.dmm_id IS NOT NULL AND ia.dmm_id != '' AND a.dmm_id = ia.dmm_id) OR ia.actress_name = a.name)
    WHERE ia.actress_name IS NOT NULL AND ia.actress_name != ''
    GROUP BY COALESCE(a.id, 0), ia.actress_name
    ORDER BY item_count DESC, ia.actress_name ASC
    LIMIT 500
";
$rows = db()->query($sql)->fetchAll();

$title = '女優一覧';
require __DIR__ . '/partials/header.php';
?>
<section class="block">
  <div class="section-head"><h1 class="section-title">女優一覧</h1></div>
  <div class="taxonomy-grid">
    <?php foreach ($rows as $r): ?>
      <?php
        $id = (int)($r['id'] ?? 0);
        $name = (string)($r['name'] ?? '');
        $count = (int)($r['item_count'] ?? 0);
        $href = $id > 0
          ? app_url('public/actress.php?id=' . $id)
          : app_url('public/actress.php?name=' . rawurlencode($name));
      ?>
      <a class="taxonomy-card" href="<?= e($href) ?>">
        <div class="taxonomy-card__media"><?= e((string)$count) ?>件</div>
        <div class="taxonomy-card__name"><?= e($name) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
