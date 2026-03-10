<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/public_ui.php';

$page = max(1, (int)get('page', 1));
$per = app_config()['pagination']['per_page'] ?? 24;
$total = (int)db()->query('SELECT COUNT(*) FROM items')->fetchColumn();
$pg = paginate($total, $page, (int)$per);

$stmt = db()->prepare('SELECT * FROM items ORDER BY release_date DESC, id DESC LIMIT :l OFFSET :o');
$stmt->bindValue(':l', (int)$pg['perPage'], PDO::PARAM_INT);
$stmt->bindValue(':o', (int)$pg['offset'], PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll() ?: [];

$title = '商品一覧';
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero('商品一覧', '最新の作品を一覧でチェックできます。'); ?>

<?php if ($rows !== []): ?>
  <section class="pcf-grid">
    <?php foreach ($rows as $r): ?>
      <?php pcf_render_item_card(is_array($r) ? $r : []); ?>
    <?php endforeach; ?>
  </section>
  <?php pcf_render_pagination($pg, public_url('items.php')); ?>
<?php else: ?>
  <?php pcf_render_empty('商品データがまだ登録されていません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
