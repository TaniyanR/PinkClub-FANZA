<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/public_ui.php';

$rows = db()->query('SELECT a.*, COUNT(ia.item_id) AS item_count FROM authors a LEFT JOIN item_authors ia ON a.id = ia.author_id GROUP BY a.id ORDER BY a.name LIMIT 500')->fetchAll() ?: [];
$title = '作者一覧';
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero('作者一覧'); ?>

<?php if ($rows !== []): ?>
  <section class="pcf-grid">
    <?php foreach ($rows as $r): ?>
      <article class="pcf-card pcf-list-card">
        <h3 class="pcf-list-card__title"><?= e((string)($r['name'] ?? '')) ?></h3>
        <?php if (!empty($r['item_count'])): ?><div class="pcf-list-card__meta">作品数: <?= e((string)$r['item_count']) ?></div><?php endif; ?>
        <p><a class="pcf-btn" href="<?= e(public_url('author.php?id=' . (int)($r['id'] ?? 0))) ?>">詳細を見る</a></p>
      </article>
    <?php endforeach; ?>
  </section>
<?php else: ?>
  <?php pcf_render_empty('作者情報がありません。'); ?>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
