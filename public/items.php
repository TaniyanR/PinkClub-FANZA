<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/public_ui.php';
require_once __DIR__ . '/../lib/repository.php';

$page = max(1, (int)get('page', 1));
$per = (int)(app_config()['pagination']['per_page'] ?? 32);
$total = 0;
$rows = [];
$sourceWhere = function_exists('items_product_source_where') ? items_product_source_where() : '';
$whereSql = $sourceWhere !== '' ? ' WHERE ' . $sourceWhere : '';

try {
    $total = (int)db()->query('SELECT COUNT(*) FROM items' . $whereSql)->fetchColumn();
} catch (Throwable) {
    $total = 0;
}

$pg = paginate($total, $page, $per);

try {
    $stmt = db()->prepare('SELECT * FROM items' . $whereSql . ' ORDER BY release_date DESC, id DESC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', (int)$pg['perPage'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$pg['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll() ?: [];
} catch (Throwable) {
    $rows = [];
}

$title = '商品一覧';
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero('商品一覧', '最新の作品を一覧でチェックできます。'); ?>

<?php if ($rows !== []): ?>
  <section class="rail-section">
    <div class="rail-row rail-row--200 rail-row--wide-thumb">
      <?php foreach ($rows as $item): ?>
        <?php pcf_render_item_card(is_array($item) ? $item : [], 200, true); ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php pcf_render_pagination($pg, public_url('items.php')); ?>
<?php else: ?>
  <?php pcf_render_empty('商品データがまだ登録されていません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
