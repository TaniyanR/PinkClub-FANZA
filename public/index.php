<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_once __DIR__ . '/partials/_helpers.php';
require_once __DIR__ . '/../lib/repository.php';

$pageStyles = ['/assets/home.css', '/assets/css/front.css'];
$pageTitle = 'トップ';
$pageDescription = '新着作品、注目作品、女優・シリーズ・メーカー・ジャンルを実データで表示します。';
$canonicalUrl = canonical_url('/index.php');

$frontErrors = [];

$frontSafeFetch = static function (callable $fetcher, string $context, mixed $fallback) use (&$frontErrors) {
    try {
        $result = $fetcher();
        return $result ?? $fallback;
    } catch (Throwable $e) {
        app_log_error('front fetch failed: ' . $context, $e);
        $frontErrors[$context] = true;
        return $fallback;
    }
};

$newItems = $frontSafeFetch(static fn(): array => fetch_items('date_published_desc', 10, 0), 'new_items', []);
$pickupItems = $frontSafeFetch(static fn(): array => fetch_items('popularity_desc', 10, 0), 'pickup_items', []);
$actresses = $frontSafeFetch(static fn(): array => fetch_actresses(12, 0), 'actresses', []);
$series = $frontSafeFetch(static fn(): array => fetch_series(12, 0), 'series', []);
$makers = $frontSafeFetch(static fn(): array => fetch_makers(12, 0), 'makers', []);
$genres = $frontSafeFetch(static fn(): array => fetch_genres(12, 0), 'genres', []);
$ogImage = isset($newItems[0]['image_large']) ? (string)$newItems[0]['image_large'] : '';

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/nav_search.php';
?>
<div class="layout">
    <?php $is_home = true; ?>
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="main-content">
        <div class="rss-pc-only">
            <?php include __DIR__ . '/partials/rss_text_widget.php'; ?>
        </div>
        <?php if (!front_db_available() || $frontErrors !== []) : ?>
            <p class="front-alert"><?php echo e(front_generic_error_message()); ?></p>
        <?php endif; ?>
        <?php render_ad('content_top', 'home', 'pc'); ?>
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
            <?php if ($actresses === []) : ?>
                <p class="front-empty">現在、表示できるデータがありません。</p>
            <?php else : ?>
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
            <?php endif; ?>
        </section>

        <section class="block">
            <div class="section-head"><h2 class="section-title">シリーズ</h2></div>
            <?php if ($series === []) : ?>
                <p class="front-empty">現在、表示できるデータがありません。</p>
            <?php else : ?>
            <div class="taxonomy-grid">
                <?php foreach ($series as $entry) : ?>
                    <a class="taxonomy-card" href="/series_one.php?id=<?php echo urlencode((string)$entry['id']); ?>">
                        <div class="taxonomy-card__media">#<?php echo e((string)$entry['id']); ?></div>
                        <div class="taxonomy-card__name"><?php echo e((string)$entry['name']); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <section class="block">
            <div class="section-head"><h2 class="section-title">メーカー</h2></div>
            <?php if ($makers === []) : ?>
                <p class="front-empty">現在、表示できるデータがありません。</p>
            <?php else : ?>
            <div class="taxonomy-grid">
                <?php foreach ($makers as $entry) : ?>
                    <a class="taxonomy-card" href="/maker.php?id=<?php echo urlencode((string)$entry['id']); ?>">
                        <div class="taxonomy-card__media">#<?php echo e((string)$entry['id']); ?></div>
                        <div class="taxonomy-card__name"><?php echo e((string)$entry['name']); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <section class="block">
            <div class="section-head"><h2 class="section-title">ジャンル</h2></div>
            <?php if ($genres === []) : ?>
                <p class="front-empty">現在、表示できるデータがありません。</p>
            <?php else : ?>
            <div class="taxonomy-grid">
                <?php foreach ($genres as $entry) : ?>
                    <a class="taxonomy-card" href="/genre.php?id=<?php echo urlencode((string)$entry['id']); ?>">
                        <div class="taxonomy-card__media">#<?php echo e((string)$entry['id']); ?></div>
                        <div class="taxonomy-card__name"><?php echo e((string)$entry['name']); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
        <?php render_ad('content_bottom', 'home', 'pc'); ?>
        <div class="rss-pc-only">
            <?php include __DIR__ . '/partials/rss_text_widget.php'; ?>
        </div>
    </main>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
