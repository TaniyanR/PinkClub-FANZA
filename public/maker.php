<?php
require_once __DIR__ . '/../lib/repository.php';

$id = (int)($_GET['id'] ?? 0);
$maker = $id ? fetch_maker($id) : null;
if (!$maker) {
    $dummy = dummy_taxonomies(1, 'maker');
    $maker = $dummy[0];
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;
$items = fetch_items_by_maker((int)($maker['id'] ?? 0), $limit, $offset);

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/nav_search.php';
?>
<main>
    <h1><?php echo htmlspecialchars($maker['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
    <h2 class="section-title">メーカー作品</h2>
    <?php if (!$items) : ?>
        <div class="notice">まだデータがありません。</div>
    <?php else : ?>
        <?php
        $railTitle = 'メーカー作品';
        $railItems = $items;
        include __DIR__ . '/partials/block_rail.php';
        ?>
        <div class="pagination">
            <a href="?id=<?php echo urlencode((string)($maker['id'] ?? 0)); ?>&page=<?php echo $page - 1; ?>">前へ</a>
            <a href="?id=<?php echo urlencode((string)($maker['id'] ?? 0)); ?>&page=<?php echo $page + 1; ?>">次へ</a>
        </div>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
