<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$sql = "
    SELECT
        COALESCE(g.id, 0) AS id,
        ig.genre_name AS name,
        COUNT(DISTINCT ig.item_id) AS item_count
    FROM item_genres ig
    LEFT JOIN genres g
      ON ((ig.dmm_id IS NOT NULL AND ig.dmm_id != '' AND g.dmm_id = ig.dmm_id) OR ig.genre_name = g.name)
    WHERE ig.genre_name IS NOT NULL AND ig.genre_name != ''
    GROUP BY COALESCE(g.id, 0), ig.genre_name
    ORDER BY item_count DESC, ig.genre_name ASC
    LIMIT 500
";
$rows = db()->query($sql)->fetchAll();

$title = 'ジャンル一覧';
require __DIR__ . '/partials/header.php';
?>
<section class="block">
  <div class="section-head"><h1 class="section-title">ジャンル一覧</h1></div>
  <div class="taxonomy-grid">
    <?php foreach ($rows as $r): ?>
      <?php
        $id = (int)($r['id'] ?? 0);
        $name = (string)($r['name'] ?? '');
        $count = (int)($r['item_count'] ?? 0);
        $href = $id > 0
          ? app_url('public/genre.php?id=' . $id)
          : app_url('public/genre.php?name=' . rawurlencode($name));
      ?>
      <a class="taxonomy-card" href="<?= e($href) ?>">
        <div class="taxonomy-card__media"><?= e((string)$count) ?>件</div>
        <div class="taxonomy-card__name"><?= e($name) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
