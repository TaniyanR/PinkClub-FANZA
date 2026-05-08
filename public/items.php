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
$per = app_config()['pagination']['per_page'] ?? 24;
$total = 0;
$rows = [];

try {
    $total = (int)db()->query('SELECT COUNT(*) FROM items')->fetchColumn();
} catch (Throwable) {
    $total = 0;
}

$pg = paginate($total, $page, (int)$per);

$dedupeOffset = max(0, ((int)$pg['page'] - 1) * (int)$pg['perPage']);
$dedupeLimit = max(1, (int)$pg['perPage']);

$orderSqlCandidates = [
    'release_date DESC, id DESC',
    'date_published DESC, id DESC',
    'updated_at DESC, id DESC',
    'id DESC',
];
foreach ($orderSqlCandidates as $orderSql) {
    try {
        $uniqueRows = [];
        $readOffset = 0;
        while (count($uniqueRows) < $dedupeOffset + $dedupeLimit) {
            $stmt = db()->prepare('SELECT * FROM items ORDER BY ' . $orderSql . ' LIMIT :l OFFSET :o');
            $stmt->bindValue(':l', (int)$dedupeLimit, PDO::PARAM_INT);
            $stmt->bindValue(':o', (int)$readOffset, PDO::PARAM_INT);
            $stmt->execute();
            $chunk = $stmt->fetchAll() ?: [];
            if ($chunk === []) {
                break;
            }
            $uniqueRows = dedupe_items_for_listing(array_merge($uniqueRows, $chunk));
            $readOffset += count($chunk);
        }
        $rows = array_slice($uniqueRows, $dedupeOffset, $dedupeLimit);
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
  <section class="pcf-grid pcf-grid--cards">
    <?php foreach ($rows as $r): ?>
      <?php pcf_render_item_card(is_array($r) ? $r : []); ?>
    <?php endforeach; ?>
  </section>
  <?php pcf_render_pagination($pg, public_url('items.php')); ?>
<?php else: ?>
  <?php pcf_render_empty('商品データがまだ登録されていません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
