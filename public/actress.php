<?php
require_once __DIR__ . '/../lib/repository.php';

$id = (int)($_GET['id'] ?? 0);
$actress = $id ? fetch_actress($id) : null;
if (!$actress) {
    $dummy = dummy_actresses(1);
    $actress = $dummy[0];
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;
$items = fetch_items_by_actress((int)$actress['id'], $limit, $offset);

include __DIR__ . '/partials/header.php';
?>
<main>
    <h1><?php echo htmlspecialchars($actress['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
    <p>プロフィール（仮）</p>
    <h2 class="section-title">出演作品</h2>
    <div class="rail">
        <?php foreach ($items as $item) : ?>
            <div class="rail-item">
                <?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>
                <div><a href="/item.php?cid=<?php echo urlencode($item['content_id']); ?>">詳細</a></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="pagination">
        <a href="?id=<?php echo urlencode((string)$actress['id']); ?>&page=<?php echo $page - 1; ?>">前へ</a>
        <a href="?id=<?php echo urlencode((string)$actress['id']); ?>&page=<?php echo $page + 1; ?>">次へ</a>
    </div>
</main>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
