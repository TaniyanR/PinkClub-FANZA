<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/_helpers.php';
require_once __DIR__ . '/../lib/repository.php';

$isDbAvailable = function_exists('front_db_available') ? front_db_available() : (($GLOBALS['front_db_available'] ?? false) === true);
$frontNotice = '';

$newItems = [];
$pickupItems = [];
$actresses = [];
$seriesList = [];
$makers = [];
$genres = [];

if ($isDbAvailable) {
    try {
        $newItems = fetch_items('date_published_desc', 12, 0);
        $pickupItems = fetch_items('popularity_desc', 12, 0);
        $actresses = fetch_actresses(18, 0, 'name');
        $seriesList = fetch_series(18, 0, 'name');
        $makers = fetch_makers(18, 0, 'name');
        $genres = fetch_genres(18, 0, 'name');
    } catch (Throwable $e) {
        app_log_error('index data fetch failed', $e);
        $frontNotice = '一部データの取得に失敗したため、表示を縮小しています。';
    }
} else {
    $frontNotice = '現在DBに接続できないため、一部コンテンツを表示できません。';
}

$pageTitle = 'トップ';
$pageDescription = '新着作品、注目作品、女優・シリーズ・メーカー・ジャンルを実データで表示します。';
$canonicalUrl = canonical_url('/index.php');
$ogImage = isset($newItems[0]['image_large']) ? (string)$newItems[0]['image_large'] : '';

$includePartialSafe = static function (string $path, string $label): bool {
    try {
        include $path;
        return true;
    } catch (Throwable $e) {
        app_log_error('front partial include failed: ' . $label, $e);
        return false;
    }
};

$headerRendered = $includePartialSafe(__DIR__ . '/partials/header.php', 'header');
if (!$headerRendered) : ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
    <link rel="stylesheet" href="/assets/css/common.css">
    <link rel="stylesheet" href="/assets/css/front.css">
</head>
<body>
<header class="site-header"><div class="site-header__inner"><a class="site-header__title" href="/">サイト名未設定</a></div></header>
<div class="site-body">
<?php endif; ?>
<?php $includePartialSafe(__DIR__ . '/partials/nav_search.php', 'nav_search'); ?>
<div class="layout">
    <?php $is_home = true; ?>
    <?php if ($isDbAvailable && $includePartialSafe(__DIR__ . '/partials/sidebar.php', 'sidebar')) : ?>
    <?php else : ?>
        <aside class="sidebar"><div class="sidebar-block"><h3>サイドメニュー</h3><p>現在、データを読み込めません。</p></div></aside>
    <?php endif; ?>

    <main class="main-content">
        <?php if ($frontNotice !== '') : ?>
            <div class="front-alert" role="status"><?php echo e($frontNotice); ?></div>
        <?php endif; ?>

        <section class="block">
            <div class="section-head">
                <h1 class="section-title">新着作品</h1>
                <span class="section-sub">最新配信順</span>
            </div>
            <?php if ($newItems !== []) : ?>
                <div class="product-grid product-grid--4">
                    <?php foreach ($newItems as $item) : ?>
                        <article class="product-card">
                            <a class="product-card__media" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>">
                                <img src="<?php echo e((string)($item['image_small'] ?: $item['image_large'])); ?>" alt="<?php echo e((string)$item['title']); ?>">
                            </a>
                            <div class="product-card__body">
                                <a class="product-card__title" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>"><?php echo e((string)$item['title']); ?></a>
                                <small><?php echo e(format_date($item['date_published'] ?? null)); ?> / <?php echo e(format_price($item['price_min'] ?? null)); ?></small>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="front-empty">現在、表示できる作品がありません。</p>
            <?php endif; ?>
        </section>

        <section class="block">
            <div class="section-head">
                <h2 class="section-title">ピックアップ</h2>
                <span class="section-sub">人気順</span>
            </div>
            <?php if ($pickupItems !== []) : ?>
                <div class="product-grid product-grid--4">
                    <?php foreach ($pickupItems as $item) : ?>
                        <article class="product-card">
                            <a class="product-card__media" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>">
                                <img src="<?php echo e((string)($item['image_small'] ?: $item['image_large'])); ?>" alt="<?php echo e((string)$item['title']); ?>">
                            </a>
                            <div class="product-card__body">
                                <a class="product-card__title" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>"><?php echo e((string)$item['title']); ?></a>
                                <small><?php echo e(format_date($item['date_published'] ?? null)); ?> / <?php echo e(format_price($item['price_min'] ?? null)); ?></small>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="front-empty">現在、表示できる作品がありません。</p>
            <?php endif; ?>
        </section>

        <section class="block">
            <div class="section-head"><h2 class="section-title">女優</h2></div>
            <?php if ($actresses !== []) : ?>
                <ul class="chip-list">
                    <?php foreach ($actresses as $row) : ?>
                        <li><a href="/actress.php?id=<?php echo urlencode((string)$row['id']); ?>"><?php echo e((string)$row['name']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="front-empty">現在、表示できるデータがありません。</p>
            <?php endif; ?>
        </section>

        <section class="block">
            <div class="section-head"><h2 class="section-title">シリーズ</h2></div>
            <?php if ($seriesList !== []) : ?>
                <ul class="chip-list">
                    <?php foreach ($seriesList as $row) : ?>
                        <li><a href="/series_one.php?id=<?php echo urlencode((string)$row['id']); ?>"><?php echo e((string)$row['name']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="front-empty">現在、表示できるデータがありません。</p>
            <?php endif; ?>
        </section>

        <section class="block">
            <div class="section-head"><h2 class="section-title">メーカー</h2></div>
            <?php if ($makers !== []) : ?>
                <ul class="chip-list">
                    <?php foreach ($makers as $row) : ?>
                        <li><a href="/maker.php?id=<?php echo urlencode((string)$row['id']); ?>"><?php echo e((string)$row['name']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="front-empty">現在、表示できるデータがありません。</p>
            <?php endif; ?>
        </section>

        <section class="block">
            <div class="section-head"><h2 class="section-title">ジャンル</h2></div>
            <?php if ($genres !== []) : ?>
                <ul class="chip-list">
                    <?php foreach ($genres as $row) : ?>
                        <li><a href="/genre.php?id=<?php echo urlencode((string)$row['id']); ?>"><?php echo e((string)$row['name']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="front-empty">現在、表示できるデータがありません。</p>
            <?php endif; ?>
        </section>

        <?php if ($isDbAvailable) { render_ad('content_bottom', 'home', 'pc'); } ?>
    </main>
</div>
<?php if (!$includePartialSafe(__DIR__ . '/partials/footer.php', 'footer')) : ?>
    <footer class="site-footer"><div class="site-footer__inner">&copy; <?php echo e((string)date('Y')); ?> サイト名未設定</div></footer>
    </div>
    </body>
    </html>
<?php endif; ?>
