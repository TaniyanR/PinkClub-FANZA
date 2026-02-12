<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_once __DIR__ . '/partials/_helpers.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/../lib/app_features.php';

$cid = normalize_content_id((string)($_GET['cid'] ?? ''));
if ($cid === '') {
    abort_404('404 Not Found', '作品IDが指定されていません。');
}

$item = fetch_item_by_content_id($cid);
if ($item === null) {
    $item = fetch_item_by_cid($cid);
}
if ($item === null) {
    abort_404('404 Not Found', '指定の作品が見つかりませんでした。');
}

$images = parse_image_list($item['image_list'] ?? '');
$actresses = fetch_item_actresses((string)$item['content_id']);
$genres = fetch_item_genres((string)$item['content_id']);
$makers = fetch_item_makers((string)$item['content_id']);
$seriesList = fetch_item_series((string)$item['content_id']);
$labels = fetch_item_labels((string)$item['content_id']);

// Use new related items function with better relevance scoring
$related = fetch_related_items((string)$item['content_id'], 6);

$pageStyles = ['/assets/css/detail.css'];
$pageTitle = (string)$item['title'];
$pageDescription = (string)($item['description'] ?? $item['category_name'] ?? $item['title']);
$canonicalUrl = canonical_url('/item.php', ['cid' => (string)$item['content_id']]);
$ogImage = (string)($item['image_large'] ?: $item['image_small']);
$ogType = 'article';
$itemCid = (string)$item['content_id'];

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/nav_search.php';
?>
<div class="layout detail-layout">
    <aside class="sidebar detail-sidebar">
        <div class="sidebar-block">
            <h3>この作品のメタ</h3>
            <ul class="meta-list">
                <?php foreach ($makers as $maker) : ?>
                    <li><a href="/maker.php?id=<?php echo urlencode((string)$maker['id']); ?>">メーカー: <?php echo e((string)$maker['name']); ?></a></li>
                <?php endforeach; ?>
                <?php foreach ($seriesList as $series) : ?>
                    <li><a href="/series_one.php?id=<?php echo urlencode((string)$series['id']); ?>">シリーズ: <?php echo e((string)$series['name']); ?></a></li>
                <?php endforeach; ?>
                <?php foreach ($genres as $genre) : ?>
                    <li><a href="/genre.php?id=<?php echo urlencode((string)$genre['id']); ?>">ジャンル: <?php echo e((string)$genre['name']); ?></a></li>
                <?php endforeach; ?>
                <?php foreach ($actresses as $actress) : ?>
                    <li><a href="/actress.php?id=<?php echo urlencode((string)$actress['id']); ?>">女優: <?php echo e((string)$actress['name']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </aside>

    <main class="main-content detail-page">
        <nav class="breadcrumb" aria-label="breadcrumb">
            <a href="/">ホーム</a><span>/</span><a href="/posts.php">作品一覧</a><span>/</span><span><?php echo e((string)$item['title']); ?></span>
        </nav>

        <section class="detail-title"><h1><?php echo e((string)$item['title']); ?></h1></section>

        <section class="detail-main">
            <div class="detail-left">
                <div class="package-media">
                    <img src="<?php echo e((string)($item['image_large'] ?: $item['image_small'])); ?>" alt="<?php echo e((string)$item['title']); ?>">
                </div>
                <?php if (!empty($item['affiliate_url'])) : ?>
                    <a class="cta-buy" href="<?php echo e((string)$item['affiliate_url']); ?>" target="_blank" rel="noopener">FANZAで購入</a>
                <?php endif; ?>
            </div>
            <div class="detail-right">
                <div class="pill-group">
                    <?php foreach ($makers as $maker) : ?><a href="/maker.php?id=<?php echo urlencode((string)$maker['id']); ?>" class="pill">メーカー: <?php echo e((string)$maker['name']); ?></a><?php endforeach; ?>
                    <?php foreach ($seriesList as $series) : ?><a href="/series_one.php?id=<?php echo urlencode((string)$series['id']); ?>" class="pill">シリーズ: <?php echo e((string)$series['name']); ?></a><?php endforeach; ?>
                    <?php foreach ($genres as $genre) : ?><a href="/genre.php?id=<?php echo urlencode((string)$genre['id']); ?>" class="pill">ジャンル: <?php echo e((string)$genre['name']); ?></a><?php endforeach; ?>
                    <?php foreach ($actresses as $actress) : ?><a href="/actress.php?id=<?php echo urlencode((string)$actress['id']); ?>" class="pill">女優: <?php echo e((string)$actress['name']); ?></a><?php endforeach; ?>
                    <?php foreach ($labels as $label) : ?><span class="pill">レーベル: <?php echo e((string)$label['label_name']); ?></span><?php endforeach; ?>
                </div>
                <p class="detail-description"><?php echo e((string)($item['description'] ?? $item['category_name'] ?? '説明文データは未登録です。')); ?></p>
                <div class="info-grid">
                    <div class="info-card"><span class="info-label">発売日</span><span class="info-value"><?php echo e(format_date($item['date_published'] ?? null)); ?></span></div>
                    <div class="info-card"><span class="info-label">価格</span><span class="info-value"><?php echo e(format_price($item['price_min'] ?? null)); ?></span></div>
                    <div class="info-card"><span class="info-label">品番</span><span class="info-value"><?php echo e((string)($item['product_id'] ?? '')); ?></span></div>
                    <div class="info-card"><span class="info-label">配信サービス</span><span class="info-value"><?php echo e((string)($item['service_code'] ?? '')); ?></span></div>
                </div>
            </div>
        </section>

        <?php if ($images !== []) : ?>
            <section class="detail-samples" id="samples">
                <div class="section-head"><h2 class="section-title">サンプル画像</h2><span class="section-sub"><?php echo e((string)count($images)); ?>枚</span></div>
                <div class="sample-grid">
                    <?php foreach ($images as $img) : ?><img src="<?php echo e($img); ?>" alt="<?php echo e((string)$item['title']); ?> サンプル"><?php endforeach; ?>
                </div>
                <?php if (!empty($item['affiliate_url'])) : ?><a class="cta-buy" href="<?php echo e((string)$item['affiliate_url']); ?>" target="_blank" rel="noopener">FANZAで購入</a><?php endif; ?>
            </section>
        <?php endif; ?>

        <?php $itemInlineAd = (string)app_setting_get('item_inline_ad_html', ''); ?>
        <?php if ($itemInlineAd !== '') : ?>
            <section class="block"><?php echo $itemInlineAd; ?></section>
        <?php else : ?>
            <section class="block"><div class="ad-box">記事内広告枠</div></section>
        <?php endif; ?>

        <?php if ($related !== []) : ?>
            <section class="detail-related" id="related">
                <div class="section-head"><h2 class="section-title">関連作品</h2></div>
                <div class="related-grid">
                    <?php foreach ($related as $rel) : ?>
                        <article class="product-card">
                            <a class="product-card__media" href="/item.php?cid=<?php echo urlencode((string)$rel['content_id']); ?>"><img src="<?php echo e((string)($rel['image_small'] ?: $rel['image_large'])); ?>" alt="<?php echo e((string)$rel['title']); ?>"></a>
                            <div class="product-card__body"><a class="product-card__title" href="/item.php?cid=<?php echo urlencode((string)$rel['content_id']); ?>"><?php echo e((string)$rel['title']); ?></a></div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
