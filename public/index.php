<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/_helpers.php';

$title = 'トップ';
$latest = [];
$pickup = [];
$syncError = null;

try {
    $latest = db()->query('SELECT id,content_id,title,image_small,image_large,price_min,price_min_text FROM items ORDER BY updated_at DESC LIMIT 24')->fetchAll(PDO::FETCH_ASSOC);
    $pickup = db()->query('SELECT id,content_id,title,image_small,image_large,price_min,price_min_text,view_count FROM items ORDER BY view_count DESC, release_date DESC, id DESC LIMIT 12')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $syncError = '商品データの読み込みに失敗しました。管理画面でDB/API設定をご確認ください。';
}

require __DIR__ . '/partials/header.php';
?>
<section class="block home-block">
    <div class="section-head">
        <h1 class="section-title">新着・人気作品</h1>
        <span class="section-sub">FANZAデータ同期済みの商品を表示します</span>
    </div>

    <?php if ($syncError !== null): ?>
        <div class="home-empty-state home-empty-state--error">
            <p><?= e($syncError) ?></p>
            <p class="home-empty-state__links">
                <a class="button button--primary" href="<?= e(app_url('admin/affiliate_api.php')) ?>">API設定を確認</a>
            </p>
        </div>
    <?php elseif ($latest === [] && $pickup === []): ?>
        <div class="home-empty-state">
            <p>まだ商品データがありません。管理画面のAPI設定から「商品情報を10件取得（手動）」または「同期待機」を実行してください。</p>
            <p class="home-empty-state__links">
                <a class="button button--primary" href="<?= e(app_url('admin/affiliate_api.php')) ?>">API設定へ</a>
                <a class="button" href="<?= e(app_url('admin/auto_timer.php')) ?>">自動取得ページへ</a>
            </p>
        </div>
    <?php else: ?>
        <h2 class="home-section-title">ピックアップ（人気順）</h2>
        <?php if ($pickup === []): ?>
            <p class="home-inline-note">ピックアップ対象はまだありません。</p>
        <?php else: ?>
            <div class="product-grid product-grid--4">
                <?php foreach ($pickup as $item): ?>
                    <?php
                    $thumb = (string)($item['image_small'] ?: $item['image_large'] ?: '');
                    $detailUrl = app_url('public/item.php?id=' . (string)$item['id']);
                    ?>
                    <article class="product-card">
                        <a class="product-card__media" href="<?= e($detailUrl) ?>">
                            <?php if ($thumb !== ''): ?>
                                <img class="thumb" src="<?= e($thumb) ?>" alt="<?= e((string)$item['title']) ?>">
                            <?php else: ?>
                                <span class="product-card__noimage">No image</span>
                            <?php endif; ?>
                        </a>
                        <div class="product-card__body">
                            <a class="product-card__title" href="<?= e($detailUrl) ?>"><?= e((string)$item['title']) ?></a>
                            <small><?= e((string)($item['price_min_text'] ?: format_price($item['price_min'] ?? null))) ?> / 閲覧数 <?= e((string)($item['view_count'] ?? 0)) ?></small>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h2 class="home-section-title">最新商品</h2>
        <?php if ($latest === []): ?>
            <p class="home-inline-note">最新商品はまだありません。</p>
        <?php else: ?>
            <div class="product-grid product-grid--4">
                <?php foreach ($latest as $item): ?>
                    <?php
                    $thumb = (string)($item['image_small'] ?: $item['image_large'] ?: '');
                    $detailUrl = app_url('public/item.php?id=' . (string)$item['id']);
                    ?>
                    <article class="product-card">
                        <a class="product-card__media" href="<?= e($detailUrl) ?>">
                            <?php if ($thumb !== ''): ?>
                                <img class="thumb" src="<?= e($thumb) ?>" alt="<?= e((string)$item['title']) ?>">
                            <?php else: ?>
                                <span class="product-card__noimage">No image</span>
                            <?php endif; ?>
                        </a>
                        <div class="product-card__body">
                            <a class="product-card__title" href="<?= e($detailUrl) ?>"><?= e((string)$item['title']) ?></a>
                            <small><?= e((string)($item['price_min_text'] ?: format_price($item['price_min'] ?? null))) ?></small>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="home-more-link"><a href="<?= e(app_url('public/posts.php')) ?>">作品一覧をもっと見る</a></p>
    <?php endif; ?>
</section>

<section class="block home-block only-pc">
    <?php include __DIR__ . '/partials/rss_text_widget.php'; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
