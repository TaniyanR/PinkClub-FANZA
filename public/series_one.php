<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_once __DIR__ . '/partials/_helpers.php';
require_once __DIR__ . '/../lib/repository.php';

$id = safe_int($_GET['id'] ?? null, 0, 0, 2147483647);
if ($id < 1) {
    abort_404('404 Not Found', 'シリーズIDが不正です。');
}

$series = fetch_series_one($id);
if ($series === null) {
    abort_404('404 Not Found', '指定のシリーズが見つかりませんでした。');
}

$page = safe_int($_GET['page'] ?? 1, 1, 1, 100000);
$limit = 12;
$offset = ($page - 1) * $limit;
[$items, $hasNext] = paginate_items(fetch_items_by_series((int)$series['id'], $limit + 1, $offset), $limit);

$pageStyles = ['/assets/css/series.css'];
$pageTitle = sprintf('%s | シリーズ', (string)$series['name']);
$pageDescription = sprintf('%s の作品一覧。', (string)$series['name']);
$canonicalUrl = canonical_url('/series_one.php', ['id' => (string)$series['id'], 'page' => $page > 1 ? (string)$page : null]);

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/nav_search.php';
?>
<div class="layout">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="main-content">
        <section class="block"><h1 class="section-title"><?php echo e((string)$series['name']); ?></h1></section>
        <section class="block"><div class="product-grid product-grid--4"><?php foreach ($items as $item) : ?><article class="product-card"><a class="product-card__media" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>"><img src="<?php echo e((string)($item['image_small'] ?: $item['image_large'])); ?>" alt="<?php echo e((string)$item['title']); ?>"></a><div class="product-card__body"><a class="product-card__title" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>"><?php echo e((string)$item['title']); ?></a></div></article><?php endforeach; ?></div></section>
        <nav class="pagination"><?php if ($page > 1) : ?><a class="page-btn" href="<?php echo e(build_url('/series_one.php', ['id' => (string)$series['id'], 'page' => (string)($page - 1)])); ?>">前へ</a><?php else : ?><span class="page-btn">前へ</span><?php endif; ?><span class="page-btn is-current"><?php echo e((string)$page); ?></span><?php if ($hasNext) : ?><a class="page-btn" href="<?php echo e(build_url('/series_one.php', ['id' => (string)$series['id'], 'page' => (string)($page + 1)])); ?>">次へ</a><?php else : ?><span class="page-btn">次へ</span><?php endif; ?></nav>
    </main>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
