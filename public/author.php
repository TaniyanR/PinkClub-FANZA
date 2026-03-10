<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/public_ui.php';
require_once __DIR__ . '/../lib/repository.php';

$id = (int)get('id', 0);
$stmt = db()->prepare('SELECT * FROM authors WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    exit('not found');
}

$list = [];
if (db_table_exists('item_authors')) {
    try {
        $itemStmt = db()->prepare('SELECT items.* FROM items INNER JOIN item_authors ia ON items.content_id = ia.content_id WHERE ia.author_id = :id ORDER BY items.date_published DESC LIMIT 100');
        $itemStmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        $itemStmt->execute();
        $list = $itemStmt->fetchAll() ?: [];
    } catch (Throwable) {
        try {
            $itemStmt = db()->prepare('SELECT items.* FROM items INNER JOIN item_authors ia ON items.id = ia.item_id WHERE ia.author_id = :id ORDER BY items.date_published DESC LIMIT 100');
            $itemStmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            $itemStmt->execute();
            $list = $itemStmt->fetchAll() ?: [];
        } catch (Throwable) {
            $list = [];
        }
    }
}

$title = (string)($row['name'] ?? '作者詳細');
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => '作者一覧', 'url' => public_url('authors.php')],
    ['label' => (string)($row['name'] ?? '作者詳細')],
]); ?>
<?php pcf_render_hero((string)($row['name'] ?? '作者詳細')); ?>
<?php if (!empty($row['ruby'])): ?><p class="pcf-list-card__meta">読み: <?= e((string)$row['ruby']) ?></p><?php endif; ?>

<h2 class="pcf-section-title">関連商品</h2>
<?php if ($list !== []): ?>
  <section class="pcf-related-grid">
    <?php foreach ($list as $item): pcf_render_item_card(is_array($item) ? $item : []); endforeach; ?>
  </section>
<?php else: ?>
  <?php pcf_render_empty('この作者の関連商品はまだありません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
