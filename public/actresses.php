<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$page = max(1, (int)get('page', 1));
$per = 20;
$total = (int)db()->query('SELECT COUNT(*) FROM actresses')->fetchColumn();

if ($total > 0) {
    $pg = paginate($total, $page, $per);
    $s = db()->prepare('SELECT id,name FROM actresses ORDER BY name LIMIT :l OFFSET :o');
    $s->bindValue(':l', $pg['perPage'], PDO::PARAM_INT);
    $s->bindValue(':o', $pg['offset'], PDO::PARAM_INT);
    $s->execute();
    $rows = $s->fetchAll();
} else {
    $rows = db()->query("SELECT MIN(a.id) AS id, ia.actress_name AS name FROM item_actresses ia LEFT JOIN actresses a ON ((ia.dmm_id IS NOT NULL AND ia.dmm_id != '' AND a.dmm_id = ia.dmm_id) OR ia.actress_name = a.name) WHERE ia.actress_name IS NOT NULL AND ia.actress_name != '' GROUP BY ia.actress_name ORDER BY ia.actress_name LIMIT 500")->fetchAll();
}

$title = '女優一覧';
require __DIR__ . '/partials/header.php';
?>
<h2>女優一覧</h2>
<ul>
<?php foreach ($rows as $r): ?>
  <li>
    <?php if ((int)($r['id'] ?? 0) > 0): ?>
      <a href="<?= e(app_url('public/actress.php?id=' . (int)$r['id'])) ?>"><?= e((string)$r['name']) ?></a>
    <?php else: ?>
      <?= e((string)$r['name']) ?>
    <?php endif; ?>
  </li>
<?php endforeach; ?>
</ul>
<?php require __DIR__ . '/partials/footer.php'; ?>
