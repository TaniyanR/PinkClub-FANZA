<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/public_ui.php';

function dedupe_items_for_listing(array $items): array
{
    $seen = [];
    $result = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $contentId = strtolower(trim((string)($item['content_id'] ?? '')));
        $productId = strtolower(trim((string)($item['product_id'] ?? '')));
        $id = trim((string)($item['id'] ?? ''));
        $key = $contentId !== '' ? 'content_id:' . $contentId : ($productId !== '' ? 'product_id:' . $productId : ($id !== '' ? 'id:' . $id : ''));
        if ($key !== '' && isset($seen[$key])) {
            continue;
        }
        if ($key !== '') {
            $seen[$key] = true;
        }
        $result[] = $item;
    }
    return $result;
}



function collect_unique_items_for_items_page(int $limit, int $offset, string $orderSql): array
{
    $rows = [];
    $chunkSize = $limit + 1;
    $cursor = max(0, $offset);
    $maxLoops = 5;

    for ($i = 0; $i < $maxLoops; $i++) {
        $stmt = db()->prepare('SELECT * FROM items ORDER BY ' . $orderSql . ' LIMIT :l OFFSET :o');
        $stmt->bindValue(':l', $chunkSize, PDO::PARAM_INT);
        $stmt->bindValue(':o', $cursor, PDO::PARAM_INT);
        $stmt->execute();
        $chunk = $stmt->fetchAll() ?: [];
        if ($chunk === []) {
            break;
        }

        $rows = dedupe_items_for_listing(array_merge($rows, $chunk));
        if (count($rows) > $limit) {
            break;
        }

        $fetched = count($chunk);
        $cursor += $fetched;
        if ($fetched < $chunkSize) {
            break;
        }
    }

    return $rows;
}
$page = max(1, (int)get('page', 1));
$per = 32;
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
        $rows = collect_unique_items_for_items_page((int)$pg['perPage'], (int)$pg['offset'], $orderSql);
        break;
    } catch (Throwable) {
        $rows = [];
    }
}

$title = '商品一覧';
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero('商品一覧', '最新の作品を一覧でチェックできます。'); ?>

<style>
  .site-main--legacy .pcf-grid.pcf-grid--items {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
  }
  .site-main--legacy .pcf-grid.pcf-grid--items > .pcf-item-card {
    width: auto;
    min-width: 0;
    max-width: none;
  }
</style>

<?php if ($rows !== []): ?>
  <section class="pcf-grid pcf-grid--items">
    <?php foreach ($rows as $r): ?>
      <?php pcf_render_item_card(is_array($r) ? $r : []); ?>
    <?php endforeach; ?>
  </section>
  <?php
    $currentPage = (int)($pg['page'] ?? 1);
    $totalPages = (int)($pg['pages'] ?? 1);
    if ($totalPages > 1):
      $startPage = max(1, min($currentPage, max(1, $totalPages - 4)));
      $endPage = min($totalPages, $startPage + 4);
  ?>
  <nav class="pcf-pagination" aria-label="ページネーション">
    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
      <?php $class = 'pcf-pagination__link' . ($i === $currentPage ? ' is-current' : ''); ?>
      <a class="<?php echo e($class); ?>" href="<?php echo e(public_url('items.php?page=' . $i)); ?>"><?php echo e((string)$i); ?></a>
    <?php endfor; ?>
    <?php if ($currentPage < $totalPages): ?>
      <a class="pcf-pagination__link" href="<?php echo e(public_url('items.php?page=' . ($currentPage + 1))); ?>">次</a>
    <?php endif; ?>
  </nav>
  <?php endif; ?>
<?php else: ?>
  <?php pcf_render_empty('商品データがまだ登録されていません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
