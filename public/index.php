<?php
declare(strict_types=1);

$q = trim((string)($_GET['q'] ?? ''));
$pageStyles = ['/assets/home.css'];
$pageScripts = ['/assets/home.js'];

include __DIR__ . '/partials/header.php';
?>
<!-- partial: header -->
<!-- partial: search -->
<div class="search-bar">
    <div class="search-bar-inner">
        <div class="search-note"><strong>当サイトはアフィリエイト広告を使用しています。</strong></div>
        <form method="get" action="/index.php" class="search-form">
            <input type="text" name="q" placeholder="作品名・女優名で検索" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit">検索</button>
        </form>
    </div>
</div>

<div class="layout">
    <!-- partial: sidebar -->
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="main-content">
        <?php if ($q !== '') : ?>
            <section class="block">
                <div class="section-head">
                    <h2 class="section-title">検索中</h2>
                    <span class="section-sub">キーワード: <?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <p>検索結果はダミー表示です。</p>
            </section>
        <?php endif; ?>

        <!-- partial: block_new -->
        <section class="block block-new">
            <div class="section-head">
                <h2 class="section-title">新着</h2>
                <span class="section-sub">最新の投稿を表示する想定</span>
            </div>
            <div class="product-grid product-grid--4" data-grid="new-top"></div>
            <div class="product-grid product-grid--6" data-grid="new-bottom"></div>
        </section>

        <!-- partial: block_pickup -->
        <section class="block block-pickup">
            <div class="section-head">
                <h2 class="section-title">ピックアップ</h2>
                <span class="section-sub">アクセスが多い作品の想定</span>
            </div>
            <div class="product-grid product-grid--4" data-grid="pickup-top"></div>
            <div class="product-grid product-grid--6" data-grid="pickup-bottom"></div>
        </section>

        <!-- partial: block_actress -->
        <section class="block block-actress">
            <div class="section-head">
                <h2 class="section-title">女優</h2>
                <span class="section-sub">注目の5名を表示</span>
            </div>
            <div class="actress-grid" data-grid="actress"></div>
        </section>

        <!-- partial: block_genre -->
        <section class="block block-genre">
            <div class="section-head">
                <h2 class="section-title">ジャンル</h2>
                <span class="section-sub">3段シャッフル表示</span>
            </div>
            <div class="tile-rows" data-rows="genre"></div>
        </section>

        <!-- partial: block_series -->
        <section class="block block-series">
            <div class="section-head">
                <h2 class="section-title">シリーズ</h2>
                <span class="section-sub">3段シャッフル表示</span>
            </div>
            <div class="tile-rows" data-rows="series"></div>
        </section>

        <!-- partial: block_maker -->
        <section class="block block-maker">
            <div class="section-head">
                <h2 class="section-title">メーカー</h2>
                <span class="section-sub">3段シャッフル表示</span>
            </div>
            <div class="tile-rows" data-rows="maker"></div>
        </section>
    </main>
</div>

<!-- partial: footer -->
<?php include __DIR__ . '/partials/footer.php'; ?>
