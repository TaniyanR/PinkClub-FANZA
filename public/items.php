<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/public_ui.php';

$page = max(1, (int)get('page', 1));
$per = app_config()['pagination']['per_page'] ?? 24;
$total = 0;
$rows = [];

try {
    $total = (int)db()->query('SELECT COUNT(*) FROM items')->fetchColumn();
} catch (Throwable) {
    $total = 0;
}

$pg = paginate($total, $page, (int)$per);

$orderSqlCandidates = [
    'release_date DESC, id DESC',
    'date_published DESC, id DESC',
    'updated_at DESC, id DESC',
    'id DESC',
];
foreach ($orderSqlCandidates as $orderSql) {
    try {
        $stmt = db()->prepare('SELECT * FROM items ORDER BY ' . $orderSql . ' LIMIT :l OFFSET :o');
        $stmt->bindValue(':l', (int)$pg['perPage'], PDO::PARAM_INT);
        $stmt->bindValue(':o', (int)$pg['offset'], PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        break;
    } catch (Throwable) {
        $rows = [];
    }
}

$title = '商品一覧';
require __DIR__ . '/partials/header.php';
?>
<?php if (get_ad_code('content_top') !== null): ?><div class="site-ad"><?php render_ad('content_top', 'list', 'pc'); ?></div><?php endif; ?>
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

<?php if (get_ad_code('content_bottom') !== null): ?><div class="site-ad"><?php render_ad('content_bottom', 'list', 'pc'); ?></div><?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>