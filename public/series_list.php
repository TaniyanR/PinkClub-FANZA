<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

$rows = fetch_series(500, 0, 'name');
$title = 'シリーズ一覧';
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero('シリーズ一覧'); ?>

<?php if ($rows !== []): ?>
  <section class="pcf-grid">
    <?php foreach ($rows as $r): ?>
      <article class="pcf-card pcf-list-card">
        <h3 class="pcf-list-card__title"><?= e((string)($r['name'] ?? '')) ?></h3>
        <?php if (!empty($r['item_count'])): ?><div class="pcf-list-card__meta">作品数: <?= e((string)$r['item_count']) ?></div><?php endif; ?>
        <p><a class="pcf-btn" href="<?= e(public_url('series_one.php?id=' . (int)($r['id'] ?? 0))) ?>">詳細を見る</a></p>
      </article>
    <?php endforeach; ?>
  </section>
<?php else: ?>
  <?php pcf_render_empty('シリーズ情報がありません。'); ?>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
