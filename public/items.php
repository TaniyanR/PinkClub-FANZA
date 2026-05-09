<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/public_ui.php';

$page = max(1, (int)get('page', 1));
$per = app_config()['pagination']['per_page'] ?? 24;
$total = 0;
$rows = [];

try {
    $totalStmt = db()->query('SELECT COUNT(*) FROM articles WHERE product_id IS NOT NULL AND product_id <> ""');
    $total = (int)$totalStmt->fetchColumn();
} catch (Throwable) {
    $total = 0;
}

$pg = paginate($total, $page, (int)$per);

try {
    $stmt = db()->prepare(
        'SELECT a.product_id, a.title AS article_title, a.image_url AS article_image_url,
                i.id, i.content_id, i.product_id AS item_product_id, i.title AS item_title,
                i.image_large, i.image_list, i.image_small, i.raw_json,
                i.sample_movie_url_720, i.sample_movie_url_644, i.sample_movie_url_560, i.sample_movie_url_476,
                i.release_date, i.price_min_text
         FROM articles a
         LEFT JOIN items i ON (i.content_id = a.product_id OR i.product_id = a.product_id)
         WHERE a.product_id IS NOT NULL AND a.product_id <> ""
         GROUP BY a.id
         ORDER BY a.id DESC
         LIMIT :l OFFSET :o'
    );
    $stmt->bindValue(':l', (int)$pg['perPage'], PDO::PARAM_INT);
    $stmt->bindValue(':o', (int)$pg['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $articleRows = $stmt->fetchAll() ?: [];

    foreach ($articleRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $item = $row;
        $item['title'] = trim((string)($row['item_title'] ?? '')) !== '' ? (string)$row['item_title'] : (string)($row['article_title'] ?? '');
        if (trim((string)($item['content_id'] ?? '')) === '') {
            $item['content_id'] = (string)($row['product_id'] ?? '');
        }
        if (trim((string)($item['product_id'] ?? '')) === '') {
            $item['product_id'] = (string)($row['product_id'] ?? '');
        }
        if (trim((string)($item['image_large'] ?? '')) === '') {
            $item['image_large'] = (string)($row['article_image_url'] ?? '');
        }
        $rows[] = $item;
    }
} catch (Throwable) {
    $rows = [];
}

$title = '商品一覧';
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero('商品一覧', '最新の作品を一覧でチェックできます。'); ?>

<?php if ($rows !== []): ?>
  <section class="pcf-grid pcf-grid--cards" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;">
    <?php foreach ($rows as $r): ?>
      <?php pcf_render_item_card(is_array($r) ? $r : []); ?>
    <?php endforeach; ?>
  </section>
  <?php pcf_render_pagination($pg, public_url('items.php')); ?>
<?php else: ?>
  <?php pcf_render_empty('商品データがまだ登録されていません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
