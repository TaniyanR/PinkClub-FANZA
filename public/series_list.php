<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$rows = fetch_series(500, 0, 'name');
$title = 'シリーズ一覧';
require __DIR__ . '/partials/header.php';
?>
<h2>シリーズ一覧</h2>
<ul>
  <?php foreach ($rows as $row): ?>
    <li><a href="<?= e(public_url('series_one.php?id=' . (int)$row['id'])) ?>"><?= e((string)$row['name']) ?></a></li>
  <?php endforeach; ?>
</ul>
<?php require __DIR__ . '/partials/footer.php'; ?>
