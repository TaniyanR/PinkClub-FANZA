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

$page = max(1, (int)get('page', 1));
$per = 32;
$total = 0;
$rows = [];

try {
    $totalSql = "SELECT COUNT(*) FROM (SELECT COALESCE(NULLIF(content_id, ''), NULLIF(product_id, ''), CAST(id AS CHAR)) AS uniq_key FROM items GROUP BY uniq_key) AS deduped_items";
    $total = (int)db()->query($totalSql)->fetchColumn();
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
        $sql = 'SELECT i.* FROM items i INNER JOIN ('
            . "SELECT MAX(id) AS id FROM items GROUP BY COALESCE(NULLIF(content_id, ''), NULLIF(product_id, ''), CAST(id AS CHAR))"
            . ') u ON u.id = i.id ORDER BY ' . $orderSql . ' LIMIT :l OFFSET :o';
        $stmt = db()->prepare($sql);
        $stmt->bindValue(':l', (int)$pg['perPage'], PDO::PARAM_INT);
        $stmt->bindValue(':o', (int)$pg['offset'], PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        $rows = dedupe_items_for_listing($rows);
        break;
    } catch (Throwable) {
        $rows = [];
    }
}

$title = '商品一覧';
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero('商品一覧', '最新の作品を一覧でチェックできます。'); ?>

<?php if ($rows !== []): ?>
  <section class="rail-section">
    <?php foreach (array_chunk($rows, 4) as $rowChunk): ?>
      <div class="rail-row rail-row--no-scroll">
        <?php foreach ($rowChunk as $r): ?>
          <?php pcf_render_item_card(is_array($r) ? $r : []); ?>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </section>
  <?php pcf_render_pagination($pg, public_url('items.php'), [], 5); ?>
<?php else: ?>
  <?php pcf_render_empty('商品データがまだ登録されていません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
