<?php
declare(strict_types=1);

require_once __DIR__ . '/partials/_helpers.php';
require_once __DIR__ . '/../lib/repository.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}
$actress = fetch_actress($id);
if (!$actress) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;
[$items, $hasNext] = paginate_items(fetch_items_by_actress((int)$actress['id'], $limit + 1, $offset), $limit);
$relatedSeries = fetch_related_series_by_actress((int)$actress['id']);
$relatedMakers = fetch_related_makers_by_actress((int)$actress['id']);

$pageTitle = sprintf('%s | 女優詳細', $actress['name']);
$pageDescription = sprintf('%s の出演作品一覧。', $actress['name']);
$canonicalUrl = canonical_url('/actress.php', ['id' => $actress['id'], 'page' => $page > 1 ? $page : null]);
$ogImage = (string)($actress['image_large'] ?: $actress['image_small']);

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/nav_search.php';
?>
<div class="layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="main-content">
    <section class="block">
        <div class="section-head"><h1 class="section-title"><?php echo e($actress['name']); ?></h1></div>
        <?php if (!empty($actress['image_large']) || !empty($actress['image_small'])) : ?><img src="<?php echo e($actress['image_large'] ?: $actress['image_small']); ?>" alt="<?php echo e($actress['name']); ?>" style="max-width:320px;"><?php endif; ?>
        <p>読み: <?php echo e($actress['ruby'] ?? '-'); ?> / 身長: <?php echo e((string)($actress['height'] ?? '-')); ?>cm / 誕生日: <?php echo e($actress['birthday'] ?? '-'); ?></p>
    </section>
    <section class="block">
        <div class="section-head"><h2 class="section-title">出演作品</h2></div>
        <div class="product-grid product-grid--4">
            <?php foreach ($items as $item) : ?>
                <article class="product-card"><a class="product-card__media" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>"><img src="<?php echo e($item['image_small'] ?: $item['image_large']); ?>" alt="<?php echo e($item['title']); ?>"></a><div class="product-card__body"><a class="product-card__title" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>"><?php echo e($item['title']); ?></a></div></article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php if ($relatedSeries) : ?><section class="block"><h2 class="section-title">関連シリーズ</h2><div class="tile-row"><?php foreach (array_slice($relatedSeries,0,6) as $s) : ?><a class="tile-card" href="/series_one.php?id=<?php echo urlencode((string)$s['id']); ?>"><?php echo e($s['name']); ?></a><?php endforeach; ?></div></section><?php endif; ?>
    <?php if ($relatedMakers) : ?><section class="block"><h2 class="section-title">関連メーカー</h2><div class="tile-row"><?php foreach (array_slice($relatedMakers,0,6) as $m) : ?><a class="tile-card" href="/maker.php?id=<?php echo urlencode((string)$m['id']); ?>"><?php echo e($m['name']); ?></a><?php endforeach; ?></div></section><?php endif; ?>
    <nav class="pagination"><?php if ($page>1): ?><a class="page-btn" href="/actress.php?id=<?php echo e((string)$actress['id']); ?>&page=<?php echo e((string)($page-1)); ?>">前へ</a><?php else: ?><span class="page-btn">前へ</span><?php endif; ?><span class="page-btn is-current"><?php echo e((string)$page); ?></span><?php if ($hasNext): ?><a class="page-btn" href="/actress.php?id=<?php echo e((string)$actress['id']); ?>&page=<?php echo e((string)($page+1)); ?>">次へ</a><?php else: ?><span class="page-btn">次へ</span><?php endif; ?></nav>
</main></div>
<?php include __DIR__ . '/partials/footer.php'; ?>
