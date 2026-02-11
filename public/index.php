<?php
declare(strict_types=1);

require_once __DIR__ . '/partials/_helpers.php';
require_once __DIR__ . '/../lib/repository.php';

$pageTitle = 'トップ | PinkClub-FANZA';
$pageDescription = '新着作品、注目作品、女優・シリーズ・メーカー・ジャンルを実データで表示します。';
$canonicalUrl = canonical_url('/index.php');

$newItems = fetch_items('date_published_desc', 10, 0);
$pickupItems = fetch_items('date_published_desc', 10, 10); // TODO: PV等が入ったら人気順に差し替え
$actresses = fetch_actresses(20, 0);
$series = fetch_series(18, 0);
$makers = fetch_makers(18, 0);
$genres = fetch_genres(18, 0);

shuffle($actresses);
shuffle($series);
shuffle($makers);
shuffle($genres);

$actresses = array_slice($actresses, 0, 5);
$series = array_slice($series, 0, 18);
$makers = array_slice($makers, 0, 18);
$genres = array_slice($genres, 0, 18);

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/nav_search.php';
?>
<div class="layout">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="main-content">
        <section class="block">
            <div class="section-head">
                <h2 class="section-title">新着</h2>
                <span class="section-sub">最新配信順</span>
            </div>
            <div class="product-grid product-grid--4">
                <?php foreach (array_slice($newItems, 0, 4) as $item) : ?>
                    <article class="product-card">
                        <a class="product-card__media" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>">
                            <img src="<?php echo e($item['image_small'] ?: $item['image_large']); ?>" alt="<?php echo e($item['title']); ?>">
                        </a>
                        <div class="product-card__body">
                            <a class="product-card__title" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>"><?php echo e($item['title']); ?></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="product-grid product-grid--6">
                <?php foreach (array_slice($newItems, 4) as $item) : ?>
                    <article class="product-card">
                        <a class="product-card__media" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>">
                            <img src="<?php echo e($item['image_small'] ?: $item['image_large']); ?>" alt="<?php echo e($item['title']); ?>">
                        </a>
                        <div class="product-card__body">
                            <a class="product-card__title" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>"><?php echo e($item['title']); ?></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="block">
            <div class="section-head">
                <h2 class="section-title">ピックアップ</h2>
                <span class="section-sub">暫定: 新着順</span>
            </div>
            <div class="product-grid product-grid--4">
                <?php foreach (array_slice($pickupItems, 0, 4) as $item) : ?>
                    <article class="product-card">
                        <a class="product-card__media" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>">
                            <img src="<?php echo e($item['image_small'] ?: $item['image_large']); ?>" alt="<?php echo e($item['title']); ?>">
                        </a>
                        <div class="product-card__body">
                            <a class="product-card__title" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>"><?php echo e($item['title']); ?></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="product-grid product-grid--6">
                <?php foreach (array_slice($pickupItems, 4) as $item) : ?>
                    <article class="product-card">
                        <a class="product-card__media" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>">
                            <img src="<?php echo e($item['image_small'] ?: $item['image_large']); ?>" alt="<?php echo e($item['title']); ?>">
                        </a>
                        <div class="product-card__body">
                            <a class="product-card__title" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>"><?php echo e($item['title']); ?></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="block">
            <div class="section-head">
                <h2 class="section-title">女優</h2>
                <span class="section-sub">ランダム表示</span>
            </div>
            <div class="actress-grid">
                <?php foreach ($actresses as $actress) : ?>
                    <article class="actress-card">
                        <a class="actress-card__media" href="/actress.php?id=<?php echo urlencode((string)$actress['id']); ?>">
                            <img src="<?php echo e($actress['image_small'] ?: $actress['image_large']); ?>" alt="<?php echo e($actress['name']); ?>">
                        </a>
                        <a class="actress-card__name" href="/actress.php?id=<?php echo urlencode((string)$actress['id']); ?>"><?php echo e($actress['name']); ?></a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <?php
        $taxonomies = [
            ['title' => 'シリーズ', 'items' => $series, 'link' => '/series_one.php?id='],
            ['title' => 'メーカー', 'items' => $makers, 'link' => '/maker.php?id='],
            ['title' => 'ジャンル', 'items' => $genres, 'link' => '/genre.php?id='],
        ];
        ?>
        <?php foreach ($taxonomies as $tax) : ?>
            <section class="block">
                <div class="section-head">
                    <h2 class="section-title"><?php echo e($tax['title']); ?></h2>
                    <span class="section-sub">3段シャッフル表示</span>
                </div>
                <div class="tile-rows">
                    <?php foreach (array_chunk($tax['items'], 6) as $rowItems) : ?>
                        <div class="tile-row">
                            <?php foreach ($rowItems as $entry) : ?>
                                <a class="tile-card" href="<?php echo e($tax['link'] . urlencode((string)$entry['id'])); ?>">
                                    <div class="tile-card__media">#<?php echo e((string)$entry['id']); ?></div>
                                    <div class="tile-card__name"><?php echo e($entry['name']); ?></div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </main>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
