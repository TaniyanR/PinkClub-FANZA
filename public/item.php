<?php
require_once __DIR__ . '/../lib/repository.php';

$cid = $_GET['cid'] ?? '';
$item = $cid ? fetch_item_by_content_id($cid) : null;

if (!$item) {
    $dummy = dummy_items(1);
    $item = $dummy[0];
}

include __DIR__ . '/partials/header.php';
?>
<main>
    <h1><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="notice">DBにデータがない場合はダミーを表示しています。</div>
    <p>発売日: <?php echo htmlspecialchars($item['date_published'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
    <p>価格: <?php echo htmlspecialchars((string)($item['price_min'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
    <p><a href="<?php echo htmlspecialchars($item['affiliate_url'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>">アフィリエイトURL</a></p>
</main>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
