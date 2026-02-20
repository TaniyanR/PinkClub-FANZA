<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_once __DIR__ . '/partials/_helpers.php';
require_once __DIR__ . '/../lib/repository.php';

/**
 * @return array<int, array<string, mixed>>
 */
function front_safe_fetch_list(callable $callback, string $context): array
{
    try {
        $rows = $callback();
        return is_array($rows) ? $rows : [];
    } catch (Throwable $e) {
        app_log_error('Front fetch failed: ' . $context, $e);
        return [];
    }
}

function front_safe_include(string $path, string $context): bool
{
    if (!is_file($path)) {
        app_log_error('Front include file missing: ' . $context . ' (' . $path . ')');
        return false;
    }

    ob_start();
    try {
        include $path;
        $rendered = (string)ob_get_clean();
        echo $rendered;
        return true;
    } catch (Throwable $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        app_log_error('Front include failed: ' . $context, $e);
        return false;
    }
}

$pageStyles = ['/assets/css/home.css', '/assets/css/front.css'];
$pageTitle = 'トップ';
$pageDescription = '新着作品、注目作品、女優・シリーズ・メーカー・ジャンルを実データで表示します。';
$canonicalUrl = canonical_url('/index.php');

$isDbAvailable = (bool)($GLOBALS['front_db_available'] ?? false);
$frontNotice = $isDbAvailable ? '' : '現在、一部データの取得に失敗しています。時間をおいて再度お試しください。';

$newItems = front_safe_fetch_list(static fn(): array => fetch_items('date_published_desc', 10, 0), 'home.new_items');
$pickupItems = front_safe_fetch_list(static fn(): array => fetch_items('popularity_desc', 10, 0), 'home.pickup_items');
$actresses = front_safe_fetch_list(static fn(): array => fetch_actresses(12, 0), 'home.actresses');
$series = front_safe_fetch_list(static fn(): array => fetch_series(12, 0), 'home.series');
$makers = front_safe_fetch_list(static fn(): array => fetch_makers(12, 0), 'home.makers');
$genres = front_safe_fetch_list(static fn(): array => fetch_genres(12, 0), 'home.genres');
$ogImage = isset($newItems[0]['image_large']) ? (string)$newItems[0]['image_large'] : '';

$headerRendered = front_safe_include(__DIR__ . '/partials/header.php', 'header');
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
<?php front_safe_include(__DIR__ . '/partials/nav_search.php', 'nav_search'); ?>
<div class="layout">
    <?php $is_home = true; ?>
    <?php if (!front_safe_include(__DIR__ . '/partials/sidebar.php', 'sidebar')) : ?>
        <aside class="sidebar"><div class="sidebar-block"><h3>サイドメニュー</h3><p>現在、データを読み込めません。</p></div></aside>
    <?php endif; ?>

    <main class="main-content">
        <?php if ($frontNotice !== '') : ?>
            <div class="front-alert" role="status"><?php echo e($frontNotice); ?></div>
        <?php endif; ?>
        <div class="rss-pc-only">
            <?php if (!front_safe_include(__DIR__ . '/partials/rss_text_widget.php', 'rss_text_widget_top')) : ?>
                <div class="block"><p class="front-empty">現在、RSSデータを表示できません。</p></div>
            <?php endif; ?>
        </div>
        <?php if ($isDbAvailable) { render_ad('content_top', 'home', 'pc'); } ?>
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
                <div class="actress-grid">
                    <?php foreach ($actresses as $actress) : ?>
                        <article class="actress-card">
                            <a class="actress-card__media" href="/actress.php?id=<?php echo urlencode((string)$actress['id']); ?>">
                                <img src="<?php echo e((string)($actress['image_small'] ?: $actress['image_large'])); ?>" alt="<?php echo e((string)$actress['name']); ?>">
                            </a>
                            <a class="actress-card__name" href="/actress.php?id=<?php echo urlencode((string)$actress['id']); ?>"><?php echo e((string)$actress['name']); ?></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="front-empty">現在、表示できる女優データがありません。</p>
            <?php endif; ?>
        </section>

        <section class="block">
            <div class="section-head"><h2 class="section-title">シリーズ</h2></div>
            <?php if ($series !== []) : ?>
                <div class="taxonomy-grid">
                    <?php foreach ($series as $entry) : ?>
                        <a class="taxonomy-card" href="/series_one.php?id=<?php echo urlencode((string)$entry['id']); ?>">
                            <div class="taxonomy-card__media">#<?php echo e((string)$entry['id']); ?></div>
                            <div class="taxonomy-card__name"><?php echo e((string)$entry['name']); ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="front-empty">現在、表示できるシリーズデータがありません。</p>
            <?php endif; ?>
        </section>

        <section class="block">
            <div class="section-head"><h2 class="section-title">メーカー</h2></div>
            <?php if ($makers !== []) : ?>
                <div class="taxonomy-grid">
                    <?php foreach ($makers as $entry) : ?>
                        <a class="taxonomy-card" href="/maker.php?id=<?php echo urlencode((string)$entry['id']); ?>">
                            <div class="taxonomy-card__media">#<?php echo e((string)$entry['id']); ?></div>
                            <div class="taxonomy-card__name"><?php echo e((string)$entry['name']); ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="front-empty">現在、表示できるメーカーデータがありません。</p>
            <?php endif; ?>
        </section>

        <section class="block">
            <div class="section-head"><h2 class="section-title">ジャンル</h2></div>
            <?php if ($genres !== []) : ?>
                <div class="taxonomy-grid">
                    <?php foreach ($genres as $entry) : ?>
                        <a class="taxonomy-card" href="/genre.php?id=<?php echo urlencode((string)$entry['id']); ?>">
                            <div class="taxonomy-card__media">#<?php echo e((string)$entry['id']); ?></div>
                            <div class="taxonomy-card__name"><?php echo e((string)$entry['name']); ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="front-empty">現在、表示できるジャンルデータがありません。</p>
            <?php endif; ?>
        </section>
        <?php if ($isDbAvailable) { render_ad('content_bottom', 'home', 'pc'); } ?>
        <div class="rss-pc-only">
            <?php if (!front_safe_include(__DIR__ . '/partials/rss_text_widget.php', 'rss_text_widget_bottom')) : ?>
                <div class="block"><p class="front-empty">現在、RSSデータを表示できません。</p></div>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php if (!front_safe_include(__DIR__ . '/partials/footer.php', 'footer')) : ?>
    <footer class="site-footer"><div class="site-footer__inner">&copy; <?php echo e((string)date('Y')); ?> サイト名未設定</div></footer>
    </div>
    </body>
    </html>
<?php endif; ?>
