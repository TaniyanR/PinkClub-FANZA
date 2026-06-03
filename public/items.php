<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/_helpers.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

function items_listing_fetch_page(int $page, int $per): array
{
    $page = max(1, $page);
    $per = normalize_int($per, 1, 100);
    $offset = ($page - 1) * $per;

    $sourceWhere = items_product_source_where();
    $whereSql = $sourceWhere !== '' ? ' WHERE ' . $sourceWhere : '';

    $countStmt = db()->query('SELECT COUNT(*) FROM items' . $whereSql);
    $total = (int)($countStmt ? $countStmt->fetchColumn() : 0);
    $pg = paginate($total, $page, $per);

    $stmt = db()->prepare('SELECT * FROM items' . $whereSql . ' ORDER BY release_date DESC, id DESC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', (int)$pg['perPage'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$pg['offset'], PDO::PARAM_INT);
    $stmt->execute();

    return [$stmt->fetchAll() ?: [], $pg];
}

$page = max(1, (int)get('page', 1));
$per = 32;

try {
    [$rows, $pg] = items_listing_fetch_page($page, $per);
} catch (Throwable) {
    $rows = [];
    $pg = paginate(0, $page, $per);
}

$title = '商品一覧';
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero('商品一覧', '最新の作品を一覧でチェックできます。'); ?>

<?php if ($rows !== []): ?>
  <section class="rail-row rail-row--200 rail-row--wide-thumb">
    <?php foreach ($rows as $item): ?>
      <?php pcf_render_item_card(is_array($item) ? $item : [], 200, true); ?>
    <?php endforeach; ?>
  </section>
  <?php pcf_render_pagination($pg, public_url('items.php')); ?>
<?php else: ?>
  <?php pcf_render_empty('商品データがまだ登録されていません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
